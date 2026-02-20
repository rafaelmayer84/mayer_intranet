<?php

namespace App\Http\Controllers;

use App\Models\AiRun;
use App\Models\BscInsightCard;
use App\Models\BscInsightSnapshot;
use App\Services\BscInsights\AiBudgetGuard;
use App\Services\BscInsights\BscInsightSnapshotBuilder;
use App\Services\BscInsights\DemoCardGenerator;
use App\Services\BscInsights\OpenAIInsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BscInsightsController extends Controller
{
    /**
     * Exibe a página de BSC Insights com os cards do último run.
     */
    public function index()
    {
        $guard = new AiBudgetGuard();
        $budgetInfo = $guard->getBudgetInfo();

        // Último run com sucesso
        $lastRun = AiRun::lastSuccessfulBscRun();

        $cards = [];
        $summary = null;
        $snapshotDate = null;

        if ($lastRun) {
            $cards = BscInsightCard::where('run_id', $lastRun->id)
                ->ordered()
                ->get()
                ->groupBy('universo');

            $snapshotDate = $lastRun->created_at;

            // Tentar recuperar summary do snapshot (armazenado como campo extra no run)
            // Por simplicidade, summary não é persistido — será exibido apenas no retorno AJAX
        }

        $isDemo = config('bsc_insights.demo_mode', false);

        return view('bsc-insights.index', compact(
            'cards',
            'lastRun',
            'budgetInfo',
            'snapshotDate',
            'isDemo'
        ));
    }

    /**
     * Gera novos insights (AJAX POST).
     */
    public function generate(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Verificar role
        if (!in_array($user->role, ['admin', 'socio', 'coordenador'])) {
            return response()->json(['error' => 'Acesso negado.'], 403);
        }

        $guard = new AiBudgetGuard();
        $isDemo = config('bsc_insights.demo_mode', false);

        // Verificar cooldown (skip em demo)
        if (!$isDemo && $guard->isInCooldown() && !$request->boolean('force')) {
            return response()->json([
                'error'    => 'cooldown',
                'message'  => 'Já houve uma execução nas últimas ' . config('bsc_insights.cooldown_hours', 6) . ' horas. Deseja forçar nova geração?',
                'can_force' => true,
            ], 429);
        }

        // Verificar budget (skip em demo)
        if (!$isDemo && !$guard->canRun()) {
            $budgetInfo = $guard->getBudgetInfo();
            $guard->registerBlocked(null, $user->id);

            return response()->json([
                'error'   => 'budget_exceeded',
                'message' => sprintf(
                    'Limite mensal de IA atingido: $%.2f / $%.2f. Aguarde o próximo mês ou ajuste o limite.',
                    $budgetInfo['spent_usd'],
                    $budgetInfo['limit_usd']
                ),
                'budget' => $budgetInfo,
            ], 402);
        }

        try {
            // 1. Construir snapshot
            $builder  = new BscInsightSnapshotBuilder();
            $data     = $builder->build();
            $hash     = BscInsightSnapshot::hashPayload($data);
            $inicio   = $builder->getInicio();
            $fim      = $builder->getFim();

            // Cache: reaproveitar snapshot se idêntico
            $snapshot = BscInsightSnapshot::findByHash($hash, $inicio, $fim);

            if (!$snapshot) {
                $snapshot = BscInsightSnapshot::create([
                    'periodo_inicio'     => $inicio,
                    'periodo_fim'        => $fim,
                    'json_payload'       => json_encode($data, JSON_UNESCAPED_UNICODE),
                    'payload_hash'       => $hash,
                    'created_by_user_id' => $user->id,
                    'trigger_type'       => 'manual',
                ]);
            }

            // 2. Gerar insights
            if ($isDemo) {
                $generator = new DemoCardGenerator();
                $result    = $generator->generate($snapshot, $user->id);
            } else {
                $service = new OpenAIInsightService();
                $result  = $service->generate($snapshot, $user->id);
            }

            $run   = $result['run'];
            $cards = $result['cards'];

            if ($run->status !== 'success') {
                return response()->json([
                    'error'   => 'generation_failed',
                    'message' => 'Falha na geração: ' . ($run->error_message ?? 'erro desconhecido'),
                    'run_id'  => $run->id,
                ], 500);
            }

            // Agrupar cards por universo para retorno
            $grouped = collect($cards)->groupBy('universo')->map(function ($group) {
                return $group->sortByDesc('impact_score')->values()->map(function ($card) {
                    return [
                        'id'              => $card->id,
                        'universo'        => $card->universo,
                        'severidade'      => $card->severidade,
                        'confidence'      => $card->confidence,
                        'title'           => $card->title,
                        'what_changed'    => $card->what_changed,
                        'why_it_matters'  => $card->why_it_matters,
                        'evidences'       => $card->evidences_json,
                        'recommendation'  => $card->recommendation,
                        'next_step'       => $card->next_step,
                        'questions'       => $card->questions_json,
                        'dependencies'    => $card->dependencies_json,
                        'impact_score'    => $card->impact_score,
                        'severidade_color' => $card->severidade_color,
                    ];
                });
            });

            return response()->json([
                'success' => true,
                'run_id'  => $run->id,
                'cards'   => $grouped,
                'summary' => $result['summary'],
                'meta'    => [
                    'total_cards'   => count($cards),
                    'model'         => $run->model,
                    'tokens'        => $run->total_tokens,
                    'cost_usd'      => $run->estimated_cost_usd,
                    'is_demo'       => $isDemo,
                    'snapshot_id'   => $snapshot->id,
                    'generated_at'  => $run->created_at->toIso8601String(),
                ],
                'budget' => (new AiBudgetGuard())->getBudgetInfo(),
            ]);

        } catch (\Throwable $e) {
            Log::error('BscInsights: erro no generate', ['error' => $e->getMessage()]);

            return response()->json([
                'error'   => 'internal_error',
                'message' => 'Erro interno: ' . $e->getMessage(),
            ], 500);
        }
    }
}
