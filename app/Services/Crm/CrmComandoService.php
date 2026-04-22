<?php

namespace App\Services\Crm;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Agregações para o Painel do Dono (GET /crm/comando).
 *
 * Quatro blocos:
 *   A - Pipeline & Receita
 *   B - Produtividade por advogada
 *   C - Saúde da carteira
 *   D - Origem, conversão e ROI
 *
 * Todos os métodos são 100% SELECT (leitura). Cache de 5 min por bloco.
 */
class CrmComandoService
{
    private const CACHE_TTL = 300; // 5 min

    public function todosOsBlocos(): array
    {
        return Cache::remember('crm_comando_v1_' . now()->format('Y-m-d-H') . '_' . intval(now()->minute / 5), self::CACHE_TTL, function () {
            return [
                'bloco_a_pipeline'      => $this->blocoAPipelineReceita(),
                'bloco_b_produtividade' => $this->blocoBProdutividade(),
                'bloco_c_carteira'      => $this->blocoCCarteira(),
                'bloco_d_origem_roi'    => $this->blocoDOrigemRoi(),
                'gerado_em'             => now()->format('d/m/Y H:i'),
            ];
        });
    }

    // ─── Bloco A: Pipeline & Receita ───────────────────────────────────
    public function blocoAPipelineReceita(): array
    {
        $inicioMes = now()->startOfMonth();
        $fimMes    = now()->endOfMonth();

        // Oportunidades por estágio (abertas)
        $porEstagio = DB::table('crm_opportunities as o')
            ->leftJoin('crm_stages as s', 's.id', '=', 'o.stage_id')
            ->where('o.status', 'open')
            ->groupBy('s.id', 's.name', 's.color', 's.order', 's.is_won', 's.is_lost')
            ->select([
                's.id as stage_id',
                's.name as stage_name',
                's.color as stage_color',
                's.order as stage_order',
                DB::raw('COUNT(*) as qtd'),
                DB::raw('COALESCE(SUM(o.value_estimated), 0) as valor'),
            ])
            ->orderBy('s.order')
            ->get();

        // Ganhas e perdidas no mês
        $ganhasMes = DB::table('crm_opportunities')
            ->where('status', 'won')
            ->whereBetween('won_at', [$inicioMes, $fimMes])
            ->select(DB::raw('COUNT(*) as qtd'), DB::raw('COALESCE(SUM(value_closed), SUM(value_estimated)) as valor'))
            ->first();

        $perdidasMes = DB::table('crm_opportunities')
            ->where('status', 'lost')
            ->whereBetween('lost_at', [$inicioMes, $fimMes])
            ->select(DB::raw('COUNT(*) as qtd'), DB::raw('COALESCE(SUM(value_estimated), 0) as valor'))
            ->first();

        // Forecast simples: soma de abertas com next_action_at nos próximos 60 dias * 50%
        // (sem probability por estágio, assume 50% para dar um número defensível)
        $forecast60d = DB::table('crm_opportunities')
            ->where('status', 'open')
            ->whereNotNull('next_action_at')
            ->whereBetween('next_action_at', [now(), now()->addDays(60)])
            ->sum('value_estimated');

        // Ticket médio últimos 90d
        $ticketMedio = DB::table('crm_opportunities')
            ->where('status', 'won')
            ->where('won_at', '>=', now()->subDays(90))
            ->avg(DB::raw('COALESCE(value_closed, value_estimated)'));

        // Top 10 "a fechar": abertas com value_estimated alto e next_action_at próximo
        $topFechar = DB::table('crm_opportunities as o')
            ->leftJoin('crm_accounts as a', 'a.id', '=', 'o.account_id')
            ->leftJoin('crm_stages as s', 's.id', '=', 'o.stage_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.owner_user_id')
            ->where('o.status', 'open')
            ->whereNotNull('o.value_estimated')
            ->where('o.value_estimated', '>', 0)
            ->orderByRaw('COALESCE(o.next_action_at, "9999-12-31") asc')
            ->orderByDesc('o.value_estimated')
            ->limit(10)
            ->select([
                'o.id', 'o.title', 'o.value_estimated', 'o.next_action_at',
                'a.id as account_id', 'a.name as account_name',
                's.name as stage_name', 's.color as stage_color',
                'u.name as owner_name',
            ])
            ->get();

        return [
            'por_estagio'    => $porEstagio,
            'ganhas_mes'     => $ganhasMes,
            'perdidas_mes'   => $perdidasMes,
            'forecast_60d'   => (float) ($forecast60d ?? 0),
            'ticket_medio'   => (float) ($ticketMedio ?? 0),
            'top_fechar'     => $topFechar,
        ];
    }

    // ─── Bloco B: Produtividade por Advogada ────────────────────────────
    public function blocoBProdutividade(): array
    {
        $inicioSemana = now()->startOfWeek();
        $fimSemana    = now()->endOfWeek();

        // Owners: usuários que aparecem em crm_accounts.owner_user_id
        $owners = DB::table('users as u')
            ->join('crm_accounts as a', 'a.owner_user_id', '=', 'u.id')
            ->where('u.ativo', 1)
            ->groupBy('u.id', 'u.name')
            ->select('u.id', 'u.name', DB::raw('COUNT(DISTINCT a.id) as carteira'))
            ->orderByDesc('carteira')
            ->get();

        $resultado = [];
        foreach ($owners as $o) {
            $atividades = DB::table('crm_activities')
                ->where('completed_by_user_id', $o->id)
                ->whereBetween('due_at', [$inicioSemana, $fimSemana])
                ->whereNotNull('due_at')
                ->count();

            $atividadesFeitas = DB::table('crm_activities')
                ->whereNotNull('due_at')
                ->where('completed_by_user_id', $o->id)
                ->whereBetween('due_at', [$inicioSemana, $fimSemana])
                ->count();

            $oportsMovidas = DB::table('crm_events')
                ->where('type', 'stage_moved')
                ->where('created_by_user_id', $o->id)
                ->whereBetween('happened_at', [$inicioSemana, $fimSemana])
                ->count();

            $gatesResolvidos = DB::table('crm_account_data_gates as g')
                ->join('crm_accounts as a', 'a.id', '=', 'g.account_id')
                ->where('a.owner_user_id', $o->id)
                ->whereIn('g.status', ['resolvido_auto', 'resolvido_manual'])
                ->whereBetween('g.resolved_at', [$inicioSemana, $fimSemana])
                ->count();

            $clientesTocados = DB::table('crm_activities as act')
                ->join('crm_accounts as a', 'a.id', '=', 'act.account_id')
                ->where('a.owner_user_id', $o->id)
                ->whereNotNull('act.due_at')
                ->whereBetween('act.due_at', [$inicioSemana, $fimSemana])
                ->distinct('act.account_id')
                ->count('act.account_id');

            $sla_cumpridos = DB::table('crm_service_requests')
                ->where('assigned_to_user_id', $o->id)
                ->whereNotNull('resolved_at')
                ->whereNotNull('sla_deadline')
                ->whereBetween('resolved_at', [$inicioSemana, $fimSemana])
                ->whereColumn('resolved_at', '<=', 'sla_deadline')
                ->count();

            $sla_violados = DB::table('crm_service_requests')
                ->where('assigned_to_user_id', $o->id)
                ->whereNotNull('resolved_at')
                ->whereNotNull('sla_deadline')
                ->whereBetween('resolved_at', [$inicioSemana, $fimSemana])
                ->whereColumn('resolved_at', '>', 'sla_deadline')
                ->count();

            $resultado[] = [
                'user_id'           => $o->id,
                'nome'              => $o->name,
                'carteira_total'    => $o->carteira,
                'atividades_feitas' => $atividadesFeitas,
                'oportunidades_movidas' => $oportsMovidas,
                'gates_resolvidos'  => $gatesResolvidos,
                'clientes_tocados'  => $clientesTocados,
                'sla_cumpridos'     => $sla_cumpridos,
                'sla_violados'      => $sla_violados,
            ];
        }

        return $resultado;
    }

    // ─── Bloco C: Saúde da Carteira ─────────────────────────────────────
    public function blocoCCarteira(): array
    {
        // Distribuição de lifecycle
        $lifecycleDist = DB::table('crm_accounts')
            ->where('kind', 'client')
            ->groupBy('lifecycle')
            ->select('lifecycle', DB::raw('COUNT(*) as qtd'))
            ->orderByDesc('qtd')
            ->get();

        // Sem toque há 30/60/90 dias (apenas clientes)
        $hoje = now();
        $semToque = [
            '30d' => DB::table('crm_accounts')
                ->where('kind', 'client')
                ->where(function ($q) use ($hoje) {
                    $q->whereNull('last_touch_at')->orWhere('last_touch_at', '<', $hoje->copy()->subDays(30));
                })
                ->count(),
            '60d' => DB::table('crm_accounts')
                ->where('kind', 'client')
                ->where(function ($q) use ($hoje) {
                    $q->whereNull('last_touch_at')->orWhere('last_touch_at', '<', $hoje->copy()->subDays(60));
                })
                ->count(),
            '90d' => DB::table('crm_accounts')
                ->where('kind', 'client')
                ->where(function ($q) use ($hoje) {
                    $q->whereNull('last_touch_at')->orWhere('last_touch_at', '<', $hoje->copy()->subDays(90));
                })
                ->count(),
        ];

        // Concentração por owner (clientes ativos)
        $concentracao = DB::table('crm_accounts as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.owner_user_id')
            ->where('a.kind', 'client')
            ->whereIn('a.lifecycle', ['ativo', 'onboarding'])
            ->groupBy('a.owner_user_id', 'u.name')
            ->select('a.owner_user_id', 'u.name as owner_name', DB::raw('COUNT(*) as qtd'))
            ->orderByDesc('qtd')
            ->get();

        // Health score médio + distribuição em buckets
        $healthMedio = DB::table('crm_accounts')
            ->where('kind', 'client')
            ->whereNotNull('health_score')
            ->avg('health_score');

        $healthBuckets = DB::table('crm_accounts')
            ->where('kind', 'client')
            ->whereNotNull('health_score')
            ->select(DB::raw('
                CASE
                    WHEN health_score >= 80 THEN "excelente"
                    WHEN health_score >= 60 THEN "bom"
                    WHEN health_score >= 40 THEN "medio"
                    WHEN health_score >= 20 THEN "baixo"
                    ELSE "critico"
                END as bucket
            '), DB::raw('COUNT(*) as qtd'))
            ->groupBy('bucket')
            ->get();

        // Gates abertos hoje por tipo
        $gatesAbertos = DB::table('crm_account_data_gates')
            ->whereIn('status', ['aberto', 'em_revisao', 'escalado'])
            ->groupBy('tipo', 'status')
            ->select('tipo', 'status', DB::raw('COUNT(*) as qtd'))
            ->get();

        // Churn silencioso: lifecycle caiu de ativo para adormecido nos últimos 60 dias
        // (via crm_events type=lifecycle_changed com payload relevante, ou fallback via updated_at)
        // Simplificação segura: contas adormecidas que foram atualizadas nos últimos 60 dias
        $churnSilencioso = DB::table('crm_accounts')
            ->where('kind', 'client')
            ->where('lifecycle', 'adormecido')
            ->where('updated_at', '>=', now()->subDays(60))
            ->count();

        return [
            'lifecycle_dist'     => $lifecycleDist,
            'sem_toque'          => $semToque,
            'concentracao'       => $concentracao,
            'health_medio'       => round((float) ($healthMedio ?? 0), 1),
            'health_buckets'     => $healthBuckets,
            'gates_abertos'      => $gatesAbertos,
            'churn_silencioso'   => $churnSilencioso,
        ];
    }

    // ─── Bloco D: Origem, Conversão & ROI ───────────────────────────────
    public function blocoDOrigemRoi(): array
    {
        $inicioMes = now()->startOfMonth();
        $fimMes    = now()->endOfMonth();
        $inicioTrimestre = now()->copy()->subMonths(3);

        // Leads do mês por origem
        $leadsPorOrigem = DB::table('leads')
            ->where('data_entrada', '>=', $inicioMes)
            ->where('data_entrada', '<=', $fimMes)
            ->groupBy('origem_canal')
            ->select('origem_canal', DB::raw('COUNT(*) as qtd'))
            ->orderByDesc('qtd')
            ->get();

        // Conversão por origem (todo histórico): leads → crm_account_id not null → oportunidade won
        $conversaoPorOrigem = DB::table('leads as l')
            ->leftJoin('crm_accounts as a', 'a.id', '=', 'l.crm_account_id')
            ->leftJoin('crm_opportunities as o', function ($j) {
                $j->on('o.account_id', '=', 'a.id')->where('o.status', 'won');
            })
            ->groupBy('l.origem_canal')
            ->select([
                'l.origem_canal',
                DB::raw('COUNT(DISTINCT l.id) as total_leads'),
                DB::raw('COUNT(DISTINCT CASE WHEN l.crm_account_id IS NOT NULL THEN l.id END) as viraram_prospect'),
                DB::raw('COUNT(DISTINCT CASE WHEN o.id IS NOT NULL THEN l.id END) as fecharam'),
            ])
            ->orderByDesc('total_leads')
            ->get();

        // Ciclo médio (dias) entre data_entrada do lead e primeiro won_at da oportunidade da conta
        $cicloMedio = DB::table('leads as l')
            ->join('crm_accounts as a', 'a.id', '=', 'l.crm_account_id')
            ->join('crm_opportunities as o', 'o.account_id', '=', 'a.id')
            ->where('o.status', 'won')
            ->whereNotNull('o.won_at')
            ->whereNotNull('l.data_entrada')
            ->groupBy('l.origem_canal')
            ->select([
                'l.origem_canal',
                DB::raw('AVG(TIMESTAMPDIFF(DAY, l.data_entrada, o.won_at)) as dias_medio'),
                DB::raw('COUNT(DISTINCT o.id) as fechadas'),
            ])
            ->orderByDesc('fechadas')
            ->get();

        // Top 3 canais do trimestre (por leads que viraram prospect)
        $topCanaisTrimestre = DB::table('leads')
            ->where('data_entrada', '>=', $inicioTrimestre)
            ->whereNotNull('crm_account_id')
            ->groupBy('origem_canal')
            ->select('origem_canal', DB::raw('COUNT(*) as qtd'))
            ->orderByDesc('qtd')
            ->limit(3)
            ->get();

        return [
            'leads_por_origem'    => $leadsPorOrigem,
            'conversao_por_origem' => $conversaoPorOrigem,
            'ciclo_medio'         => $cicloMedio,
            'top_canais_trimestre' => $topCanaisTrimestre,
        ];
    }
}
