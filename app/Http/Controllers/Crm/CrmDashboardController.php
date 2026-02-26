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

        return view('crm.dashboard.index', compact(
            'user', 'isRestricted', 'kpis', 'agenda', 'alertas', 'recentClients', 'openOpps'
        ));
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
}
