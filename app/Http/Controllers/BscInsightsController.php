<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateBscInsightsJob;
use App\Models\BscInsightCardV2;
use App\Models\BscInsightRun;
use App\Services\BscInsights\AiBudgetGuard;
use App\Services\BscInsights\BscInsightSnapshotBuilder;
use App\Services\BscInsights\DemoCardGenerator;
use App\Services\BscInsights\V2\BscInsightsEngineService;
use App\Services\BscInsights\V2\BscSnapshotNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BscInsightsController extends Controller
{
    public function index()
    {
        $isDemo = config('bsc_insights.demo_mode', false);
        $guard  = new AiBudgetGuard();
        $budgetInfo = $guard->getBudgetInfo();

        $lastRun = BscInsightRun::lastSuccessful();
        $cards = collect();
        $snapshotDate = now();

        if ($lastRun) {
            $cards = BscInsightCardV2::where('run_id', $lastRun->id)
                ->ordered()
                ->get()
                ->groupBy('perspectiva');
            $snapshotDate = $lastRun->created_at;
        }

        // Mapear perspectivas para universos da view
        $mappedCards = collect();
        $perspMap = [
            'financeiro' => 'FINANCEIRO',
            'clientes'   => 'CLIENTES_MERCADO',
            'processos'  => 'PROCESSOS_INTERNOS',
            'times'      => 'TIMES_EVOLUCAO',
        ];
        foreach ($cards as $persp => $group) {
            $universo = $perspMap[$persp] ?? strtoupper($persp);
            $mappedCards[$universo] = $group;
        }

        return view('bsc-insights.index', [
            'cards'        => $mappedCards,
            'lastRun'      => $lastRun,
            'snapshotDate' => $snapshotDate,
            'budgetInfo'   => $budgetInfo,
            'isDemo'       => $isDemo,
        ]);
    }

    public function generate(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'socio', 'coordenador'])) {
            return response()->json(['message' => 'Sem permissao'], 403);
        }

        $isDemo = config('bsc_insights.demo_mode', false);
        $force  = (bool) $request->input('force', false);
        $guard  = new AiBudgetGuard();

        // Demo mode — síncrono com DemoCardGenerator (usa tabelas antigas)
        if ($isDemo) {
            return $this->generateDemo($user);
        }

        // Cooldown check
        if (!$force && $guard->isInCooldown()) {
            $lastRun = BscInsightRun::lastSuccessful();
            $cooldownH = config('bsc_insights.cooldown_hours', 6);
            return response()->json([
                'message'   => "Ultimo insight gerado ha menos de {$cooldownH}h.",
                'can_force' => true,
            ], 429);
        }

        // Budget check
        if (!$guard->canRun()) {
            return response()->json(['message' => 'Limite de budget mensal atingido.'], 429);
        }

        // Build snapshot e normalizar para criar o run
        $builder  = new BscInsightSnapshotBuilder();
        $raw      = $builder->build();
        $normResult = (new BscSnapshotNormalizer())->normalize($raw);

        // Cache check
        if (!$force) {
            $cached = BscInsightRun::findCached($normResult['hash'], config('bsc_insights.cache_hours', 12));
            if ($cached) {
                return $this->formatRunResponse($cached);
            }
        }

        // Criar run em status queued
        $run = BscInsightRun::create([
            'snapshot_hash'       => $normResult['hash'],
            'snapshot_json'       => json_encode($normResult['snapshot'], JSON_UNESCAPED_UNICODE),
            'periodo_inicio'      => $builder->getInicio(),
            'periodo_fim'         => $builder->getFim(),
            'status'              => 'queued',
            'model_used'          => config('bsc_insights.openai_model'),
            'prompt_version'      => config('bsc_insights.prompt_version', '2.0'),
            'normalizer_log_json' => json_encode($normResult['log'], JSON_UNESCAPED_UNICODE),
            'force_requested'     => $force,
            'created_by_user_id'  => $user->id,
        ]);

        // Dispatch: se queue driver = sync, executa inline
        if (config('queue.default') === 'sync') {
            $engine = new BscInsightsEngineService();
            $resultRun = $engine->executeFromRun($run, $user->id, $force);
            return $this->formatRunResponse($resultRun);
        }

        // Async
        GenerateBscInsightsJob::dispatch($run->id, $user->id, $force);

        return response()->json([
            'success' => true,
            'async'   => true,
            'run_id'  => $run->id,
            'message' => 'Analise em andamento. Acompanhe o status.',
        ]);
    }

    public function status(int $runId)
    {
        $run = BscInsightRun::find($runId);
        if (!$run) {
            return response()->json(['message' => 'Run nao encontrada'], 404);
        }

        $data = [
            'run_id'  => $run->id,
            'status'  => $run->status,
            'message' => $this->statusMessage($run->status),
        ];

        if ($run->status === 'success') {
            $data = array_merge($data, $this->formatRunData($run));
        } elseif ($run->status === 'failed') {
            $data['error'] = $run->error_message;
        }

        return response()->json($data);
    }

    // ── Privados ──

    private function generateDemo($user)
    {
        $builder  = new BscInsightSnapshotBuilder();
        $snapshot = new \App\Models\BscInsightSnapshot();
        $snapshot->json_payload = json_encode($builder->build());
        $snapshot->periodo_inicio = $builder->getInicio();
        $snapshot->periodo_fim = $builder->getFim();

        $demo = new DemoCardGenerator();
        $result = $demo->generate($snapshot, $user->id);

        return response()->json([
            'success' => true,
            'cards'   => $result['cards'] ?? [],
            'summary' => $result['summary'] ?? null,
            'meta'    => ['total_cards' => count($result['cards'] ?? []), 'model' => 'demo-mode', 'cost' => 0],
            'budget'  => (new AiBudgetGuard())->getBudgetInfo(),
        ]);
    }

    private function formatRunResponse(BscInsightRun $run)
    {
        if ($run->status !== 'success') {
            return response()->json([
                'success' => false,
                'message' => $run->error_message ?? 'Falha na geracao',
                'run_id'  => $run->id,
            ], 500);
        }

        return response()->json(array_merge(['success' => true], $this->formatRunData($run)));
    }

    private function formatRunData(BscInsightRun $run): array
    {
        $cards = BscInsightCardV2::where('run_id', $run->id)->ordered()->get();

        $perspMap = [
            'financeiro' => 'FINANCEIRO',
            'clientes'   => 'CLIENTES_MERCADO',
            'processos'  => 'PROCESSOS_INTERNOS',
            'times'      => 'TIMES_EVOLUCAO',
        ];

        $grouped = [];
        foreach ($cards as $card) {
            $universo = $perspMap[$card->perspectiva] ?? strtoupper($card->perspectiva);
            $grouped[$universo][] = [
                'title'          => $card->titulo,
                'what_changed'   => $card->descricao,
                'why_it_matters' => $card->descricao,
                'recommendation' => $card->recomendacao,
                'next_step'      => $card->acao_sugerida ?? '',
                'severidade'     => strtoupper($card->severidade),
                'confidence'     => $card->confidence,
                'impact_score'   => $card->impact_score,
                'evidences'      => $card->evidencias,
                'questions'      => [],
                'universo'       => $universo,
            ];
        }

        // Summary from meta
        $meta = $run->derived_metrics;
        $summary = null;
        // Se houver meta da IA, usar
        $aiContent = null;
        $lastCard = $cards->last();
        if ($lastCard && $run->snapshot_json) {
            // O meta vem da resposta da IA, não está mais no derived_metrics
            // Para agora, gerar summary básico dos cards
            $criticos = $cards->where('severidade', 'critico');
            $summary = [
                'principais_riscos'        => $criticos->take(3)->pluck('titulo')->toArray(),
                'principais_oportunidades' => $cards->where('severidade', 'info')->take(3)->pluck('titulo')->toArray(),
                'apostas_recomendadas'     => $cards->sortByDesc('impact_score')->take(3)->map(fn($c) => [
                    'descricao'        => $c->recomendacao,
                    'impacto_esperado' => "Score: {$c->impact_score}/10",
                    'esforco'          => $c->severidade === 'critico' ? 'Urgente' : 'Médio',
                ])->values()->toArray(),
            ];
        }

        return [
            'cards'   => $grouped,
            'summary' => $summary,
            'meta'    => [
                'total_cards'  => $cards->count(),
                'model'        => $run->model_used,
                'cost'         => $run->cost_usd_estimated,
                'tokens'       => $run->total_tokens,
                'duration_ms'  => $run->duration_ms,
                'run_id'       => $run->id,
                'cache_hit'    => $run->cache_hit,
                'validator_issues' => count($run->validator_issues),
            ],
            'budget' => (new AiBudgetGuard())->getBudgetInfo(),
        ];
    }

    private function statusMessage(string $status): string
    {
        return match ($status) {
            'queued'     => 'Na fila de processamento...',
            'running'    => 'Construindo snapshot...',
            'validating' => 'Validando dados...',
            'calling_ai' => 'Consultando IA...',
            'processing' => 'Processando resposta...',
            'success'    => 'Concluido!',
            'failed'     => 'Falhou.',
            default      => $status,
        };
    }
}
