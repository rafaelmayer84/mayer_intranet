<?php

namespace App\Http\Controllers;

use App\Models\NexoQaCampaign;
use App\Models\NexoQaSampledTarget;
use App\Models\NexoQaAggregateWeekly;
use App\Models\NexoQaResponseContent;
use App\Policies\NexoQaPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NexoQaController extends Controller
{
    private NexoQaPolicy $policy;

    public function __construct()
    {
        $this->policy = new NexoQaPolicy();
    }

    /**
     * Garante que exista uma campanha automática permanente.
     * Cria na primeira vez, retorna sempre a mesma depois.
     */
    private function getOrCreateAutoCampaign(): NexoQaCampaign
    {
        $campaign = NexoQaCampaign::first();

        if ($campaign === null) {
            $campaign = NexoQaCampaign::create([
                'name' => 'Pesquisa Contínua',
                'sample_size' => 10,
                'lookback_days' => 21,
                'cooldown_days' => 60,
                'status' => 'DRAFT',
                'channels' => ['whatsapp'],
                'created_by_user_id' => Auth::id(),
            ]);
        }

        return $campaign;
    }

    /**
     * Dashboard de monitoramento.
     * GET /nexo/qualidade
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$this->policy->viewModule($user)) {
            abort(403, 'Acesso negado ao módulo NEXO Qualidade.');
        }

        $campaign = $this->getOrCreateAutoCampaign();

        // KPIs globais (últimas 4 semanas)
        $fourWeeksAgo = Carbon::now('America/Sao_Paulo')->subWeeks(4)->startOfWeek(Carbon::MONDAY);

        $globalStats = DB::table('nexo_qa_aggregates_weekly')
            ->where('week_start', '>=', $fourWeeksAgo->format('Y-m-d'))
            ->selectRaw('SUM(responses_count) as total_responses')
            ->selectRaw('SUM(targets_sent) as total_sent')
            ->selectRaw('AVG(avg_score) as global_avg_score')
            ->selectRaw('AVG(nps_score) as global_nps')
            ->selectRaw('SUM(promoters) as total_promoters')
            ->selectRaw('SUM(detractors) as total_detractors')
            ->selectRaw('SUM(passives) as total_passives')
            ->first();

        $responseRate = ($globalStats->total_sent > 0)
            ? round(($globalStats->total_responses / $globalStats->total_sent) * 100, 1)
            : 0;

        // Agregados semanais para gráfico (últimas 8 semanas)
        $weeklyTrend = DB::table('nexo_qa_aggregates_weekly')
            ->where('week_start', '>=', Carbon::now('America/Sao_Paulo')->subWeeks(8)->format('Y-m-d'))
            ->selectRaw('week_start, SUM(responses_count) as responses, AVG(avg_score) as avg_score, AVG(nps_score) as nps')
            ->groupBy('week_start')
            ->orderBy('week_start')
            ->get();

        // Ranking por advogado (últimas 4 semanas)
        $ranking = DB::table('nexo_qa_aggregates_weekly as a')
            ->join('users as u', 'u.id', '=', 'a.responsible_user_id')
            ->where('a.week_start', '>=', $fourWeeksAgo->format('Y-m-d'))
            ->selectRaw('u.id, u.name, AVG(a.avg_score) as avg_score, AVG(a.nps_score) as nps_score, SUM(a.responses_count) as total_responses')
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('avg_score')
            ->get();

        // Últimos disparos (10 mais recentes)
        $recentTargets = NexoQaSampledTarget::where('campaign_id', $campaign->id)
            ->with('responsibleUser')
            ->orderByDesc('sampled_at')
            ->limit(10)
            ->get();

        // Últimas respostas (10 mais recentes)
        $recentRespostas = DB::table('nexo_qa_sampled_targets as t')
            ->join('nexo_qa_responses_content as c', 'c.target_id', '=', 't.id')
            ->join('nexo_qa_responses_identity as i', 'i.target_id', '=', 't.id')
            ->leftJoin('users as u', 'u.id', '=', 't.responsible_user_id')
            ->where('t.campaign_id', $campaign->id)
            ->select(['t.id as target_id', 'u.name as advogado_nome', 'c.score_1_5', 'c.nps', 'c.free_text', 'i.answered_at'])
            ->orderByDesc('i.answered_at')
            ->limit(10)
            ->get();

        // Contadores rápidos
        $counters = (object) [
            'total_targets' => NexoQaSampledTarget::where('campaign_id', $campaign->id)->count(),
            'pending' => NexoQaSampledTarget::where('campaign_id', $campaign->id)->where('send_status', 'PENDING')->count(),
            'sent' => NexoQaSampledTarget::where('campaign_id', $campaign->id)->where('send_status', 'SENT')->count(),
            'skipped' => NexoQaSampledTarget::where('campaign_id', $campaign->id)->where('send_status', 'SKIPPED')->count(),
            'failed' => NexoQaSampledTarget::where('campaign_id', $campaign->id)->where('send_status', 'FAILED')->count(),
        ];

        return view('nexo.qualidade.index', compact(
            'campaign', 'globalStats', 'responseRate', 'weeklyTrend', 'ranking',
            'recentTargets', 'recentRespostas', 'counters'
        ));
    }

    /**
     * Detalhes de targets.
     * GET /nexo/qualidade/{campaign}/targets
     */
    public function targets(Request $request, int $campaignId)
    {
        $user = Auth::user();
        if (!$this->policy->viewTargets($user)) {
            abort(403);
        }

        $campaign = NexoQaCampaign::findOrFail($campaignId);

        $query = NexoQaSampledTarget::where('campaign_id', $campaignId)
            ->with('responsibleUser');

        if ($request->filled('status')) {
            $query->where('send_status', $request->status);
        }

        $targets = $query->orderByDesc('sampled_at')->paginate(30);

        return view('nexo.qualidade.targets', compact('campaign', 'targets'));
    }

    /**
     * Respostas recebidas.
     * GET /nexo/qualidade/{campaign}/respostas
     */
    public function respostas(Request $request, int $campaignId)
    {
        $user = Auth::user();
        if (!$this->policy->viewResponses($user)) {
            abort(403);
        }

        $campaign = NexoQaCampaign::findOrFail($campaignId);

        $respostas = DB::table('nexo_qa_sampled_targets as t')
            ->join('nexo_qa_responses_content as c', 'c.target_id', '=', 't.id')
            ->join('nexo_qa_responses_identity as i', 'i.target_id', '=', 't.id')
            ->leftJoin('users as u', 'u.id', '=', 't.responsible_user_id')
            ->where('t.campaign_id', $campaignId)
            ->select([
                't.id as target_id',
                't.responsible_user_id',
                'u.name as advogado_nome',
                'c.score_1_5',
                'c.nps',
                'c.free_text',
                'i.answered_at',
                'i.opted_out',
                't.phone_e164',
            ])
            ->orderByDesc('i.answered_at')
            ->paginate(30);

        $canViewIdentity = $this->policy->viewIdentity($user);

        return view('nexo.qualidade.respostas', compact(
            'campaign', 'respostas', 'canViewIdentity'
        ));
    }

    /**
     * Alternar status: DRAFT→ACTIVE, ACTIVE→PAUSED, PAUSED→ACTIVE.
     * PATCH /nexo/qualidade/{campaign}/toggle-status
     */
    public function toggleStatus(int $campaignId)
    {
        $user = Auth::user();
        if (!$this->policy->manageCampaigns($user)) {
            abort(403);
        }

        $campaign = NexoQaCampaign::findOrFail($campaignId);

        $newStatus = match ($campaign->status) {
            'DRAFT' => 'ACTIVE',
            'ACTIVE' => 'PAUSED',
            'PAUSED' => 'ACTIVE',
            default => $campaign->status,
        };

        $campaign->update(['status' => $newStatus]);

        return redirect()->route('nexo.qualidade.index')
            ->with('success', "Status alterado para {$newStatus}.");
    }

    /**
     * Atualizar configuração da campanha.
     * PATCH /nexo/qualidade/{campaign}/config
     */
    public function updateConfig(Request $request, int $campaignId)
    {
        $user = Auth::user();
        if (!$this->policy->manageCampaigns($user)) {
            abort(403);
        }

        $campaign = NexoQaCampaign::findOrFail($campaignId);

        $validated = $request->validate([
            'sample_size' => 'required|integer|min:1|max:100',
            'lookback_days' => 'required|integer|min:1|max:90',
            'cooldown_days' => 'required|integer|min:1|max:365',
        ]);

        $campaign->update($validated);

        return redirect()->route('nexo.qualidade.index')
            ->with('success', 'Configuração atualizada.');
    }

    /**
     * Excluir campanha e todos os dados relacionados.
     * DELETE /nexo/qualidade/{campaign}
     */
    public function destroy(int $campaignId)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin'])) {
            abort(403);
        }

        $campaign = NexoQaCampaign::findOrFail($campaignId);

        // Limpar dados relacionados
        $targetIds = NexoQaSampledTarget::where('campaign_id', $campaign->id)->pluck('id');
        DB::table('nexo_qa_responses_content')->whereIn('target_id', $targetIds)->delete();
        DB::table('nexo_qa_responses_identity')->whereIn('target_id', $targetIds)->delete();
        NexoQaSampledTarget::where('campaign_id', $campaign->id)->delete();
        $campaign->delete();

        return redirect()->route('nexo.qualidade.index')
            ->with('success', 'Campanha excluída.');
    }
}
