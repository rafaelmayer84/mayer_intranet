<?php

/**
 * ============================================================================
 * SIRIC v2 — SiricOpenAIService
 * ============================================================================
 *
 * Serviço de integração com OpenAI para análise de crédito inteligente.
 *
 * Responsabilidades:
 * - Gerar Relatório Final de análise de crédito via IA (único estágio na v2)
 * - Construir prompts anti-alucinação com citação obrigatória de dados
 * - Gerenciar chamada à API com fallback de modelo e timeout
 * - Parsear e validar resposta JSON da IA
 * - Registrar metadados de uso (tokens, modelo, timestamp) para auditoria
 *
 * v2 mudanças:
 * - Gate Decision REMOVIDO da IA (agora é determinístico no SiricService)
 * - Modelo padrão trocado de gpt-5.2 para o3 (reasoning, +17% aderência)
 * - Prompt exige citação de dados específicos para cada fator da análise
 * - Recebe dados Serasa já estruturados (não mais texto bruto)
 * - Não recebe mais serasa_data duplicado dentro do gate_result
 * ============================================================================
 */

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SiricOpenAIService
{
    private string $model;
    private string $fallbackModel;
    private int $maxTokens;
    private float $temperature;

    public function __construct()
    {
        $this->model = config('services.siric.openai_model', 'o3');
        $this->fallbackModel = config('services.siric.openai_fallback', 'gpt-5.2');
        $this->maxTokens = (int) config('services.siric.openai_max_tokens', 4000);
        $this->temperature = (float) config('services.siric.openai_temperature', 0.3);
    }

    /**
     * Gera Relatório Final de análise de crédito.
     *
     * Na v2, este é o ÚNICO estágio de IA. O gate decision é determinístico (SiricService).
     *
     * @param array      $dadosFormulario Dados do formulário da consulta
     * @param array      $snapshot        Snapshot interno coletado do BD
     * @param array      $gateResult      Gate decision determinístico (do SiricService)
     * @param array|null $dadosSerasa     Dados do Serasa ESTRUTURADOS (se consultado)
     * @return array ['success' => bool, 'relatorio' => array|null, 'model_used' => string|null, 'error' => string|null]
     */
    public function gerarRelatorioFinal(array $dadosFormulario, array $snapshot, array $gateResult, ?array $dadosSerasa = null): array
    {
        try {
            $messages = $this->buildRelatorioPrompt($dadosFormulario, $snapshot, $gateResult, $dadosSerasa);
            $parsed = $this->callOpenAI($messages, 'relatorio_final');

            return [
                'success' => true,
                'relatorio' => $parsed,
                'model_used' => $parsed['_meta']['model_used'] ?? $this->model,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('SIRIC OpenAI Relatório Final falhou: ' . $e->getMessage());
            return [
                'success' => false,
                'relatorio' => null,
                'model_used' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Monta prompt para Relatório Final (v2 — com citação obrigatória).
     */
    private function buildRelatorioPrompt(array $form, array $snapshot, array $gateResult, ?array $dadosSerasa): array
    {
        $contasReceber = $snapshot['contas_receber'] ?? [];
        $movimentos = $snapshot['movimentos'] ?? [];
        $processos = $snapshot['processos'] ?? [];
        $leads = $snapshot['leads'] ?? [];
        $clienteInfo = $snapshot['cliente'] ?? [];
        $conversasWa = $snapshot['conversas_whatsapp'] ?? null;
        $relacao = $snapshot['relacao_escritorio'] ?? [];

        $valorPretendido = (float) ($form['valor_total'] ?? 0);
        $numParcelas = (int) ($form['parcelas_desejadas'] ?? 1);
        $parcelaEstimada = $numParcelas > 0 ? round($valorPretendido / $numParcelas, 2) : $valorPretendido;

        $systemPrompt = "Você é o Analista SIRIC do escritório Mayer Advogados.\n\n"
            . "OBJETIVO: Gerar relatório completo de análise de crédito para parcelamento de honorários advocatícios.\n\n"
            . "REGRAS INVIOLÁVEIS:\n"
            . "- Decisão de crédito é SEMPRE humana; você gera análise e RECOMENDAÇÃO.\n"
            . "- Use APENAS dados fornecidos. NÃO invente, suponha ou extrapole dados.\n"
            . "- NÃO use atributos sensíveis (raça/religião/política/saúde).\n"
            . "- Score de 0 a 1000. Rating de A (melhor) a E (pior).\n"
            . "- Responda EXCLUSIVAMENTE em JSON válido, sem markdown.\n\n"

            . "REGRA DE CITAÇÃO OBRIGATÓRIA (v2):\n"
            . "- CADA fator positivo/negativo DEVE citar o DADO ESPECÍFICO que o fundamenta.\n"
            . "- Formato obrigatório: \"[DADO: campo=valor] Descrição do fator\"\n"
            . "- Exemplos corretos:\n"
            . "  - \"[DADO: qtd_atrasos=3, max_dias_atraso=45] Histórico de atrasos recorrentes\"\n"
            . "  - \"[DADO: total_pago=R$16.827, recorrencia=101 meses] Relacionamento longo e consistente\"\n"
            . "  - \"[DADO: serasa_score=350, protestos=5] Score Serasa muito baixo com protestos ativos\"\n"
            . "- Se não houver dado específico para citar, NÃO liste o fator.\n"
            . "- Na analise_detalhada, cite os números que sustentam cada parágrafo.\n\n"

            . "CRITÉRIOS DE SCORE:\n"
            . "- A (800-1000): Excelente histórico, baixo risco, aprovação recomendada\n"
            . "- B (600-799): Bom histórico, risco moderado-baixo, aprovação com monitoramento\n"
            . "- C (400-599): Histórico misto, risco moderado, aprovação condicional sugerida\n"
            . "- D (200-399): Histórico problemático, risco alto, condições restritivas ou negação\n"
            . "- E (0-199): Sem dados ou histórico muito negativo, negação recomendada\n\n"

            . "FATORES DE ANÁLISE (em ordem de peso):\n"
            . "1. Histórico de pagamentos (30%): pontualidade, atrasos, inadimplência — cite qtd_atrasos, max_dias_atraso, total_pago\n"
            . "2. Capacidade de pagamento (25%): renda vs compromisso — cite renda, parcela_estimada, comprometimento_%\n"
            . "3. Dados Serasa/bureau (20% se disponível, redistribua se não): score, pendências, protestos — cite valores exatos\n"
            . "4. Relacionamento com escritório (15%): tempo, frequência, volume — cite recorrencia_meses, total_registros\n"
            . "5. Completude de dados cadastrais (5%): cite campos faltantes\n"
            . "6. Processos ativos (3%): exposição, complexidade — cite total_ativos\n"
            . "7. Conversas WhatsApp (2% se disponível): tom, comprometimento\n\n"

            . "FORMATO DE RESPOSTA (JSON puro):\n"
            . "{\n"
            . "  \"score_final\": <0-1000>,\n"
            . "  \"rating\": \"<A|B|C|D|E>\",\n"
            . "  \"resumo_executivo\": \"<texto conciso 2-3 frases>\",\n"
            . "  \"recomendacao\": \"<aprovado|negado|condicional>\",\n"
            . "  \"comprometimento_max_sugerido\": \"<percentual ou valor>\",\n"
            . "  \"parcelas_max_sugeridas\": <número>,\n"
            . "  \"fatores_positivos\": [\"[DADO: campo=valor] descrição\", ...],\n"
            . "  \"fatores_negativos\": [\"[DADO: campo=valor] descrição\", ...],\n"
            . "  \"analise_detalhada\": {\n"
            . "    \"historico_pagamentos\": \"<análise com dados citados>\",\n"
            . "    \"capacidade_pagamento\": \"<análise com dados citados>\",\n"
            . "    \"riscos_identificados\": \"<riscos com dados citados>\",\n"
            . "    \"pontos_positivos\": \"<fatores favoráveis com dados citados>\",\n"
            . "    \"relacionamento_escritorio\": \"<análise com dados citados>\"\n"
            . "  },\n"
            . "  \"alertas\": [\"<alerta1>\", \"<alerta2>\"]\n"
            . "}";

        $dadosCompletos = [
            'formulario' => [
                'cpf_cnpj' => $form['cpf_cnpj'] ?? '',
                'nome' => $form['nome'] ?? '',
                'valor_pretendido' => $valorPretendido,
                'num_parcelas' => $numParcelas,
                'parcela_estimada' => $parcelaEstimada,
                'renda_declarada' => $form['renda_declarada'] ?? null,
                'observacoes' => $form['observacoes'] ?? '',
            ],
            'dados_internos' => [
                'cliente' => $clienteInfo,
                'relacao_escritorio' => $relacao,
                'contas_receber' => $contasReceber,
                'movimentos' => $movimentos,
                'processos' => $processos,
                'leads' => $leads,
            ],
            'gate_decision_deterministico' => [
                'gate_score' => $gateResult['gate_score'] ?? null,
                'gate_score_breakdown' => $gateResult['gate_score_breakdown'] ?? [],
                'riscos_preliminares' => $gateResult['riscos_preliminares'] ?? [],
                'dados_faltantes' => $gateResult['dados_faltantes'] ?? [],
                'comprometimento_pct' => $gateResult['comprometimento_pct'] ?? null,
                'bom_historico' => $gateResult['bom_historico'] ?? false,
            ],
        ];

        // Dados Serasa ESTRUTURADOS (v2 — sem duplicação)
        if ($dadosSerasa) {
            $dadosCompletos['dados_serasa'] = $dadosSerasa;
        }

        if ($conversasWa && !empty($conversasWa['mensagens'])) {
            $dadosCompletos['conversas_whatsapp'] = [
                'total_conversas' => $conversasWa['total_conversas'] ?? 0,
                'total_mensagens' => $conversasWa['total_mensagens'] ?? 0,
                'periodo' => $conversasWa['periodo'] ?? 'N/A',
                'resumo_interacoes' => $conversasWa['resumo'] ?? '',
                'ultimas_mensagens' => array_slice($conversasWa['mensagens'] ?? [], -30),
            ];
        }

        $userContent = json_encode($dadosCompletos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Gere o relatório de análise de crédito com base nos seguintes dados:\n\n" . $userContent],
        ];
    }

    /**
     * Chama a API OpenAI com fallback de modelo.
     */
    private function callOpenAI(array $messages, string $etapa): array
    {
        $apiKey = config('services.siric.openai_api_key', env('SIRIC_OPENAI_API_KEY', env('OPENAI_API_KEY')));

        if (empty($apiKey)) {
            throw new \RuntimeException('OPENAI_API_KEY não configurada.');
        }

        // Tentativa com modelo principal
        try {
            $response = $this->doRequest($apiKey, $this->model, $messages);
            return $this->parseResponse($response, $etapa, $this->model);
        } catch (\Throwable $e) {
            Log::warning("SIRIC IA [{$etapa}] falhou com modelo {$this->model}: {$e->getMessage()}");
        }

        // Fallback
        if ($this->fallbackModel !== $this->model) {
            try {
                $response = $this->doRequest($apiKey, $this->fallbackModel, $messages);
                return $this->parseResponse($response, $etapa, $this->fallbackModel);
            } catch (\Throwable $e) {
                Log::error("SIRIC IA [{$etapa}] falhou no fallback {$this->fallbackModel}: {$e->getMessage()}");
                throw new \RuntimeException("Análise IA falhou em ambos os modelos. Etapa: {$etapa}");
            }
        }

        throw new \RuntimeException("Análise IA falhou. Etapa: {$etapa}");
    }

    /**
     * Executa request HTTP para OpenAI (v2 — compatível com modelos reasoning).
     */
    private function doRequest(string $apiKey, string $model, array $messages): array
    {
        $isReasoning = in_array($model, ['o3', 'o4-mini', 'o3-mini', 'o1', 'o1-mini']);

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_completion_tokens' => $this->maxTokens,
            'response_format' => ['type' => 'json_object'],
        ];

        // Modelos reasoning não suportam temperature
        if (!$isReasoning) {
            $payload['temperature'] = $this->temperature;
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])
        ->timeout(180) // v2: mais tempo para modelos reasoning
        ->post('https://api.openai.com/v1/chat/completions', $payload);

        if (!$response->successful()) {
            $error = $response->json('error.message', $response->body());
            throw new \RuntimeException("OpenAI API error ({$response->status()}): {$error}");
        }

        return $response->json();
    }

    /**
     * Parseia resposta da OpenAI e valida JSON.
     */
    private function parseResponse(array $response, string $etapa, string $modelUsed): array
    {
        $content = $response['choices'][0]['message']['content'] ?? '';
        $usage = $response['usage'] ?? [];

        // Limpa possíveis wrappers markdown
        $content = preg_replace('/^```json\s*/i', '', trim($content));
        $content = preg_replace('/\s*```$/i', '', $content);

        $parsed = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("SIRIC IA [{$etapa}] JSON inválido", [
                'content' => substr($content, 0, 500),
                'json_error' => json_last_error_msg(),
            ]);
            throw new \RuntimeException("Resposta da IA não é JSON válido na etapa {$etapa}");
        }

        $parsed['_meta'] = [
            'model_used' => $modelUsed,
            'etapa' => $etapa,
            'tokens_prompt' => $usage['prompt_tokens'] ?? 0,
            'tokens_completion' => $usage['completion_tokens'] ?? 0,
            'tokens_total' => $usage['total_tokens'] ?? 0,
            'timestamp' => now()->toIso8601String(),
        ];

        Log::info("SIRIC IA [{$etapa}] concluída", [
            'model' => $modelUsed,
            'tokens' => $usage['total_tokens'] ?? 0,
        ]);

        return $parsed;
    }
}
