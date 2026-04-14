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
     * Dashboard de monitoramento — KPIs em TEMPO REAL.
     * GET /nexo/qualidade
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$this->policy->viewModule($user)) {
            abort(403, 'Acesso negado ao módulo NEXO Qualidade.');
        }

        $campaign = $this->getOrCreateAutoCampaign();

        $fourWeeksAgo = Carbon::now('America/Sao_Paulo')->subWeeks(4)->startOfWeek(Carbon::MONDAY);

        // ── KPIs globais em TEMPO REAL (direto das respostas) ──
        $globalStats = DB::table('nexo_qa_sampled_targets as t')
            ->join('nexo_qa_responses_content as c', 'c.target_id', '=', 't.id')
            ->join('nexo_qa_responses_identity as i', 'i.target_id', '=', 't.id')
            ->where('t.campaign_id', $campaign->id)
            ->where('i.answered_at', '>=', $fourWeeksAgo)
            ->selectRaw('COUNT(*) as total_responses')
            ->selectRaw('AVG(c.score_1_5) as global_avg_score')
            ->selectRaw('SUM(CASE WHEN c.nps = 5 THEN 1 ELSE 0 END) as total_promoters')
            ->selectRaw('SUM(CASE WHEN c.nps = 4 THEN 1 ELSE 0 END) as total_passives')
            ->selectRaw('SUM(CASE WHEN c.nps IS NOT NULL AND c.nps <= 3 THEN 1 ELSE 0 END) as total_detractors')
            ->first();

        $totalSent = NexoQaSampledTarget::where('campaign_id', $campaign->id)
            ->where('send_status', 'SENT')
            ->where('sampled_at', '>=', $fourWeeksAgo)
            ->count();

        $globalStats->total_sent = $totalSent;

        // Calcular NPS score (-100 a +100)
        $totalNps = ($globalStats->total_promoters ?? 0) + ($globalStats->total_passives ?? 0) + ($globalStats->total_detractors ?? 0);
        $globalStats->global_nps = $totalNps > 0
            ? round((($globalStats->total_promoters - $globalStats->total_detractors) / $totalNps) * 100, 0)
            : null;

        $responseRate = ($totalSent > 0)
            ? round(($globalStats->total_responses / $totalSent) * 100, 1)
            : 0;

        // ── Ranking por advogado — TEMPO REAL ──
        $ranking = DB::table('nexo_qa_sampled_targets as t')
            ->join('nexo_qa_responses_content as c', 'c.target_id', '=', 't.id')
            ->join('nexo_qa_responses_identity as i', 'i.target_id', '=', 't.id')
            ->join('users as u', 'u.id', '=', 't.responsible_user_id')
            ->where('t.campaign_id', $campaign->id)
            ->where('i.answered_at', '>=', $fourWeeksAgo)
            ->selectRaw('u.id, u.name')
            ->selectRaw('AVG(c.score_1_5) as avg_score')
            ->selectRaw('COUNT(*) as total_responses')
            ->selectRaw('SUM(CASE WHEN c.nps = 5 THEN 1 ELSE 0 END) as promoters')
            ->selectRaw('SUM(CASE WHEN c.nps = 4 THEN 1 ELSE 0 END) as passives')
            ->selectRaw('SUM(CASE WHEN c.nps IS NOT NULL AND c.nps <= 3 THEN 1 ELSE 0 END) as detractors')
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('avg_score')
            ->get()
            ->map(function ($r) {
                $total = $r->promoters + $r->passives + $r->detractors;
                $r->nps_score = $total > 0 ? round((($r->promoters - $r->detractors) / $total) * 100, 0) : null;
                $r->sufficient_data = $r->total_responses >= 5;
                return $r;
            });

        // ── Taxa de resposta por advogado ──
        $sentByLawyer = DB::table('nexo_qa_sampled_targets')
            ->where('campaign_id', $campaign->id)
            ->where('send_status', 'SENT')
            ->where('sampled_at', '>=', $fourWeeksAgo)
            ->whereNotNull('responsible_user_id')
            ->selectRaw('responsible_user_id, COUNT(*) as sent_count')
            ->groupBy('responsible_user_id')
            ->pluck('sent_count', 'responsible_user_id');

        foreach ($ranking as $r) {
            $sent = $sentByLawyer->get($r->id, 0);
            $r->response_rate = $sent > 0 ? round(($r->total_responses / $sent) * 100, 1) : 0;
            $r->sent_count = $sent;
        }

        // ── Distribuição da amostra (ciclo atual = última semana) ──
        $lastWeek = Carbon::now('America/Sao_Paulo')->subWeek();
        $sampleDistribution = DB::table('nexo_qa_sampled_targets as t')
            ->leftJoin('users as u', 'u.id', '=', 't.responsible_user_id')
            ->where('t.campaign_id', $campaign->id)
            ->where('t.sampled_at', '>=', $lastWeek)
            ->selectRaw('COALESCE(u.name, "Sem responsável") as name, COUNT(*) as total, t.send_status')
            ->groupBy('u.name', 't.send_status')
            ->get()
            ->groupBy('name')
            ->map(function ($rows) {
                return (object) [
                    'total' => $rows->sum('total'),
                    'sent' => $rows->where('send_status', 'SENT')->sum('total'),
                    'pending' => $rows->where('send_status', 'PENDING')->sum('total'),
                    'failed' => $rows->where('send_status', 'FAILED')->sum('total'),
                    'skipped' => $rows->where('send_status', 'SKIPPED')->sum('total'),
                ];
            });

        // ── Gráfico tendência (dados históricos da agregação semanal) ──
        $weeklyTrend = DB::table('nexo_qa_aggregates_weekly')
            ->where('week_start', '>=', Carbon::now('America/Sao_Paulo')->subWeeks(8)->format('Y-m-d'))
            ->selectRaw('week_start, SUM(responses_count) as responses, AVG(avg_score) as avg_score, AVG(nps_score) as nps')
            ->groupBy('week_start')
            ->orderBy('week_start')
            ->get();

        // ── Últimos disparos (10 mais recentes) ──
        $recentTargets = NexoQaSampledTarget::where('campaign_id', $campaign->id)
            ->with('responsibleUser')
            ->orderByDesc('sampled_at')
            ->limit(10)
            ->get();

        // ── Últimas respostas (10 mais recentes) ──
        $recentRespostas = DB::table('nexo_qa_sampled_targets as t')
            ->join('nexo_qa_responses_content as c', 'c.target_id', '=', 't.id')
            ->join('nexo_qa_responses_identity as i', 'i.target_id', '=', 't.id')
            ->leftJoin('users as u', 'u.id', '=', 't.responsible_user_id')
            ->where('t.campaign_id', $campaign->id)
            ->select(['t.id as target_id', 'u.name as advogado_nome', 'c.score_1_5', 'c.nps', 'c.free_text', 'i.answered_at'])
            ->orderByDesc('i.answered_at')
            ->limit(10)
            ->get();

        // ── Contadores rápidos ──
        $counters = (object) [
            'total_targets' => NexoQaSampledTarget::where('campaign_id', $campaign->id)->count(),
            'pending' => NexoQaSampledTarget::where('campaign_id', $campaign->id)->where('send_status', 'PENDING')->count(),
            'sent' => NexoQaSampledTarget::where('campaign_id', $campaign->id)->where('send_status', 'SENT')->count(),
            'skipped' => NexoQaSampledTarget::where('campaign_id', $campaign->id)->where('send_status', 'SKIPPED')->count(),
            'failed' => NexoQaSampledTarget::where('campaign_id', $campaign->id)->where('send_status', 'FAILED')->count(),
        ];

        return view('nexo.qualidade.index', compact(
            'campaign', 'globalStats', 'responseRate', 'weeklyTrend', 'ranking',
            'recentTargets', 'recentRespostas', 'counters', 'sampleDistribution'
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
