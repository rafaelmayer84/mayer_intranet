<?php

namespace App\Services\Justus;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JustusClaudeService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('justus.anthropic_api_key', '');
        $this->model = config('justus.claude_model', 'claude-sonnet-4-5-20250929');
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Redige peça jurídica com base na análise do GPT.
     */
    public function redraft(string $gptAnalysis, string $documentType, array $caseContext = []): array
    {
        if (!$this->isAvailable()) {
            return ['success' => false, 'error' => 'Claude API não configurada'];
        }

        $systemPrompt = $this->buildRedactionPrompt($documentType, $caseContext);

        $userPrompt = "ANÁLISE DO CASO (gerada por IA de diagnóstico):\n\n{$gptAnalysis}\n\n"
            . "TAREFA: Com base nesta análise, redija a peça jurídica indicada. "
            . "Siga rigorosamente as instruções do system prompt quanto a formato, tom e estrutura.";

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 8000,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

            if (!$response->successful()) {
                $err = $response->json('error.message') ?? $response->body();
                Log::error('JUSTUS Claude: API error', ['status' => $response->status(), 'error' => $err]);
                return ['success' => false, 'error' => "Claude API: {$err}"];
            }

            $data = $response->json();
            $content = $data['content'][0]['text'] ?? '';
            $inputTokens = $data['usage']['input_tokens'] ?? 0;
            $outputTokens = $data['usage']['output_tokens'] ?? 0;

            $costUsd = ($inputTokens * config('justus.claude_pricing.input_per_million_usd', 3.0) / 1_000_000)
                     + ($outputTokens * config('justus.claude_pricing.output_per_million_usd', 15.0) / 1_000_000);
            $costBrl = round($costUsd * config('justus.usd_brl', 5.80), 6);

            return [
                'success' => true,
                'content' => $content,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_usd' => round($costUsd, 6),
                'cost_brl' => $costBrl,
                'model' => $this->model,
            ];

        } catch (\Exception $e) {
            Log::error('JUSTUS Claude: Exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Claude: ' . $e->getMessage()];
        }
    }

    /**
     * Avalia se conteúdo do GPT precisa de redação Claude.
     */
    public function needsClaudeRedaction(string $gptResponse, string $conversationType): bool
    {
        $redactionTypes = ['peca', 'parecer', 'contrato', 'notificacao'];
        if (in_array($conversationType, $redactionTypes)) {
            return true;
        }

        $redactionSignals = [
            'PETIÇÃO', 'CONTESTAÇÃO', 'RECURSO', 'AGRAVO', 'APELAÇÃO',
            'EMBARGOS', 'MANIFESTAÇÃO', 'PARECER', 'NOTIFICAÇÃO',
            'CONTRARRAZÕES', 'IMPUGNAÇÃO', 'RÉPLICA', 'MEMORIAIS',
            'EXCELENTÍSSIMO', 'EGRÉGIO', 'MERITÍSSIMO',
            'requer a Vossa Excelência', 'ante o exposto',
        ];

        $upper = mb_strtoupper($gptResponse);
        $matches = 0;
        foreach ($redactionSignals as $signal) {
            if (mb_strpos($upper, mb_strtoupper($signal)) !== false) {
                $matches++;
            }
        }

        return $matches >= 2;
    }

    private function buildRedactionPrompt(string $documentType, array $caseContext): string
    {
        $partes = '';
        if (!empty($caseContext['autor'])) {
            $partes .= "AUTOR/REQUERENTE: {$caseContext['autor']}\n";
        }
        if (!empty($caseContext['reu'])) {
            $partes .= "RÉU/REQUERIDO: {$caseContext['reu']}\n";
        }
        if (!empty($caseContext['cnj'])) {
            $partes .= "PROCESSO: {$caseContext['cnj']}\n";
        }
        if (!empty($caseContext['vara'])) {
            $partes .= "VARA/JUÍZO: {$caseContext['vara']}\n";
        }

        return <<<PROMPT
Você é um redator jurídico de elite do escritório Mayer Advogados (SC). Sua função EXCLUSIVA é redigir peças processuais e documentos jurídicos de alta qualidade técnica.

REGRAS DE REDAÇÃO INVIOLÁVEIS:
1. ESCRITA EM TERCEIRA PESSOA (nunca "eu", nunca "nós"). Usar: "o Requerente", "a parte autora", "o escritório subscritor".
2. DIRIGIR-SE AO JUÍZO, nunca à pessoa do juiz. Usar: "Excelentíssimo Juízo da X Vara", "esse Douto Juízo", "esse Egrégio Tribunal". NUNCA "Vossa Excelência o Juiz".
3. TOM: técnico, formal, assertivo. Sem adjetivação excessiva, sem retórica vazia, sem clichês forenses desnecessários.
4. ESTRUTURA: parágrafos densos e argumentativos. NÃO usar bullets, listas numeradas ou tópicos — PROSA CONTÍNUA.
5. FUNDAMENTAÇÃO: citar artigos de lei com precisão. NUNCA fabricar jurisprudência — usar apenas "conforme entendimento consolidado dos Tribunais" ou similar quando necessário, sem inventar números de acórdãos.
6. PEDIDOS: claros, objetivos, em itens numerados (exceção à regra de prosa — pedidos podem ser em itens).
7. NÃO incluir cabeçalho do escritório nem assinatura — serão inseridos no documento final.
8. Formatação: usar **negrito** para destaques essenciais. Usar CAPS apenas em títulos de seções (DOS FATOS, DO DIREITO, DOS PEDIDOS).

TIPO DE DOCUMENTO: {$documentType}

{$partes}

[REVISÃO OBRIGATÓRIA: Este conteúdo foi gerado por IA e deve ser integralmente revisado pelo advogado responsável antes de qualquer utilização — Normativo AD003]
PROMPT;
    }
}
