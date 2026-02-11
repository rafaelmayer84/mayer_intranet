<?php

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
        $this->model = config('services.siric.openai_model', 'gpt-5.2');
        $this->fallbackModel = config('services.siric.openai_fallback', 'gpt-5');
        $this->maxTokens = (int) config('services.siric.openai_max_tokens', 4000);
        $this->temperature = (float) config('services.siric.openai_temperature', 0.3);
    }

    /**
     * Etapa 1: Gate Decision
     *
     * @param array $dadosFormulario  Dados do formulário da consulta
     * @param array $snapshot         Snapshot interno coletado do BD
     * @return array ['success' => bool, 'data' => array|null, 'error' => string|null]
     */
    public function executarGateDecision(array $dadosFormulario, array $snapshot): array
    {
        try {
            $messages = $this->buildGatePrompt($dadosFormulario, $snapshot);
            $parsed = $this->callOpenAI($messages, 'gate_decision');

            return [
                'success' => true,
                'data' => $parsed,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('SIRIC OpenAI Gate Decision falhou: ' . $e->getMessage());
            return [
                'success' => false,
                'data' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Etapa 2: Relatório Final
     *
     * @param array      $dadosFormulario Dados do formulário
     * @param array      $snapshot        Snapshot interno
     * @param array      $gateResult      Resultado do gate decision
     * @param array|null $dadosSerasa     Dados do Serasa (se consultado)
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
     * Monta prompt para Gate Decision (Etapa 1).
     */
    private function buildGatePrompt(array $form, array $snapshot): array
    {
        $metricas = $snapshot['metricas'] ?? [];
        $contasReceber = $snapshot['contas_receber'] ?? [];
        $movimentos = $snapshot['movimentos'] ?? [];
        $processos = $snapshot['processos'] ?? [];
        $leads = $snapshot['leads'] ?? [];
        $clienteInfo = $snapshot['cliente'] ?? [];
        $conversasWa = $snapshot['conversas_whatsapp'] ?? null;

        $valorPretendido = (float) ($form['valor_total'] ?? 0);
        $numParcelas = (int) ($form['parcelas_desejadas'] ?? 1);
        $parcelaEstimada = $numParcelas > 0 ? round($valorPretendido / $numParcelas, 2) : $valorPretendido;

        $systemPrompt = "Você é o Analista SIRIC do escritório Mayer Advogados.\n\n"
            . "OBJETIVO: Decidir se vale consultar Serasa (custo R\$17,00) e/ou fazer web_intel, antes de gerar o relatório de crédito.\n\n"
            . "REGRAS INVIOLÁVEIS:\n"
            . "- Decisão de crédito é SEMPRE humana; você só recomenda e justifica.\n"
            . "- Use APENAS dados fornecidos (formulário + interno + resultados de tools).\n"
            . "- NÃO use atributos sensíveis (raça/religião/política/saúde) nem inferências disso.\n"
            . "- Responda EXCLUSIVAMENTE em JSON válido, sem markdown.\n\n"
            . "CÁLCULO DO gate_score (0-100+):\n\n"
            . "HISTÓRICO:\n"
            . "+35 se sem histórico interno (cliente não encontrado no BD)\n"
            . "+40 se inadimplência atual (saldo vencido > 0)\n"
            . "+25 se max_dias_atraso >= 15\n"
            . "+25 se quebrou acordo anterior (parcelas canceladas existentes)\n\n"
            . "BOM HISTÓRICO (desconto):\n"
            . "-30 se relacionamento >= 12 meses E >= 6 parcelas pagas sem atraso E saldo_vencido = 0\n\n"
            . "RENDA:\n"
            . "+25 se renda ausente/não declarada\n"
            . "+15 se (parcela_estimada / renda) > 0.20\n"
            . "+30 se (parcela_estimada / renda) > 0.30\n"
            . "+25 se (renda - despesas_estimadas) < parcela_estimada\n\n"
            . "COMPLETUDE DE DADOS:\n"
            . "Campos-chave: telefone, email, endereço completo, profissão, empregador, tempo_emprego\n"
            . "+15 se faltam >= 2 campos-chave\n"
            . "+30 se faltam >= 4 campos-chave\n\n"
            . "CONSISTÊNCIA:\n"
            . "+25 se telefone/email divergem dos dados internos para o mesmo CPF/CNPJ\n\n"
            . "EXPOSIÇÃO (valor_pretendido):\n"
            . "+15 se valor_pretendido >= R\$800\n"
            . "+25 se valor_pretendido >= R\$2.000\n"
            . "-10 se valor_pretendido < R\$400\n\n"
            . "DECISÃO:\n"
            . "- Se autorizacao_consulta_externa = false OU cpf_cnpj inválido => need_serasa = false\n"
            . "- Se gate_score >= 30 => need_serasa = true\n"
            . "- Se 15 <= gate_score < 30 => need_web_intel = true primeiro; após web, se inconsistência factual => gate_score += 20 e redecide\n"
            . "- Se gate_score < 15 => need_serasa = false, need_web_intel = false\n\n"
            . "FORMATO DE RESPOSTA (JSON puro):\n"
            . "{\n"
            . "  \"gate_score\": <número>,\n"
            . "  \"gate_score_breakdown\": {\n"
            . "    \"historico\": <número>,\n"
            . "    \"bom_historico_desconto\": <número>,\n"
            . "    \"renda\": <número>,\n"
            . "    \"completude\": <número>,\n"
            . "    \"consistencia\": <número>,\n"
            . "    \"exposicao\": <número>\n"
            . "  },\n"
            . "  \"need_serasa\": <boolean>,\n"
            . "  \"need_web_intel\": <boolean>,\n"
            . "  \"justificativa\": \"<texto curto>\",\n"
            . "  \"riscos_preliminares\": [\"<risco1>\", \"<risco2>\"],\n"
            . "  \"dados_faltantes\": [\"<campo1>\", \"<campo2>\"]\n"
            . "}";

        $userContent = json_encode([
            'formulario' => [
                'cpf_cnpj' => $form['cpf_cnpj'] ?? '',
                'nome' => $form['nome'] ?? '',
                'valor_pretendido' => $valorPretendido,
                'num_parcelas' => $numParcelas,
                'parcela_estimada' => $parcelaEstimada,
                'renda_declarada' => $form['renda_declarada'] ?? null,
                'observacoes' => $form['observacoes'] ?? '',
                'autorizacao_consulta_externa' => (bool) ($form['autorizou_consultas_externas'] ?? false),
            ],
            'dados_internos' => [
                'cliente_encontrado' => !empty($clienteInfo),
                'cliente_info' => $clienteInfo,
                'metricas_financeiras' => $metricas,
                'contas_receber' => [
                    'total_registros' => $contasReceber['total_registros'] ?? 0,
                    'total_pago' => $contasReceber['total_pago'] ?? 0,
                    'saldo_aberto' => $contasReceber['saldo_aberto'] ?? 0,
                    'saldo_vencido' => $contasReceber['saldo_vencido'] ?? 0,
                    'qtd_atrasos' => $contasReceber['qtd_atrasos'] ?? 0,
                    'max_dias_atraso' => $contasReceber['max_dias_atraso'] ?? 0,
                    'media_dias_atraso' => $contasReceber['media_dias_atraso'] ?? 0,
                ],
                'movimentos' => [
                    'total' => $movimentos['total'] ?? 0,
                    'total_creditos' => $movimentos['total_creditos'] ?? 0,
                    'total_debitos' => $movimentos['total_debitos'] ?? 0,
                ],
                'processos' => [
                    'total_ativos' => $processos['total_ativos'] ?? 0,
                    'total_inativos' => $processos['total_inativos'] ?? 0,
                ],
                'leads' => $leads,
            ],
            'conversas_whatsapp' => $conversasWa ? [
                'total_conversas' => $conversasWa['total_conversas'] ?? 0,
                'resumo' => $conversasWa['resumo'] ?? 'Nenhuma conversa encontrada',
            ] : null,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Analise os seguintes dados e calcule o gate_score:\n\n" . $userContent],
        ];
    }

    /**
     * Monta prompt para Relatório Final (Etapa 2).
     */
    private function buildRelatorioPrompt(array $form, array $snapshot, array $gateResult, ?array $dadosSerasa): array
    {
        $metricas = $snapshot['metricas'] ?? [];
        $contasReceber = $snapshot['contas_receber'] ?? [];
        $movimentos = $snapshot['movimentos'] ?? [];
        $processos = $snapshot['processos'] ?? [];
        $leads = $snapshot['leads'] ?? [];
        $clienteInfo = $snapshot['cliente'] ?? [];
        $conversasWa = $snapshot['conversas_whatsapp'] ?? null;

        $valorPretendido = (float) ($form['valor_total'] ?? 0);
        $numParcelas = (int) ($form['parcelas_desejadas'] ?? 1);
        $parcelaEstimada = $numParcelas > 0 ? round($valorPretendido / $numParcelas, 2) : $valorPretendido;

        $systemPrompt = "Você é o Analista SIRIC do escritório Mayer Advogados.\n\n"
            . "OBJETIVO: Gerar relatório completo de análise de crédito para parcelamento de honorários advocatícios.\n\n"
            . "REGRAS INVIOLÁVEIS:\n"
            . "- Decisão de crédito é SEMPRE humana; você gera análise e RECOMENDAÇÃO.\n"
            . "- Use APENAS dados fornecidos. Não invente dados.\n"
            . "- NÃO use atributos sensíveis (raça/religião/política/saúde).\n"
            . "- Score de 0 a 1000. Rating de A (melhor) a E (pior).\n"
            . "- Responda EXCLUSIVAMENTE em JSON válido, sem markdown.\n\n"
            . "CRITÉRIOS DE SCORE:\n"
            . "- A (800-1000): Excelente histórico, baixo risco, aprovação recomendada\n"
            . "- B (600-799): Bom histórico, risco moderado-baixo, aprovação com monitoramento\n"
            . "- C (400-599): Histórico misto, risco moderado, aprovação condicional sugerida\n"
            . "- D (200-399): Histórico problemático, risco alto, condições restritivas ou negação\n"
            . "- E (0-199): Sem dados ou histórico muito negativo, negação recomendada\n\n"
            . "FATORES DE ANÁLISE:\n"
            . "1. Histórico de pagamentos (pontualidade, atrasos, inadimplência)\n"
            . "2. Capacidade de pagamento (renda vs compromisso, comprometimento mensal)\n"
            . "3. Relacionamento com escritório (tempo, frequência, volume)\n"
            . "4. Processos ativos (exposição, complexidade)\n"
            . "5. Completude de dados cadastrais\n"
            . "6. Dados Serasa/bureau (se disponíveis)\n"
            . "7. Conversas WhatsApp (tom, comprometimento, histórico de comunicação)\n\n"
            . "FORMATO DE RESPOSTA (JSON puro):\n"
            . "{\n"
            . "  \"score_final\": <0-1000>,\n"
            . "  \"rating\": \"<A|B|C|D|E>\",\n"
            . "  \"resumo_executivo\": \"<texto conciso 2-3 frases>\",\n"
            . "  \"recomendacao\": \"<aprovado|negado|condicional>\",\n"
            . "  \"comprometimento_max_sugerido\": \"<percentual ou valor>\",\n"
            . "  \"parcelas_max_sugeridas\": <número>,\n"
            . "  \"fatores_positivos\": [\"<fator1>\", \"<fator2>\"],\n"
            . "  \"fatores_negativos\": [\"<fator1>\", \"<fator2>\"],\n"
            . "  \"analise_detalhada\": {\n"
            . "    \"historico_pagamentos\": \"<análise>\",\n"
            . "    \"capacidade_pagamento\": \"<análise>\",\n"
            . "    \"riscos_identificados\": \"<riscos>\",\n"
            . "    \"pontos_positivos\": \"<fatores favoráveis>\",\n"
            . "    \"relacionamento_escritorio\": \"<análise>\"\n"
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
                'metricas' => $metricas,
                'contas_receber' => $contasReceber,
                'movimentos' => $movimentos,
                'processos' => $processos,
                'leads' => $leads,
            ],
            'gate_decision' => [
                'gate_score' => $gateResult['gate_score'] ?? null,
                'riscos_preliminares' => $gateResult['riscos_preliminares'] ?? [],
                'dados_faltantes' => $gateResult['dados_faltantes'] ?? [],
            ],
        ];

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
     * Chama a API OpenAI com fallback.
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
     * Executa request HTTP para OpenAI.
     */
    private function doRequest(string $apiKey, string $model, array $messages): array
    {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_completion_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'response_format' => ['type' => 'json_object'],
        ];

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])
        ->timeout(120)
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
            'resposta_completa' => $parsed,
        ]);

        return $parsed;
    }
}
