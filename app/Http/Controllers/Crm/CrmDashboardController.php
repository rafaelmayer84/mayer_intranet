<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmAccount;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmOpportunity;
use App\Models\NotificationIntranet;
use Illuminate\Support\Facades\DB;

class CrmDashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $isRestricted = ($user->role === 'advogado');
        $ownerId = $isRestricted ? $user->id : null;

        // ── KPIs ──
        $kpis = $this->buildKpis($ownerId);

        // ── Agenda do dia (tasks pendentes hoje/amanhã) ──
        $agenda = $this->buildAgenda($ownerId);

        // ── Alertas proativos ──
        $alertas = $this->buildAlertas($ownerId);

        // ── Meus clientes recentes (últimos tocados) ──
        $recentClients = $this->recentClients($ownerId);

        // ── Oportunidades abertas ──
        $openOpps = $this->openOpportunities($ownerId);

        // ── Dados para gráficos ──
        $charts = $this->buildCharts($ownerId);

        // ── Gates de qualidade de dados (ranking por owner p/ gestor, contagem p/ advogado) ──
        $gatesQualidade = $this->buildGatesQualidade($ownerId, $isRestricted);

        return view('crm.dashboard.index', compact(
            'user', 'isRestricted', 'kpis', 'agenda', 'alertas', 'recentClients', 'openOpps', 'charts',
            'gatesQualidade'
        ));
    }

    /**
     * Para advogado: mostra apenas contagem por status dos seus gates.
     * Para gestor/admin: ranking por owner (abertos, em revisao, escalados).
     */
    private function buildGatesQualidade(?int $ownerId, bool $isRestricted): array
    {
        if ($isRestricted) {
            $rows = DB::table('crm_account_data_gates')
                ->where('owner_user_id', $ownerId)
                ->whereIn('status', ['aberto', 'em_revisao', 'escalado'])
                ->select('status', DB::raw('COUNT(*) as n'))
                ->groupBy('status')
                ->pluck('n', 'status')
                ->toArray();
            return ['modo' => 'advogado', 'totais' => $rows];
        }

        $rank = DB::table('crm_account_data_gates as g')
            ->leftJoin('users as u', 'u.id', '=', 'g.owner_user_id')
            ->whereIn('g.status', ['aberto', 'em_revisao', 'escalado'])
            ->select(
                'g.owner_user_id',
                'u.name as owner_name',
                DB::raw("SUM(CASE WHEN g.status='aberto' THEN 1 ELSE 0 END) as abertos"),
                DB::raw("SUM(CASE WHEN g.status='em_revisao' THEN 1 ELSE 0 END) as em_revisao"),
                DB::raw("SUM(CASE WHEN g.status='escalado' THEN 1 ELSE 0 END) as escalados"),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('g.owner_user_id', 'u.name')
            ->orderByDesc('escalados')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        return ['modo' => 'gestor', 'ranking' => $rank];
    }

    private function buildKpis(?int $ownerId): array
    {
        $accBase = CrmAccount::query();
        $oppBase = CrmOpportunity::query();
        if ($ownerId) {
            $accBase->where('owner_user_id', $ownerId);
            $oppBase->where('owner_user_id', $ownerId);
        }

        $activeClients = (clone $accBase)->where('lifecycle', 'ativo')->count();
        $totalClients = (clone $accBase)->whereIn('lifecycle', ['ativo', 'onboarding', 'adormecido'])->count();
        $openOpps = (clone $oppBase)->where('status', 'open')->count();
        $pipelineValue = (clone $oppBase)->where('status', 'open')->sum('value_estimated') ?: 0;

        // Win rate últimos 3 meses
        $closed = (clone $oppBase)
            ->whereIn('status', ['won', 'lost'])
            ->where('updated_at', '>=', now()->subMonths(3));
        $closedCount = (clone $closed)->count();
        $wonCount = (clone $closed)->where('status', 'won')->count();
        $winRate = $closedCount > 0 ? round($wonCount / $closedCount * 100, 1) : 0;

        // Receita won este mês
        $wonMonth = (clone $oppBase)->where('status', 'won')
            ->whereMonth('won_at', now()->month)
            ->whereYear('won_at', now()->year)
            ->sum('value_estimated') ?: 0;

        // Sem contato 30d (ativos)
        $semContato30d = (clone $accBase)->where('lifecycle', 'ativo')
            ->where(function ($q) {
                $q->whereNull('last_touch_at')
                  ->orWhere('last_touch_at', '<', now()->subDays(30));
            })->count();

        return [
            'active_clients'  => $activeClients,
            'total_clients'   => $totalClients,
            'open_opps'       => $openOpps,
            'pipeline_value'  => $pipelineValue,
            'win_rate'        => $winRate,
            'won_month'       => $wonMonth,
            'sem_contato_30d' => $semContato30d,
        ];
    }

    private function buildAgenda(?int $ownerId): object
    {
        $query = CrmActivity::with(['account', 'opportunity'])
            ->whereNull('done_at');

        if ($ownerId) {
            $query->where('created_by_user_id', $ownerId);
        }

        $hoje = (clone $query)->whereDate('due_at', today())->orderBy('due_at')->get();
        $amanha = (clone $query)->whereDate('due_at', today()->addDay())->orderBy('due_at')->get();
        $vencidas = (clone $query)->where('due_at', '<', today())->orderByDesc('due_at')->limit(10)->get();

        return (object) [
            'hoje'    => $hoje,
            'amanha'  => $amanha,
            'vencidas' => $vencidas,
        ];
    }

    private function buildAlertas(?int $ownerId): array
    {
        $alertas = [];

        // 1. Clientes ativos sem contato há 30+ dias
        $semContato = CrmAccount::where('lifecycle', 'ativo')
            ->where(function ($q) {
                $q->whereNull('last_touch_at')
                  ->orWhere('last_touch_at', '<', now()->subDays(30));
            });
        if ($ownerId) $semContato->where('owner_user_id', $ownerId);
        $scList = $semContato->orderBy('last_touch_at')->limit(5)->get(['id', 'name', 'last_touch_at']);

        foreach ($scList as $acc) {
            $dias = $acc->last_touch_at ? (int) \Carbon\Carbon::parse($acc->last_touch_at)->diffInDays(now()) : 999;
            $alertas[] = [
                'tipo'  => 'sem_contato',
                'icone' => '⚠️',
                'cor'   => $dias > 60 ? 'red' : 'yellow',
                'texto' => "{$acc->name} — {$dias} dias sem contato",
                'link'  => route('crm.accounts.show', $acc->id),
            ];
        }

        // 2. Follow-ups vencidos (activities com due_at passada, não concluídas)
        $overdue = CrmActivity::with('account')
            ->whereNull('done_at')
            ->where('due_at', '<', now());
        if ($ownerId) $overdue->where('created_by_user_id', $ownerId);
        $odList = $overdue->orderBy('due_at')->limit(5)->get();

        foreach ($odList as $act) {
            $dias = (int) \Carbon\Carbon::parse($act->due_at)->diffInDays(now());
            $alertas[] = [
                'tipo'  => 'followup_vencido',
                'icone' => '🔴',
                'cor'   => 'red',
                'texto' => "{$act->title} — vencido há {$dias}d" . ($act->account ? " ({$act->account->name})" : ''),
                'link'  => $act->account ? route('crm.accounts.show', $act->account_id) : '#',
            ];
        }

        return $alertas;
    }

    private function recentClients(?int $ownerId): object
    {
        $query = CrmAccount::with('owner')
            ->whereIn('lifecycle', ['ativo', 'onboarding'])
            ->whereNotNull('last_touch_at')
            ->orderByDesc('last_touch_at')
            ->limit(8);
        if ($ownerId) $query->where('owner_user_id', $ownerId);

        return $query->get();
    }

    private function openOpportunities(?int $ownerId): object
    {
        $query = CrmOpportunity::with(['account', 'stage', 'owner'])
            ->where('status', 'open')
            ->orderBy('next_action_at');
        if ($ownerId) $query->where('owner_user_id', $ownerId);

        return $query->limit(10)->get();
    }

    private function buildCharts(?int $ownerId): array
    {
        // 1. Pipeline por estágio (valor e quantidade)
        $pipelineByStage = CrmOpportunity::with('stage')
            ->where('status', 'open')
            ->when($ownerId, fn($q) => $q->where('owner_user_id', $ownerId))
            ->get()
            ->groupBy('stage_id')
            ->map(fn($opps) => [
                'name'  => $opps->first()->stage?->name ?? 'Sem estágio',
                'color' => $opps->first()->stage?->color ?? '#385776',
                'count' => $opps->count(),
                'value' => $opps->sum('value_estimated'),
            ])
            ->values();

        // 2. Tendência mensal: opps ganhas e perdidas (últimos 6 meses)
        $months = collect(range(5, 0))->map(fn($i) => now()->subMonths($i));
        $won = CrmOpportunity::where('status', 'won')
            ->when($ownerId, fn($q) => $q->where('owner_user_id', $ownerId))
            ->where('won_at', '>=', now()->subMonths(6))
            ->get()
            ->groupBy(fn($o) => \Carbon\Carbon::parse($o->won_at)->format('Y-m'));
        $lost = CrmOpportunity::where('status', 'lost')
            ->when($ownerId, fn($q) => $q->where('owner_user_id', $ownerId))
            ->where('updated_at', '>=', now()->subMonths(6))
            ->get()
            ->groupBy(fn($o) => $o->updated_at->format('Y-m'));

        $trend = $months->map(fn($m) => [
            'label' => $m->locale('pt_BR')->isoFormat('MMM/YY'),
            'won'   => $won->get($m->format('Y-m'))?->count() ?? 0,
            'lost'  => $lost->get($m->format('Y-m'))?->count() ?? 0,
        ]);

        // 3. Atividades por tipo (últimos 30 dias)
        $actByType = CrmActivity::where('created_at', '>=', now()->subDays(30))
            ->when($ownerId, fn($q) => $q->where('created_by_user_id', $ownerId))
            ->select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->pluck('total', 'type');

        // 4. Clientes por lifecycle
        $byLifecycle = CrmAccount::when($ownerId, fn($q) => $q->where('owner_user_id', $ownerId))
            ->select('lifecycle', DB::raw('count(*) as total'))
            ->groupBy('lifecycle')
            ->pluck('total', 'lifecycle');

        return [
            'pipeline_stages' => $pipelineByStage,
            'trend'           => $trend,
            'activities'      => $actByType,
            'lifecycle'       => $byLifecycle,
        ];
    }
}
