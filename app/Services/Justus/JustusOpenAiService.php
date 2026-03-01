<?php

namespace App\Services\Justus;

use App\Models\JustusConversation;
use App\Models\JustusMessage;
use App\Models\JustusStyleGuide;
use App\Models\SystemEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\Justus\JustusClaudeService;

class JustusOpenAiService
{
    private JustusBudgetService $budget;
    private JustusClaudeService $claude;
    private JustusRagService $rag;

    public function __construct(JustusBudgetService $budget, JustusRagService $rag)
    {
        $this->budget = $budget;
        $this->rag = $rag;
    }

    public function sendMessage(JustusConversation $conversation, string $userMessage): array
    {
        $userId = $conversation->user_id;
        $budgetCheck = $this->budget->canProceed($userId);

        if (!$budgetCheck['allowed']) {
            return [
                'success' => false,
                'error' => $budgetCheck['blocked_reason'],
                'blocked' => true,
            ];
        }

        $userMsg = JustusMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        $chunks = $this->rag->retrieveRelevantChunks($conversation, $userMessage);
        $attachmentId = $conversation->attachments()->where('processing_status', 'completed')->value('id');
        $ragContext = $this->rag->buildContextFromChunks($chunks, $attachmentId);

        $mode = $conversation->mode ?? 'consultor';
        $styleGuide = JustusStyleGuide::where('mode', $mode)->where('is_active', true)->orderByDesc('version')->first();
        if (!$styleGuide) {
            $styleGuide = JustusStyleGuide::where('is_active', true)->orderByDesc('version')->first();
        }
        $systemPrompt = $styleGuide ? $styleGuide->system_prompt : 'Você é o JUSTUS, assistente jurídico do escritório Mayer Advogados.';
        $behaviorRules = $styleGuide->behavior_rules ?? '';
        $ad003 = $styleGuide->ad003_disclaimer ?? '';
        $styleVersion = $styleGuide ? $styleGuide->version : 1;

        $profile = $conversation->processProfile;
        $profileContext = '';
        if ($profile) {
            $profileContext = "\n\nDADOS DO PROCESSO:\n";
            $profileContext .= "Número CNJ: " . ($profile->numero_cnj ?: '[dado não localizado nos autos]') . "\n";
            $profileContext .= "Fase: " . ($profile->fase_atual ?: '[dado não localizado nos autos]') . "\n";
            $profileContext .= "Autor: " . ($profile->autor ?: '[dado não localizado nos autos]') . "\n";
            $profileContext .= "Réu: " . ($profile->reu ?: '[dado não localizado nos autos]') . "\n";
            $profileContext .= "Objetivo: " . ($profile->objetivo_analise ?: '[não definido]') . "\n";
            $profileContext .= "Tese Principal: " . ($profile->tese_principal ?: '[não definida]') . "\n";
            $profileContext .= "Limites: " . ($profile->limites_restricoes ?: '[nenhum definido]') . "\n";
        }

        $typeContext = "Tipo de análise: " . ($conversation->type_label ?? 'Análise') . "\n";
        if ($conversation->type === 'peca') {
            $typeContext .= "ATENÇÃO: Esta é uma solicitação de PEÇA PROCESSUAL. Antes de redigir, confirme seu entendimento com o advogado.\n";
        }

        $fullSystemPrompt = $behaviorRules . "\n\n" . $ad003 . "\n\n" . $systemPrompt . "\n\n" . $typeContext . $profileContext . "\n\n" . $ragContext;
        $messages = [
            ['role' => 'system', 'content' => $fullSystemPrompt],
        ];

        $history = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('id')
            ->get()
            ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        $messages = array_merge($messages, $history);

        $model = config('justus.model_default');
        $apiKey = config('justus.openai_api_key');

        if (empty($apiKey)) {
            return [
                'success' => false,
                'error' => 'Chave da API OpenAI não configurada (JUSTUS_OPENAI_API_KEY).',
                'blocked' => false,
            ];
        }

        try {
            $payload = [
                'model' => $model,
                'messages' => $messages,
                'max_completion_tokens' => 4096,
                'temperature' => 0.3,
            ];

            $response = Http::withToken($apiKey)
                ->timeout(120)
                ->post('https://api.openai.com/v1/chat/completions', $payload);

            if (!$response->successful()) {
                $errorBody = $response->json();
                $errorMsg = $errorBody['error']['message'] ?? 'Erro na API OpenAI: HTTP ' . $response->status();
                Log::error('JUSTUS OpenAI Error', ['status' => $response->status(), 'body' => $errorBody]);

                SystemEvent::sistema('justus', 'error', 'JUSTUS: Erro OpenAI', null, ['conversation_id' => $conversation->id]);

                return ['success' => false, 'error' => $errorMsg, 'blocked' => false];
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';
            $usage = $data['usage'] ?? [];

            $inputTokens = $usage['prompt_tokens'] ?? 0;
            $outputTokens = $usage['completion_tokens'] ?? 0;
            $costBrl = $this->budget->calculateCost($inputTokens, $outputTokens, $model);

            $assistantMsg = JustusMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $content,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_brl' => $costBrl,
                'model_used' => $model,
                'style_version' => $styleVersion,
                'citations' => $this->extractCitations($content),
            ]);

            $conversation->increment('total_input_tokens', $inputTokens);
            $conversation->increment('total_output_tokens', $outputTokens);
            $conversation->update([
                'total_cost_brl' => $conversation->total_cost_brl + $costBrl,
                'style_version' => $styleVersion,
            ]);

            $this->budget->recordUsage($userId, $inputTokens, $outputTokens, $costBrl);

            // Pipeline Claude: avaliar se precisa redação jurídica
            $claudeDocPath = null;
            if ($this->claude->isAvailable() && $this->claude->needsClaudeRedaction($content, $conversation->type)) {
                $caseContext = [];
                $profile = $conversation->profile;
                if ($profile) {
                    $caseContext = [
                        'autor' => $profile->autor ?? '',
                        'reu' => $profile->reu ?? '',
                        'cnj' => $profile->numero_cnj ?? '',
                        'vara' => $profile->vara ?? '',
                    ];
                }

                $claudeResult = $this->claude->redraft($content, $conversation->type, $caseContext);

                if ($claudeResult['success']) {
                    // Gerar DOCX
                    $docPath = $this->generateDocx($claudeResult['content'], $conversation);

                    // Salvar mensagem Claude como segunda resposta
                    $claudeMsg = $conversation->messages()->create([
                        'role' => 'assistant',
                        'content' => '📄 Peça jurídica redigida por Claude e disponível para download. Custo da redação: R$ ' . number_format($claudeResult['cost_brl'], 4, ',', '.'),
                        'model_used' => $claudeResult['model'],
                        'input_tokens' => $claudeResult['input_tokens'],
                        'output_tokens' => $claudeResult['output_tokens'],
                        'cost_brl' => $claudeResult['cost_brl'],
                        'metadata' => json_encode([
                            'pipeline' => 'claude_redaction',
                            'doc_path' => $docPath,
                            'cost_usd' => $claudeResult['cost_usd'],
                        ]),
                    ]);

                    // Registrar custo Claude no budget
                    $this->budget->recordUsage($userId, $claudeResult['input_tokens'], $claudeResult['output_tokens'], $claudeResult['cost_brl']);
                    $claudeDocPath = $docPath;

                    Log::info('JUSTUS Claude: Peça gerada', [
                        'conversation_id' => $conversation->id,
                        'model' => $claudeResult['model'],
                        'tokens' => $claudeResult['input_tokens'] + $claudeResult['output_tokens'],
                        'cost_brl' => $claudeResult['cost_brl'],
                        'doc' => $docPath,
                    ]);
                } else {
                    Log::warning('JUSTUS Claude: Falha na redação', ['error' => $claudeResult['error']]);
                }
            }

            // Auto-gerar titulo na primeira mensagem
            if ($conversation->messages()->where('role', 'assistant')->count() === 1 && $conversation->title === 'Nova Analise') {
                $this->generateTitle($conversation, $userMessage, $content);
            }

            SystemEvent::sistema('justus', 'info', 'JUSTUS: Resposta gerada', null, ['conversation_id' => $conversation->id, 'model' => $model, 'cost' => $costBrl]);

            return [
                'success' => true,
                'message' => $assistantMsg,
                'tokens' => ['input' => $inputTokens, 'output' => $outputTokens],
                'cost_brl' => $costBrl,
                'claude_doc' => $claudeDocPath,
            ];
        } catch (\Exception $e) {
            Log::error('JUSTUS OpenAI Exception', ['error' => $e->getMessage(), 'conversation_id' => $conversation->id]);

            SystemEvent::sistema('justus', 'error', 'JUSTUS: Erro OpenAI', null, ['conversation_id' => $conversation->id]);

            return ['success' => false, 'error' => 'Erro interno: ' . $e->getMessage(), 'blocked' => false];
        }
    }

    private function generateDocx(string $content, JustusConversation $conversation): ?string
    {
        try {
            $dir = storage_path('app/public/justus');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $filename = 'justus_peca_' . $conversation->id . '_' . date('Ymd_His') . '.docx';
            $path = $dir . '/' . $filename;

            $phpWord = new \PhpOffice\PhpWord\PhpWord();
            $phpWord->getDefaultFontName('Times New Roman');
            $phpWord->getDefaultFontSize(12);

            $section = $phpWord->addSection([
                'marginTop' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(3),
                'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
                'marginLeft' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(3),
                'marginRight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1.5),
            ]);

            // Processar conteudo markdown -> DOCX
            $lines = explode("
", $content);
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (empty($trimmed)) {
                    $section->addTextBreak();
                    continue;
                }

                // Títulos em CAPS (DOS FATOS, DO DIREITO, etc.)
                if (preg_match('/^(#{1,3}\s+)?([A-ZÁÀÂÃÉÈÊÍÏÓÔÕÚÇ\s\-–—\.]+)$/', $trimmed, $m) && mb_strlen($trimmed) < 80) {
                    $title = trim($m[2] ?? $trimmed);
                    $section->addText($title, ['bold' => true, 'size' => 13, 'name' => 'Times New Roman'], ['alignment' => 'center', 'spaceBefore' => 240, 'spaceAfter' => 120]);
                    continue;
                }

                // Parágrafos normais com negrito inline
                $textRun = $section->addTextRun(['alignment' => 'both', 'lineHeight' => 1.5, 'spaceAfter' => 120, 'indent' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2)]);
                $parts = preg_split('/(\*\*[^*]+\*\*)/', $trimmed, -1, PREG_SPLIT_DELIM_CAPTURE);
                foreach ($parts as $part) {
                    if (preg_match('/^\*\*(.+)\*\*$/', $part, $bm)) {
                        $textRun->addText($bm[1], ['bold' => true, 'size' => 12, 'name' => 'Times New Roman']);
                    } else {
                        $textRun->addText($part, ['size' => 12, 'name' => 'Times New Roman']);
                    }
                }
            }

            // Disclaimer AD003
            $section->addTextBreak(2);
            $section->addText(
                '[REVISÃO OBRIGATÓRIA: Este conteúdo foi gerado por IA e deve ser integralmente revisado pelo advogado responsável — Normativo AD003]',
                ['italic' => true, 'size' => 9, 'color' => '999999', 'name' => 'Times New Roman'],
                ['alignment' => 'center']
            );

            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($path);

            return 'justus/' . $filename;

        } catch (\Exception $e) {
            Log::error('JUSTUS DOCX: Erro ao gerar', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function generateTitle(JustusConversation $conversation, string $userMessage, string $assistantResponse): void
    {
        try {
            $apiKey = config('justus.openai_api_key');
            $snippet = mb_substr($userMessage, 0, 300);
            $respSnippet = mb_substr($assistantResponse, 0, 200);

            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-5-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Gere um titulo curto (maximo 6 palavras) para esta conversa juridica. Sem aspas, sem pontuacao final. O titulo deve identificar rapidamente o caso. Exemplos: Rescisao Indireta Gerente Varejista, Execucao Fiscal Empresa X, Revisao Contratual Locacao Comercial, Honorarios Sucumbencia Excesso Execucao.'],
                        ['role' => 'user', 'content' => "Pergunta: {$snippet}
Resposta: {$respSnippet}"],
                    ],
                    'max_completion_tokens' => 150,
                ]);

            if ($response->successful()) {
                $title = trim($response->json('choices.0.message.content') ?? '');
                $title = str_replace(['"', "'", '.', '!', '?'], '', $title);
                if (!empty($title) && mb_strlen($title) <= 60) {
                    $conversation->update(['title' => $title]);
                }
            }
        } catch (\Exception $e) {
            Log::warning('JUSTUS: Falha ao gerar titulo', ['error' => $e->getMessage()]);
        }
    }

    private function extractCitations(string $content): array
    {
        $citations = [];
        if (preg_match_all('/\(p\.\s*(\d+)(?:\s*[–-]\s*(\d+))?\)/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $citations[] = [
                    'page_start' => (int) $match[1],
                    'page_end' => isset($match[2]) ? (int) $match[2] : (int) $match[1],
                ];
            }
        }
        return $citations;
    }
}
