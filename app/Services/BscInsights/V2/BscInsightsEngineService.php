<?php

namespace App\Services\BscInsights\V2;

use App\Models\BscInsightCardV2;
use App\Models\BscInsightRun;
use App\Services\BscInsights\AiBudgetGuard;
use App\Services\BscInsights\BscInsightSnapshotBuilder;
use Illuminate\Support\Facades\Log;

class BscInsightsEngineService
{
    private BscSnapshotValidator $validator;
    private BscSnapshotNormalizer $normalizer;
    private BscDerivedMetricsService $derivedMetrics;
    private BscOpenAiClient $aiClient;
    private BscAiResponseValidator $responseValidator;

    public function __construct()
    {
        $this->validator         = new BscSnapshotValidator();
        $this->normalizer        = new BscSnapshotNormalizer();
        $this->derivedMetrics    = new BscDerivedMetricsService();
        $this->aiClient          = new BscOpenAiClient();
        $this->responseValidator = new BscAiResponseValidator();
    }

    public function execute(?int $userId = null, bool $force = false): BscInsightRun
    {
        $startMs = (int)(microtime(true) * 1000);

        $builder = new BscInsightSnapshotBuilder();
        $rawSnapshot = $builder->build();

        $normResult = $this->normalizer->normalize($rawSnapshot);
        $snapshot = $normResult['snapshot'];
        $hash = $normResult['hash'];

        if (!$force) {
            $cached = BscInsightRun::findCached($hash, config('bsc_insights.cache_hours', 12));
            if ($cached) {
                Log::info('BscEngine: cache hit', ['run_id' => $cached->id]);
                return $cached;
            }
        }

        $run = BscInsightRun::create([
            'snapshot_hash'       => $hash,
            'snapshot_json'       => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'periodo_inicio'      => $builder->getInicio(),
            'periodo_fim'         => $builder->getFim(),
            'status'              => 'running',
            'model_used'          => config('bsc_insights.openai_model'),
            'prompt_version'      => config('bsc_insights.prompt_version', '2.0'),
            'normalizer_log_json' => json_encode($normResult['log'], JSON_UNESCAPED_UNICODE),
            'force_requested'     => $force,
            'created_by_user_id'  => $userId,
        ]);

        try {
            $run->markValidating();
            $validation = $this->validator->validate($snapshot);
            $run->update(['validator_issues_json' => json_encode($validation['issues'], JSON_UNESCAPED_UNICODE)]);

            $derived = $this->derivedMetrics->calculate($snapshot);
            $run->update(['derived_metrics_json' => json_encode($derived, JSON_UNESCAPED_UNICODE)]);

            $run->markCallingAi();
            $systemPrompt = $this->buildSystemPrompt($validation);
            $userPrompt   = $this->buildUserPrompt($snapshot, $derived, $validation);
            $schema       = $this->getResponseSchema();

            $aiResult = $this->aiClient->call($systemPrompt, $userPrompt, $schema);

            $run->markProcessing();
            $respVal = $this->responseValidator->validate($aiResult['content'], array_keys($snapshot));

            if (!$respVal['valid']) {
                Log::warning('BscEngine: resposta invalida, tentando repair', ['errors' => array_slice($respVal['errors'], 0, 5)]);
                $repairPrompt = $this->responseValidator->buildRepairPrompt($aiResult['content'], $respVal['errors']);
                $repair = $this->aiClient->call($systemPrompt, $repairPrompt, $schema);
                $respVal = $this->responseValidator->validate($repair['content'], array_keys($snapshot));
                $aiResult['input_tokens']  += $repair['input_tokens'];
                $aiResult['output_tokens'] += $repair['output_tokens'];
                $aiResult['cost_usd']      += $repair['cost_usd'];
                $aiResult['duration_ms']   += $repair['duration_ms'];

                if (!$respVal['valid']) {
                    $run->markFailed('Invalido apos repair: ' . implode('; ', array_slice($respVal['errors'], 0, 5)), [
                        'input_tokens' => $aiResult['input_tokens'], 'output_tokens' => $aiResult['output_tokens'],
                        'cost_usd_estimated' => $aiResult['cost_usd'], 'duration_ms' => $aiResult['duration_ms'],
                    ]);
                    $this->recordBudget($aiResult['cost_usd']);
                    return $run->fresh();
                }
            }

            $this->persistCards($respVal['cards'], $run);

            $run->markSuccess([
                'input_tokens' => $aiResult['input_tokens'], 'output_tokens' => $aiResult['output_tokens'],
                'cost_usd_estimated' => $aiResult['cost_usd'], 'duration_ms' => (int)(microtime(true) * 1000) - $startMs,
            ]);
            $this->recordBudget($aiResult['cost_usd']);

            return $run->fresh();

        } catch (\Throwable $e) {
            $run->markFailed($e->getMessage(), ['duration_ms' => (int)(microtime(true) * 1000) - $startMs]);
            Log::error('BscEngine: erro', ['run_id' => $run->id, 'error' => $e->getMessage()]);
            return $run->fresh();
        }
    }

    /**
     * Executa pipeline usando um BscInsightRun já criado (chamado pelo Job).
     */
    public function executeFromRun(BscInsightRun $run, ?int $userId = null, bool $force = false): BscInsightRun
    {
        $startMs = (int)(microtime(true) * 1000);

        try {
            $snapshot = json_decode($run->snapshot_json, true) ?? [];

            $run->markValidating();
            $validation = $this->validator->validate($snapshot);
            $run->update(['validator_issues_json' => json_encode($validation['issues'], JSON_UNESCAPED_UNICODE)]);

            $derived = $this->derivedMetrics->calculate($snapshot);
            $run->update(['derived_metrics_json' => json_encode($derived, JSON_UNESCAPED_UNICODE)]);

            $run->markCallingAi();
            $systemPrompt = $this->buildSystemPrompt($validation);
            $userPrompt   = $this->buildUserPrompt($snapshot, $derived, $validation);
            $schema       = $this->getResponseSchema();

            $aiResult = $this->aiClient->call($systemPrompt, $userPrompt, $schema);

            $run->markProcessing();
            $respVal = $this->responseValidator->validate($aiResult['content'], array_keys($snapshot));

            if (!$respVal['valid']) {
                $repairPrompt = $this->responseValidator->buildRepairPrompt($aiResult['content'], $respVal['errors']);
                $repair = $this->aiClient->call($systemPrompt, $repairPrompt, $schema);
                $respVal = $this->responseValidator->validate($repair['content'], array_keys($snapshot));
                $aiResult['input_tokens']  += $repair['input_tokens'];
                $aiResult['output_tokens'] += $repair['output_tokens'];
                $aiResult['cost_usd']      += $repair['cost_usd'];
                $aiResult['duration_ms']   += $repair['duration_ms'];

                if (!$respVal['valid']) {
                    $run->markFailed('Invalido apos repair: ' . implode('; ', array_slice($respVal['errors'], 0, 5)), [
                        'input_tokens' => $aiResult['input_tokens'], 'output_tokens' => $aiResult['output_tokens'],
                        'cost_usd_estimated' => $aiResult['cost_usd'], 'duration_ms' => $aiResult['duration_ms'],
                    ]);
                    $this->recordBudget($aiResult['cost_usd']);
                    return $run->fresh();
                }
            }

            $this->persistCards($respVal['cards'], $run);

            $run->markSuccess([
                'input_tokens' => $aiResult['input_tokens'], 'output_tokens' => $aiResult['output_tokens'],
                'cost_usd_estimated' => $aiResult['cost_usd'], 'duration_ms' => (int)(microtime(true) * 1000) - $startMs,
            ]);
            $this->recordBudget($aiResult['cost_usd']);

            return $run->fresh();

        } catch (\Throwable $e) {
            $run->markFailed($e->getMessage(), ['duration_ms' => (int)(microtime(true) * 1000) - $startMs]);
            throw $e;
        }
    }

        private function buildSystemPrompt(array $validation): string
    {
        $qualityWarn = '';
        if ($validation['critical_count'] > 0) {
            $qualityWarn = "\n\nALERTA: Snapshot com {$validation['critical_count']} inconsistencias criticas. "
                . "Para blocos afetados, gere cards de GOVERNANCA (severidade 'atencao') recomendando correcao.";
        }

        return "Voce e auditor de dados BSC de um escritorio de advocacia brasileiro.\n"
            . "Sua UNICA fonte e o snapshot JSON fornecido. NAO invente dados.\n\n"
            . "REGRAS:\n"
            . "1. Produza 12-24 cards em 4 perspectivas: financeiro, clientes, processos, times (3-6 cada).\n"
            . "2. Cada card DEVE citar pelo menos 2 evidencias numericas com chaves exatas em metricas_referenciadas.\n"
            . "3. Se nao houver dado, NAO crie card. Crie card de governanca explicando a lacuna.\n"
            . "4. NAO invente causas. Hipoteses devem ser marcadas como tal.\n"
            . "5. Severidade: critico (acao imediata), atencao (monitorar), info (positivo/neutro).\n"
            . "6. Confidence 0-100. Impact_score 0.0-10.0.\n"
            . "7. Responda SOMENTE JSON no schema definido. Portugues brasileiro.\n"
            . "8. Titulo max 200 chars. Descricao e recomendacao obrigatorios.\n"
            . "9. acao_sugerida: frase curta e direta (max 200 chars).\n"
            . "10. Priorize: riscos criticos > oportunidades > governanca.\n"
            . "11. Em metricas_referenciadas use paths do snapshot: finance.receita_total_mensal, inadimplencia.total_vencido etc.\n"
            . "12. Em evidencias use: [{\"metrica\":\"...\",\"valor\":\"...\",\"variacao\":\"...\"}]"
            . $qualityWarn;
    }

    private function buildUserPrompt(array $snapshot, array $derived, array $validation): string
    {
        $payload = [
            'snapshot' => $snapshot,
            'metricas_derivadas' => $derived,
            'qualidade_dados' => [
                'blocos_disponiveis' => $derived['data_quality']['available_blocks'] ?? 0,
                'blocos_total'       => $derived['data_quality']['total_blocks'] ?? 0,
                'integridade_pct'    => $derived['data_quality']['integrity_pct'] ?? 0,
                'indisponiveis'      => $derived['data_quality']['unavailable'] ?? [],
                'issues_criticos'    => $validation['critical_count'] ?? 0,
                'issues_warning'     => $validation['warning_count'] ?? 0,
            ],
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

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
                            'perspectiva'            => ['type' => 'string', 'enum' => ['financeiro','clientes','processos','times']],
                            'severidade'             => ['type' => 'string', 'enum' => ['info','atencao','critico']],
                            'titulo'                 => ['type' => 'string'],
                            'descricao'              => ['type' => 'string'],
                            'recomendacao'           => ['type' => 'string'],
                            'acao_sugerida'          => ['type' => ['string','null']],
                            'metricas_referenciadas' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'evidencias'             => ['type' => 'array', 'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'metrica'  => ['type' => 'string'],
                                    'valor'    => ['type' => 'string'],
                                    'variacao' => ['type' => ['string','null']],
                                ],
                                'required' => ['metrica', 'valor'],
                                'additionalProperties' => false,
                            ]],
                            'confidence'   => ['type' => 'integer'],
                            'impact_score' => ['type' => 'number'],
                        ],
                        'required' => ['perspectiva','severidade','titulo','descricao','recomendacao','metricas_referenciadas','evidencias','confidence','impact_score'],
                        'additionalProperties' => false,
                    ],
                ],
                'meta' => [
                    'type' => 'object',
                    'properties' => [
                        'principais_riscos'        => ['type' => 'array', 'items' => ['type' => 'string']],
                        'principais_oportunidades' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'apostas_recomendadas'     => ['type' => 'array', 'items' => [
                            'type' => 'object',
                            'properties' => [
                                'descricao'        => ['type' => 'string'],
                                'impacto_esperado' => ['type' => 'string'],
                                'esforco'          => ['type' => 'string'],
                            ],
                            'required' => ['descricao','impacto_esperado','esforco'],
                            'additionalProperties' => false,
                        ]],
                    ],
                    'required' => ['principais_riscos','principais_oportunidades','apostas_recomendadas'],
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['cards', 'meta'],
            'additionalProperties' => false,
        ];
    }

    private function persistCards(array $cards, BscInsightRun $run): array
    {
        $created = [];
        foreach ($cards as $idx => $card) {
            $created[] = BscInsightCardV2::create([
                'run_id'                      => $run->id,
                'perspectiva'                 => $card['perspectiva'],
                'severidade'                  => $card['severidade'],
                'titulo'                      => mb_substr($card['titulo'], 0, 200),
                'descricao'                   => $card['descricao'],
                'recomendacao'                => $card['recomendacao'],
                'acao_sugerida'               => mb_substr($card['acao_sugerida'] ?? '', 0, 200) ?: null,
                'metricas_referenciadas_json'  => json_encode($card['metricas_referenciadas'] ?? [], JSON_UNESCAPED_UNICODE),
                'evidencias_json'             => json_encode($card['evidencias'] ?? [], JSON_UNESCAPED_UNICODE),
                'confidence'                  => max(0, min(100, $card['confidence'] ?? 50)),
                'impact_score'                => max(0, min(10, $card['impact_score'] ?? 0)),
                'ordem'                       => $idx,
            ]);
        }
        return $created;
    }

    private function recordBudget(float $cost): void
    {
        try {
            $guard = new AiBudgetGuard();
            $guard->recordSpend($cost);
        } catch (\Throwable $e) {
            Log::warning('BscEngine: falha ao registrar budget', ['error' => $e->getMessage()]);
        }
    }
}
