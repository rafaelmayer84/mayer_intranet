<?php

namespace App\Services\Crm;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gera checklist jurídico personalizado para processos administrativos
 * usando Claude Opus via Anthropic API.
 */
class CrmAdminProcessChecklistAiService
{
    private string $apiKey;
    private string $model = 'claude-sonnet-4-6';

    public function __construct()
    {
        $this->apiKey = config('justus.anthropic_api_key', '');
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Extrai JSON de uma resposta que pode vir envolto em ```json ... ```.
     */
    private function parseJsonResponse(string $text): ?array
    {
        $text = trim($text);

        // Remove bloco markdown ```json ... ``` se presente
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $m)) {
            $text = trim($m[1]);
        }

        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['items']) && is_array($decoded['items'])) {
            return $decoded;
        }

        return null;
    }

    /**
     * Extrai texto de um arquivo Word (.doc ou .docx).
     */
    public function extrairTextoDocx(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Arquivo não encontrado: {$filePath}");
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        Log::info('CrmAdminProcessChecklistAI: lendo Word', [
            'path' => $filePath,
            'ext'  => $ext,
            'size' => filesize($filePath),
        ]);

        // Determinar o reader correto com base na extensão e conteúdo
        $readerName = match ($ext) {
            'doc'  => 'MsDoc',
            'docx' => 'Word2007',
            default => null,
        };

        if ($readerName) {
            $reader  = \PhpOffice\PhpWord\IOFactory::createReader($readerName);
            $phpWord = $reader->load($filePath);
        } else {
            // Fallback: auto-detectar
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
        }

        $text = '';
        foreach ($phpWord->getSections() as $section) {
            $text .= $this->extractElementText($section);
        }

        return trim($text);
    }

    /**
     * Extrai texto recursivamente de qualquer elemento PhpWord.
     */
    private function extractElementText($element): string
    {
        $text = '';

        if (method_exists($element, 'getText')) {
            $t = $element->getText();
            if (is_string($t)) {
                $text .= $t . "\n";
            } elseif (is_array($t)) {
                // TextRun retorna array de sub-elementos
                foreach ($t as $sub) {
                    if (is_string($sub)) $text .= $sub;
                    elseif (method_exists($sub, 'getText')) {
                        $st = $sub->getText();
                        if (is_string($st)) $text .= $st;
                    }
                }
                $text .= "\n";
            }
        }

        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child) {
                $text .= $this->extractElementText($child);
            }
        }

        return $text;
    }

    /**
     * Lê um .docx e usa Claude para extrair os itens de checklist contidos nele.
     *
     * @return array{success: bool, items: string[], error?: string}
     */
    public function extrairChecklistDeDocx(string $filePath, string $processoTitulo = ''): array
    {
        if (!$this->isAvailable()) {
            return ['success' => false, 'items' => [], 'error' => 'Claude API não configurada'];
        }

        try {
            $textoDocumento = $this->extrairTextoDocx($filePath);
        } catch (\Exception $e) {
            Log::error('CrmAdminProcessChecklistAI: erro ao ler .docx', ['msg' => $e->getMessage()]);
            return ['success' => false, 'items' => [], 'error' => 'Não foi possível ler o arquivo Word: ' . $e->getMessage()];
        }

        if (empty($textoDocumento)) {
            return ['success' => false, 'items' => [], 'error' => 'O arquivo não contém texto legível.'];
        }

        $systemPrompt = <<<'PROMPT'
Você é um assistente jurídico especializado em documentação de processos extrajudiciais brasileiros.
Receberá o conteúdo de um documento Word que contém um checklist de documentos necessários para um processo.

Sua tarefa é extrair APENAS os itens de checklist (documentos, certidões, requisitos) contidos no documento.

REGRAS:
- Extraia somente itens que representem documentos, certidões ou requisitos a serem obtidos/apresentados.
- Ignore títulos, cabeçalhos, rodapés, notas explicativas e textos que não sejam itens de lista.
- Normalize o texto: capitalize a primeira letra, remova numeração e marcadores (•, -, 1., etc.).
- Não repita itens.
- Responda SOMENTE com JSON no formato: {"items": ["item 1", "item 2", ...]}
- Nenhum texto antes ou depois do JSON.
PROMPT;

        $contexto = $processoTitulo ? "Processo: {$processoTitulo}\n\n" : '';
        $userPrompt = "{$contexto}Conteúdo do documento:\n\n{$textoDocumento}";

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model'      => $this->model,
                'max_tokens' => 1024,
                'system'     => $systemPrompt,
                'messages'   => [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

            if (!$response->successful()) {
                $err = $response->json('error.message') ?? $response->body();
                return ['success' => false, 'items' => [], 'error' => "Claude API: {$err}"];
            }

            $text    = $response->json('content.0.text', '');
            $decoded = $this->parseJsonResponse($text);

            if (!$decoded) {
                Log::warning('CrmAdminProcessChecklistAI: JSON inválido', ['text' => $text]);
                return ['success' => false, 'items' => [], 'error' => 'Resposta da IA em formato inesperado.'];
            }

            $items = array_values(array_filter(array_map('trim', $decoded['items'])));

            return ['success' => true, 'items' => $items];

        } catch (\Exception $e) {
            Log::error('CrmAdminProcessChecklistAI: exceção ao extrair do docx', ['msg' => $e->getMessage()]);
            return ['success' => false, 'items' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Gera checklist de documentos para o processo.
     *
     * @return array{success: bool, items: string[], error?: string}
     */
    public function gerarChecklist(
        string $tipo,
        string $tipoLabel,
        string $titulo,
        string $descricao = '',
        string $clienteNome = ''
    ): array {
        if (!$this->isAvailable()) {
            return ['success' => false, 'items' => [], 'error' => 'Claude API não configurada'];
        }

        $systemPrompt = <<<'PROMPT'
Você é um advogado especialista em direito notarial, registral e extrajudicial brasileiro,
com profundo conhecimento em documentação cartorária e exigências dos órgãos públicos.

Sua tarefa é gerar um checklist preciso de documentos necessários para um processo administrativo/extrajudicial
no escritório Mayer Advogados, em Santa Catarina, Brasil.

REGRAS:
- Liste APENAS documentos realmente necessários para o caso específico.
- Seja específico: em vez de "documentos pessoais", liste "RG e CPF do outorgante".
- Inclua certidões com prazo de validade quando relevante (ex: "Certidão de matrícula atualizada — validade 30 dias").
- Priorize pela ordem lógica de obtenção.
- Não repita itens.
- Gere entre 5 e 20 itens conforme a complexidade do caso.
- Responda SOMENTE com um JSON no formato: {"items": ["item 1", "item 2", ...]}
- Nenhum texto antes ou depois do JSON.
PROMPT;

        $contexto = "Tipo de processo: {$tipoLabel}\nTítulo: {$titulo}";
        if ($clienteNome) $contexto .= "\nCliente: {$clienteNome}";
        if ($descricao)   $contexto .= "\n\nDescrição do caso:\n{$descricao}";

        $userPrompt = "Gere o checklist de documentos para o seguinte processo:\n\n{$contexto}";

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model'      => $this->model,
                'max_tokens' => 1024,
                'system'     => $systemPrompt,
                'messages'   => [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

            if (!$response->successful()) {
                $err = $response->json('error.message') ?? $response->body();
                Log::error('CrmAdminProcessChecklistAI: API error', ['status' => $response->status(), 'error' => $err]);
                return ['success' => false, 'items' => [], 'error' => "Claude API: {$err}"];
            }

            $text = $response->json('content.0.text', '');
            $decoded = $this->parseJsonResponse($text);

            if (!$decoded) {
                Log::warning('CrmAdminProcessChecklistAI: JSON inválido em gerarChecklist', ['text' => $text]);
                return ['success' => false, 'items' => [], 'error' => 'Resposta da IA em formato inesperado.'];
            }

            $items = array_values(array_filter(array_map('trim', $decoded['items'])));

            return ['success' => true, 'items' => $items];

        } catch (\Exception $e) {
            Log::error('CrmAdminProcessChecklistAI: exceção', ['msg' => $e->getMessage()]);
            return ['success' => false, 'items' => [], 'error' => $e->getMessage()];
        }
    }

    // ── Geração de Etapas a partir do Checklist ───────────────────────────────

    /**
     * Gera etapas (fases) do processo a partir dos itens de checklist.
     *
     * @param  string[] $checklistItems
     * @return array{success: bool, steps: array[], error?: string}
     */
    public function gerarEtapas(
        array $checklistItems,
        string $tipoProcesso,
        string $tituloProcesso
    ): array {
        if (!$this->isAvailable()) {
            return ['success' => false, 'steps' => [], 'error' => 'Claude API não configurada'];
        }

        if (empty($checklistItems)) {
            return ['success' => false, 'steps' => [], 'error' => 'Nenhum item de checklist fornecido.'];
        }

        $systemPrompt = <<<'PROMPT'
Você é um advogado especialista em direito notarial, registral e extrajudicial brasileiro.
Receberá o tipo de processo, o título e a lista de documentos do checklist.

Sua tarefa é criar as ETAPAS (fases macro) de execução do processo, agrupando logicamente os documentos.

REGRAS:
- Crie entre 4 e 10 etapas, cada uma representando uma fase distinta do trabalho.
- Ordene as etapas na sequência lógica de execução.
- Cada etapa deve ter: titulo (nome curto da fase), tipo (interno|externo|cliente|aprovacao) e deadline_days (dias úteis estimados).
  - "interno" = trabalho do escritório
  - "externo" = depende de cartório/órgão público
  - "cliente" = depende de documentação/ação do cliente
  - "aprovacao" = etapa de validação/conferência
- Use nomes objetivos: "Due diligence registral", "Documentação pessoal dos outorgantes", etc.
- Responda SOMENTE com JSON: {"steps": [{"titulo": "...", "tipo": "...", "deadline_days": N}, ...]}
- Nenhum texto antes ou depois do JSON.
PROMPT;

        $listaChecklist = implode("\n", array_map(fn($i, $item) => ($i + 1) . ". {$item}", array_keys($checklistItems), $checklistItems));

        $userPrompt = "Tipo: {$tipoProcesso}\nTítulo: {$tituloProcesso}\n\nChecklist de documentos:\n{$listaChecklist}";

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model'      => $this->model,
                'max_tokens' => 1024,
                'system'     => $systemPrompt,
                'messages'   => [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

            if (!$response->successful()) {
                $err = $response->json('error.message') ?? $response->body();
                return ['success' => false, 'steps' => [], 'error' => "Claude API: {$err}"];
            }

            $text    = $response->json('content.0.text', '');
            $decoded = $this->parseJsonSteps($text);

            if (!$decoded) {
                Log::warning('CrmAdminProcessChecklistAI: JSON inválido em gerarEtapas', ['text' => $text]);
                return ['success' => false, 'steps' => [], 'error' => 'Resposta da IA em formato inesperado.'];
            }

            return ['success' => true, 'steps' => $decoded];

        } catch (\Exception $e) {
            Log::error('CrmAdminProcessChecklistAI: exceção em gerarEtapas', ['msg' => $e->getMessage()]);
            return ['success' => false, 'steps' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Extrai array de steps de uma resposta JSON (com ou sem markdown).
     */
    private function parseJsonSteps(string $text): ?array
    {
        $text = trim($text);

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $m)) {
            $text = trim($m[1]);
        }

        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['steps']) && is_array($decoded['steps'])) {
            return $decoded['steps'];
        }

        return null;
    }
}
