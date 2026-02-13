<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Stage;
use App\Models\Crm\Opportunity;
use App\Models\Crm\Account;
use App\Services\Crm\CrmMetricsService;
use App\Models\User;
use Illuminate\Http\Request;

class PipelineController extends Controller
{
    protected CrmMetricsService $metrics;

    public function __construct(CrmMetricsService $metrics)
    {
        $this->metrics = $metrics;
    }

    /**
     * Pipeline Kanban view.
     */
    public function index(Request $request)
    {
        $filters = [
            'owner' => $request->input('owner'),
            'source' => $request->input('source'),
            'period_start' => $request->input('period_start'),
            'period_end' => $request->input('period_end'),
        ];

        // KPIs do header
        $kpis = $this->metrics->pipelineKPIs($filters);

        // Stages com oportunidades open
        $stages = Stage::active()->ordered()->get();

        $pipeline = [];
        foreach ($stages as $stage) {
            $query = Opportunity::with(['account', 'owner'])
                ->where('stage_id', $stage->id)
                ->where('status', 'open');

            if (!empty($filters['owner'])) {
                $query->where('owner_user_id', $filters['owner']);
            }
            if (!empty($filters['source'])) {
                $query->where('source', $filters['source']);
            }
            if (!empty($filters['period_start'])) {
                $query->where('created_at', '>=', $filters['period_start']);
            }
            if (!empty($filters['period_end'])) {
                $query->where('created_at', '<=', $filters['period_end']);
            }

            $opportunities = $query->orderBy('next_action_at')->get();

            $pipeline[] = [
                'stage' => $stage,
                'opportunities' => $opportunities,
                'count' => $opportunities->count(),
                'value' => $opportunities->sum('value_estimated'),
            ];
        }

        // Dados para filtros
        $users = User::orderBy('name')->get(['id', 'name']);
        $sources = Opportunity::whereNotNull('source')
            ->distinct()->pluck('source')->sort()->values();

        return view('crm.pipeline', compact(
            'kpis', 'pipeline', 'stages', 'users', 'sources', 'filters'
        ));
    }

    /**
     * KPIs em JSON (para refresh AJAX futuro).
     */
    public function kpisJson(Request $request)
    {
        $filters = $request->only(['owner', 'source', 'period_start', 'period_end']);
        return response()->json($this->metrics->pipelineKPIs($filters));
    }

    /**
     * Busca de accounts para autocomplete.
     */
    public function searchAccounts(Request $request)
    {
        $term = $request->input('q', '');
        if (strlen($term) < 2) {
            return response()->json([]);
        }

        $accounts = Account::where('name', 'like', "%{$term}%")
            ->limit(10)
            ->get(['id', 'name', 'type']);

        return response()->json($accounts);
    }
}
