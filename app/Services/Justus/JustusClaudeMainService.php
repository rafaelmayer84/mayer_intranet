<?php

namespace App\Services\Justus;

use App\Jobs\JustusMetadataJob;
use App\Models\JustusConversation;
use App\Models\JustusMessage;
use App\Models\JustusStyleGuide;
use App\Models\SystemEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JustusClaudeMainService
{
    private JustusBudgetService $budget;
    private JustusRagService $rag;
    private JustusJurisprudenciaService $jurisprudencia;

    public function __construct(
        JustusBudgetService $budget,
        JustusRagService $rag,
        JustusJurisprudenciaService $jurisprudencia
    ) {
        $this->budget = $budget;
        $this->rag = $rag;
        $this->jurisprudencia = $jurisprudencia;
    }

    public function sendMessage(JustusConversation $conversation, string $userMessage, bool $fullContext = false): array
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

        JustusMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        if ($fullContext) {
            $chunks = $this->rag->retrieveAllChunks($conversation);
            Log::info('JUSTUS: Modo ANÁLISE COMPLETA ativado', [
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
                'total_chunks' => $chunks->count(),
            ]);
        } else {
            $chunks = $this->rag->retrieveRelevantChunks($conversation, $userMessage);
        }
        $usedChunkIds = $chunks->pluck('id')->toArray();
        $attachmentId = $conversation->attachments()->where('processing_status', 'completed')->value('id');
        $ragContext = $this->rag->buildContextFromChunks($chunks, $attachmentId);

        $mode = $conversation->mode ?? 'consultor';
        $styleGuide = JustusStyleGuide::where('mode', $mode)->where('is_active', true)->orderByDesc('version')->first()
            ?? JustusStyleGuide::where('is_active', true)->orderByDesc('version')->first();

        $systemPrompt = $styleGuide ? $styleGuide->system_prompt : 'Você é o JUSTUS, assistente jurídico do escritório Mayer Advogados.';
        $behaviorRules = $styleGuide->behavior_rules ?? '';
        $ad003 = $styleGuide->ad003_disclaimer ?? '';
        $styleVersion = $styleGuide ? $styleGuide->version : 1;

        $profile = $conversation->processProfile;
        $profileContext = $this->buildProfileContext($profile);

        $typeContext = "Tipo de análise: " . ($conversation->type_label ?? 'Análise') . "\n";
        if ($conversation->type === 'peca') {
            $typeContext .= "ATENÇÃO: Esta é uma solicitação de PEÇA PROCESSUAL. Antes de redigir, confirme seu entendimento com o advogado.\n";
        }

        // Para peças processuais, injetar regras de redação jurídica diretamente no system prompt
        $pecaRules = $conversation->type === 'peca' ? $this->buildPecaRules($profile) : '';

        $jurisResult = $this->jurisprudencia->searchForPrompt($conversation, $userMessage);
        $jurisContext = $jurisResult['context'] ?? '';
        if ($jurisResult['found']) {
            Log::info('JUSTUS: Jurisprudência injetada', [
                'conversation_id' => $conversation->id,
                'count' => $jurisResult['count'],
                'refs' => $jurisResult['references'],
            ]);
        }

        $fullSystemPrompt = implode("\n\n", array_filter([
            $behaviorRules,
            $ad003,
            $systemPrompt,
            $typeContext . $profileContext,
            $pecaRules,
            $ragContext,
            $jurisContext,
        ]));

        // Claude API: histórico em messages[], system é parâmetro top-level
        $history = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('id')
            ->get()
            ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        $heavyTypes = ['peca', 'analise_completa'];
        $model = in_array($conversation->type, $heavyTypes)
            ? config('justus.claude_opus_model', 'claude-opus-4-7')
            : config('justus.claude_main_model', 'claude-sonnet-4-6');
        $apiKey = config('justus.anthropic_api_key');

        if (empty($apiKey)) {
            return [
                'success' => false,
                'error' => 'Chave da API Anthropic não configurada (JUSTUS_ANTHROPIC_API_KEY).',
                'blocked' => false,
            ];
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 16000,
                'system' => $fullSystemPrompt,
                'messages' => $history,
            ]);

            if (!$response->successful()) {
                $errorBody = $response->json();
                $errorMsg = $errorBody['error']['message'] ?? 'Erro na API Anthropic: HTTP ' . $response->status();
                Log::error('JUSTUS Claude Error', ['status' => $response->status(), 'body' => $errorBody]);
                SystemEvent::sistema('justus', 'error', 'JUSTUS: Erro Claude', null, ['conversation_id' => $conversation->id]);
                return ['success' => false, 'error' => $errorMsg, 'blocked' => false];
            }

            $data = $response->json();
            $content = $data['content'][0]['text'] ?? '';

            if (empty(trim($content))) {
                Log::error('JUSTUS: Content vazio na resposta Claude', [
                    'conversation_id' => $conversation->id,
                    'stop_reason' => $data['stop_reason'] ?? 'unknown',
                ]);
                SystemEvent::sistema('justus', 'warning', 'JUSTUS: Resposta vazia da IA', null, ['conversation_id' => $conversation->id]);
                return [
                    'success' => false,
                    'error' => 'A IA processou a solicitação mas não gerou resposta visível. Tente reformular a pergunta de forma mais específica.',
                    'blocked' => false,
                ];
            }

            $usage = $data['usage'] ?? [];
            $inputTokens = $usage['input_tokens'] ?? 0;
            $outputTokens = $usage['output_tokens'] ?? 0;
            $costBrl = $this->budget->calculateCost($inputTokens, $outputTokens, $model);

            // Para peças processuais, gerar DOCX automaticamente
            $claudeDocPath = null;
            if ($conversation->type === 'peca') {
                $claudeDocPath = $this->generateDocx($content, $conversation);
            }

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
                'metadata' => json_encode(['chunk_ids' => $usedChunkIds, 'doc_path' => $claudeDocPath]),
            ]);

            $conversation->increment('total_input_tokens', $inputTokens);
            $conversation->increment('total_output_tokens', $outputTokens);
            $conversation->update([
                'total_cost_brl' => $conversation->total_cost_brl + $costBrl,
                'style_version' => $styleVersion,
            ]);

            $this->budget->recordUsage($userId, $inputTokens, $outputTokens, $costBrl);

            $assistantCount = $conversation->messages()->where('role', 'assistant')->count();
            JustusMetadataJob::dispatch($conversation->id, $userMessage, $content, $assistantCount);

            SystemEvent::sistema('justus', 'info', 'JUSTUS: Resposta gerada', null, [
                'conversation_id' => $conversation->id,
                'model' => $model,
                'cost' => $costBrl,
            ]);

            return [
                'success' => true,
                'message' => $assistantMsg,
                'tokens' => ['input' => $inputTokens, 'output' => $outputTokens],
                'cost_brl' => $costBrl,
                'claude_doc' => $claudeDocPath,
            ];

        } catch (\Exception $e) {
            Log::error('JUSTUS Claude Exception', ['error' => $e->getMessage(), 'conversation_id' => $conversation->id]);
            SystemEvent::sistema('justus', 'error', 'JUSTUS: Erro Claude', null, ['conversation_id' => $conversation->id]);
            return ['success' => false, 'error' => 'Erro interno: ' . $e->getMessage(), 'blocked' => false];
        }
    }

    private function buildProfileContext($profile): string
    {
        if (!$profile) return '';

        $fields = [];
        if ($profile->numero_cnj) $fields[] = "Número CNJ: {$profile->numero_cnj}";
        if ($profile->classe) $fields[] = "Classe: {$profile->classe}";
        if ($profile->orgao) $fields[] = "Órgão: {$profile->orgao}";
        if ($profile->autor) $fields[] = "Autor/Exequente: {$profile->autor}";
        if ($profile->reu) $fields[] = "Réu/Executado: {$profile->reu}";
        if ($profile->relator_vara) $fields[] = "Vara/Relator: {$profile->relator_vara}";
        if ($profile->fase_atual) $fields[] = "Fase: {$profile->fase_atual}";
        if ($profile->tese_principal) $fields[] = "Tese Principal: {$profile->tese_principal}";
        if ($profile->objetivo_analise) $fields[] = "Objetivo: {$profile->objetivo_analise}";
        if ($profile->limites_restricoes) $fields[] = "Limites: {$profile->limites_restricoes}";

        return empty($fields) ? '' : "\n\nDADOS DO PROCESSO:\n" . implode("\n", $fields) . "\n";
    }

    private function buildPecaRules($profile): string
    {
        $partes = '';
        if ($profile) {
            if (!empty($profile->autor)) $partes .= "AUTOR/REQUERENTE: {$profile->autor}\n";
            if (!empty($profile->reu)) $partes .= "RÉU/REQUERIDO: {$profile->reu}\n";
            if (!empty($profile->numero_cnj)) $partes .= "PROCESSO: {$profile->numero_cnj}\n";
            if (!empty($profile->relator_vara)) $partes .= "VARA/JUÍZO: {$profile->relator_vara}\n";
        }

        return <<<RULES
REGRAS DE REDAÇÃO JURÍDICA INVIOLÁVEIS:
1. ESCRITA EM TERCEIRA PESSOA (nunca "eu", nunca "nós"). Usar: "o Requerente", "a parte autora", "o escritório subscritor".
2. DIRIGIR-SE AO JUÍZO, nunca à pessoa do juiz. Usar: "Excelentíssimo Juízo da X Vara", "esse Douto Juízo". NUNCA "Vossa Excelência o Juiz".
3. TOM: técnico, formal, assertivo. Sem retórica vazia, sem clichês forenses desnecessários.
4. ESTRUTURA: parágrafos densos e argumentativos. NÃO usar bullets, listas numeradas ou tópicos — PROSA CONTÍNUA.
5. FUNDAMENTAÇÃO: citar artigos de lei com precisão. NUNCA fabricar jurisprudência — usar "conforme entendimento consolidado dos Tribunais" quando necessário, sem inventar números de acórdãos.
6. PEDIDOS: claros, objetivos, em itens numerados (exceção à regra de prosa).
7. NÃO incluir cabeçalho do escritório nem assinatura — serão inseridos no documento final.
8. Formatação: usar **negrito** para destaques essenciais. Usar CAPS apenas em títulos de seções (DOS FATOS, DO DIREITO, DOS PEDIDOS).

{$partes}
RULES;
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

            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (empty($trimmed)) {
                    $section->addTextBreak();
                    continue;
                }

                if (preg_match('/^(#{1,3}\s+)?([A-ZÁÀÂÃÉÈÊÍÏÓÔÕÚÇ\s\-–—\.]+)$/', $trimmed, $m) && mb_strlen($trimmed) < 80) {
                    $title = trim($m[2] ?? $trimmed);
                    $section->addText($title, ['bold' => true, 'size' => 13, 'name' => 'Times New Roman'], ['alignment' => 'center', 'spaceBefore' => 240, 'spaceAfter' => 120]);
                    continue;
                }

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
