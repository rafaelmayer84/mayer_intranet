<?php

namespace App\Services\BscInsights;

use App\Models\AiRun;
use App\Models\BscInsightCard;
use App\Models\BscInsightSnapshot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIInsightService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('bsc_insights.openai_api_key');
        $this->model  = config('bsc_insights.openai_model', 'gpt-5-mini');
    }

    /**
     * Gera insights a partir do snapshot via OpenAI Structured Output.
     *
     * @return array{run: AiRun, cards: array}
     */
    public function generate(BscInsightSnapshot $snapshot, ?int $userId = null): array
    {
        $run = AiRun::create([
            'feature'            => 'bsc_insights',
            'snapshot_id'        => $snapshot->id,
            'model'              => $this->model,
            'status'             => 'pending',
            'created_by_user_id' => $userId,
        ]);

        try {
            $payload = json_decode($snapshot->json_payload, true);
            $systemPrompt = $this->buildSystemPrompt();
            $userPrompt   = $this->buildUserPrompt($payload);

            $response = Http::timeout(180)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'    => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name'   => 'bsc_insights_response',
                            'strict' => true,
                            'schema' => $this->getResponseSchema(),
                        ],
                    ],
                    'max_completion_tokens' => 25000,
                    
                ]);

            if (!$response->successful()) {
                throw new \RuntimeException('OpenAI HTTP ' . $response->status() . ': ' . $response->body());
            }

            $data = $response->json();
            $usage = $data['usage'] ?? [];

            $inputTokens  = $usage['prompt_tokens'] ?? 0;
            $outputTokens = $usage['completion_tokens'] ?? 0;
            $totalTokens  = $usage['total_tokens'] ?? 0;
            $cost = AiBudgetGuard::estimateCost($inputTokens, $outputTokens);

            // Parse structured output
            $content = $data['choices'][0]['message']['content'] ?? '{}';
            Log::info('BscInsights RAW response', ['content' => substr($content, 0, 2000), 'usage' => $usage]);
            $parsed  = json_decode($content, true);

            if (!$parsed || !isset($parsed['cards'])) {
                throw new \RuntimeException('Resposta OpenAI sem campo "cards": ' . substr($content, 0, 500) . ' | json_error: ' . json_last_error_msg());
            }

            // Persistir cards
            $cards = $this->persistCards($parsed['cards'], $run, $snapshot);

            // Atualizar run com sucesso
            $run->update([
                'input_tokens'       => $inputTokens,
                'output_tokens'      => $outputTokens,
                'total_tokens'       => $totalTokens,
                'estimated_cost_usd' => $cost,
                'status'             => 'success',
            ]);

            // Registrar gasto no budget
            $guard = new AiBudgetGuard();
            $guard->recordSpend($cost);

            Log::info('BscInsights: geração concluída', [
                'run_id'  => $run->id,
                'cards'   => count($cards),
                'tokens'  => $totalTokens,
                'cost'    => $cost,
            ]);

            return ['run' => $run->fresh(), 'cards' => $cards, 'summary' => $parsed['summary'] ?? null];

        } catch (\Throwable $e) {
            $run->update([
                'status'        => 'error',
                'error_message' => substr($e->getMessage(), 0, 1000),
            ]);

            Log::error('BscInsights: erro na geração', [
                'run_id' => $run->id,
                'error'  => $e->getMessage(),
            ]);

            return ['run' => $run->fresh(), 'cards' => [], 'summary' => null];
        }
    }

    // ========================================================================
    // PROMPTS
    // ========================================================================

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
Você é um conselheiro de gestão especializado em Balanced Scorecard (BSC) para escritórios de advocacia brasileiros.

Sua tarefa: analisar o snapshot de dados e produzir cards de insight curtos e acionáveis.

REGRAS OBRIGATÓRIAS:
1. Produza entre 12 e 32 cards no total, distribuídos nos 4 universos BSC.
2. Cada universo deve ter entre 3 e 8 cards.
3. NÃO invente números. Use APENAS dados presentes no snapshot.
4. Cite a chave do snapshot de onde saiu cada evidência (ex: "finance.receita_total_mensal.2026-01").
5. Cards devem ser curtos e diretos — voltados para decisão executiva.
6. Severidade: CRITICO (requer ação imediata), ATENCAO (monitorar de perto), INFO (informativo positivo ou neutro).
7. Confidence: 0-100 baseado na robustez dos dados (dados completos = 80+, dados parciais = 40-70, estimativa = <40).
8. Impact_score: 0.00 a 10.00 — prioridade de ação.
9. Se faltar dado para um insight relevante, crie card do tipo "lacuna de dados" com severidade INFO explicando o que falta.
10. Recomendações devem ser 1 ação concreta. Próximo passo deve ser 1 tarefa com prazo sugerido.
11. Escreva em português brasileiro formal.

UNIVERSOS BSC:
- FINANCEIRO: receita, despesas, margem, inadimplência, mix PF/PJ
- CLIENTES_MERCADO: base de clientes, leads, conversão, CRM, oportunidades
- PROCESSOS_INTERNOS: processos jurídicos, SLA, backlog, throughput, eficiência
- TIMES_EVOLUCAO: horas trabalhadas, produtividade, GDP/performance, atendimento WhatsApp, tickets
PROMPT;
    }

    private function buildUserPrompt(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $periodo = ($payload['meta']['periodo_inicio'] ?? '?') . ' a ' . ($payload['meta']['periodo_fim'] ?? '?');

        return "PERÍODO DE ANÁLISE: {$periodo}\n\nSNAPSHOT DE DADOS:\n```json\n{$json}\n```\n\nGere os insights BSC conforme as regras do sistema.";
    }

    // ========================================================================
    // SCHEMA (Structured Output)
    // ========================================================================

    private function getResponseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'cards' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'universo'       => ['type' => 'string', 'enum' => ['FINANCEIRO', 'CLIENTES_MERCADO', 'PROCESSOS_INTERNOS', 'TIMES_EVOLUCAO']],
                            'severidade'     => ['type' => 'string', 'enum' => ['INFO', 'ATENCAO', 'CRITICO']],
                            'confidence'     => ['type' => 'integer'],
                            'title'          => ['type' => 'string'],
                            'what_changed'   => ['type' => 'string'],
                            'why_it_matters' => ['type' => 'string'],
                            'evidences' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'metric'     => ['type' => 'string'],
                                        'value'      => ['type' => 'string'],
                                        'variation'  => ['type' => 'string'],
                                        'source_key' => ['type' => 'string'],
                                    ],
                                    'required' => ['metric', 'value', 'variation', 'source_key'],
                                    'additionalProperties' => false,
                                ],
                            ],
                            'recommendation'  => ['type' => 'string'],
                            'next_step'       => ['type' => 'string'],
                            'questions'       => ['type' => 'array', 'items' => ['type' => 'string']],
                            'dependencies'    => ['type' => 'array', 'items' => ['type' => 'string']],
                            'evidence_keys'   => ['type' => 'array', 'items' => ['type' => 'string']],
                            'impact_score'    => ['type' => 'number'],
                        ],
                        'required' => ['universo', 'severidade', 'confidence', 'title', 'what_changed', 'why_it_matters', 'evidences', 'recommendation', 'next_step', 'questions', 'dependencies', 'evidence_keys', 'impact_score'],
                        'additionalProperties' => false,
                    ],
                ],
                'summary' => [
                    'type' => 'object',
                    'properties' => [
                        'principais_riscos'        => ['type' => 'array', 'items' => ['type' => 'string']],
                        'principais_oportunidades' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'apostas_recomendadas' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'descricao'         => ['type' => 'string'],
                                    'impacto_esperado'  => ['type' => 'string'],
                                    'esforco'           => ['type' => 'string'],
                                ],
                                'required' => ['descricao', 'impacto_esperado', 'esforco'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'required' => ['principais_riscos', 'principais_oportunidades', 'apostas_recomendadas'],
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['cards', 'summary'],
            'additionalProperties' => false,
        ];
    }

    // ========================================================================
    // PERSISTÊNCIA
    // ========================================================================

    private function persistCards(array $cards, AiRun $run, BscInsightSnapshot $snapshot): array
    {
        $persisted = [];

        foreach ($cards as $c) {
            $card = BscInsightCard::create([
                'run_id'              => $run->id,
                'snapshot_id'         => $snapshot->id,
                'universo'            => $c['universo'],
                'severidade'          => $c['severidade'],
                'confidence'          => min(100, max(0, $c['confidence'] ?? 50)),
                'title'               => mb_substr($c['title'] ?? '', 0, 100),
                'what_changed'        => mb_substr($c['what_changed'] ?? '', 0, 300),
                'why_it_matters'      => mb_substr($c['why_it_matters'] ?? '', 0, 300),
                'evidences_json'      => $c['evidences'] ?? [],
                'recommendation'      => mb_substr($c['recommendation'] ?? '', 0, 280),
                'next_step'           => mb_substr($c['next_step'] ?? '', 0, 280),
                'questions_json'      => $c['questions'] ?? [],
                'dependencies_json'   => $c['dependencies'] ?? [],
                'evidence_keys_json'  => $c['evidence_keys'] ?? [],
                'impact_score'        => min(10, max(0, $c['impact_score'] ?? 0)),
            ]);

            $persisted[] = $card;
        }

        return $persisted;
    }
}
