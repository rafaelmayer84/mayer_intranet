<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmAccount;
use App\Models\Crm\CrmOpportunity;
use App\Models\Crm\CrmStage;
use Illuminate\Support\Facades\DB;

class CrmMetricsService
{
    /**
     * KPIs para o header do pipeline.
     */
    public function pipelineKpis(?int $ownerUserId = null): array
    {
        $base = CrmOpportunity::query();
        if ($ownerUserId) $base->where('owner_user_id', $ownerUserId);

        $openCount  = (clone $base)->open()->count();
        $openValue  = (clone $base)->open()->sum('value_estimated') ?: 0;
        $overdueCount = (clone $base)->overdue()->count();

        $wonMonth = (clone $base)->won()
            ->whereMonth('won_at', now()->month)
            ->whereYear('won_at', now()->year);
        $wonCount = $wonMonth->count();
        $wonValue = (clone $wonMonth)->sum('value_estimated') ?: 0;

        return [
            'open_count'    => $openCount,
            'open_value'    => $openValue,
            'overdue_count' => $overdueCount,
            'won_month'     => $wonCount,
            'won_value'     => $wonValue,
        ];
    }

    /**
     * Dados do kanban agrupados por stage.
     */
    public function kanbanData(?int $ownerUserId = null, ?string $type = null): array
    {
        $stages = CrmStage::active()->ordered()->get();
        $result = [];

        foreach ($stages as $stage) {
            $query = CrmOpportunity::where('stage_id', $stage->id)
                ->with(['account', 'owner']);

            if ($ownerUserId) $query->where('owner_user_id', $ownerUserId);
            if ($type) $query->where('type', $type);

            // Stages finais (won/lost) mostram só último mês
            if ($stage->is_won || $stage->is_lost) {
                $query->where('updated_at', '>=', now()->subDays(30));
            }

            $result[] = [
                'stage' => $stage,
                'opportunities' => $query->orderBy('next_action_at')->get(),
            ];
        }

        return $result;
    }

    /**
     * Relatórios: funil de conversão.
     */
    public function funnelReport(int $months = 6): array
    {
        $stages = CrmStage::active()->ordered()->get();
        $data = [];

        foreach ($stages as $stage) {
            $total = CrmOpportunity::where('stage_id', $stage->id)->count();

            // Contar quantas passaram por esse stage (events)
            $passedThrough = DB::table('crm_events')
                ->where('type', 'stage_changed')
                ->whereRaw("JSON_EXTRACT(payload, '$.to_stage') = ?", [$stage->slug])
                ->where('happened_at', '>=', now()->subMonths($months))
                ->count();

            $data[] = [
                'stage'          => $stage->name,
                'color'          => $stage->color,
                'current_count'  => $total,
                'passed_through' => $passedThrough,
            ];
        }

        return $data;
    }

    /**
     * Win rate por owner.
     */
    public function winRateByOwner(int $months = 3): array
    {
        return DB::select("
            SELECT
                u.name as owner_name,
                COUNT(CASE WHEN o.status = 'won' THEN 1 END) as won,
                COUNT(CASE WHEN o.status = 'lost' THEN 1 END) as lost,
                COUNT(CASE WHEN o.status IN ('won','lost') THEN 1 END) as closed,
                ROUND(
                    COUNT(CASE WHEN o.status = 'won' THEN 1 END) * 100.0 /
                    NULLIF(COUNT(CASE WHEN o.status IN ('won','lost') THEN 1 END), 0)
                , 1) as win_rate
            FROM crm_opportunities o
            LEFT JOIN users u ON u.id = o.owner_user_id
            WHERE o.updated_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
              AND o.status IN ('won', 'lost')
            GROUP BY o.owner_user_id, u.name
            ORDER BY win_rate DESC
        ", [$months]);
    }

    /**
     * Motivos de perda.
     */
    public function lostReasons(int $months = 6): array
    {
        return DB::select("
            SELECT
                COALESCE(lost_reason, 'Não informado') as reason,
                COUNT(*) as count
            FROM crm_opportunities
            WHERE status = 'lost'
              AND lost_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY lost_reason
            ORDER BY count DESC
            LIMIT 10
        ", [$months]);
    }

    /**
     * Valor projetado em 30/60/90 dias.
     */
    public function projectedValue(): array
    {
        $open = CrmOpportunity::open()->get();
        $v30 = $v60 = $v90 = 0;

        foreach ($open as $opp) {
            $val = (float) ($opp->value_estimated ?? 0);
            if ($val <= 0) continue;

            $stage = $opp->stage;
            if (!$stage) continue;

            // Peso por estágio (mais avançado = mais provável)
            $weight = match (true) {
                $stage->slug === 'negociacao' => 0.7,
                $stage->slug === 'proposta'   => 0.4,
                $stage->slug === 'em-contato'  => 0.2,
                default                        => 0.1,
            };

            $weighted = $val * $weight;
            $v30 += $weighted;
            $v60 += $weighted;
            $v90 += $weighted;
        }

        return [
            '30d' => round($v30, 2),
            '60d' => round($v60, 2),
            '90d' => round($v90, 2),
        ];
    }

    /**
     * Tempo médio por etapa (dias).
     */
    public function avgTimePerStage(int $months = 6): array
    {
        return DB::select("
            SELECT
                JSON_UNQUOTE(JSON_EXTRACT(e1.payload, '$.from_stage')) as from_stage,
                JSON_UNQUOTE(JSON_EXTRACT(e1.payload, '$.to_stage')) as to_stage,
                ROUND(AVG(TIMESTAMPDIFF(DAY, e0.happened_at, e1.happened_at)), 1) as avg_days
            FROM crm_events e1
            LEFT JOIN crm_events e0 ON e0.opportunity_id = e1.opportunity_id
                AND e0.type = 'stage_changed'
                AND e0.happened_at < e1.happened_at
                AND JSON_UNQUOTE(JSON_EXTRACT(e0.payload, '$.to_stage')) = JSON_UNQUOTE(JSON_EXTRACT(e1.payload, '$.from_stage'))
            WHERE e1.type = 'stage_changed'
              AND e1.happened_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
              AND e0.id IS NOT NULL
            GROUP BY from_stage, to_stage
            ORDER BY e1.happened_at
        ", [$months]);
    }

    /**
     * Conversão por etapa (%).
     */
    public function conversionByStage(int $months = 6): array
    {
        $stages = CrmStage::active()->ordered()->where('is_lost', false)->get();
        $data = [];
        $prev = null;

        foreach ($stages as $stage) {
            $entered = DB::table('crm_events')
                ->where('type', 'stage_changed')
                ->whereRaw("JSON_EXTRACT(payload, '$.to_stage') = ?", [$stage->slug])
                ->where('happened_at', '>=', now()->subMonths($months))
                ->count();

            $rate = ($prev && $prev > 0) ? round($entered / $prev * 100, 1) : null;

            $data[] = [
                'stage'   => $stage->name,
                'color'   => $stage->color,
                'entered' => $entered,
                'rate'    => $rate,
            ];

            $prev = $entered ?: $prev;
        }

        return $data;
    }

    /**
     * Carteira por advogado — receita, clientes, lifecycle breakdown.
     */
    public function carteiraByOwner(): array
    {
        $owners = DB::select("
            SELECT
                u.id as user_id,
                u.name as owner_name,
                COUNT(a.id) as total_accounts,
                SUM(CASE WHEN a.lifecycle = 'ativo' THEN 1 ELSE 0 END) as ativos,
                SUM(CASE WHEN a.lifecycle = 'onboarding' THEN 1 ELSE 0 END) as onboarding,
                SUM(CASE WHEN a.lifecycle = 'adormecido' THEN 1 ELSE 0 END) as adormecidos,
                SUM(CASE WHEN a.lifecycle = 'arquivado' THEN 1 ELSE 0 END) as arquivados,
                SUM(CASE WHEN a.lifecycle = 'ativo' AND (a.last_touch_at IS NULL OR a.last_touch_at < DATE_SUB(NOW(), INTERVAL 30 DAY)) THEN 1 ELSE 0 END) as sem_contato_30d
            FROM crm_accounts a
            INNER JOIN users u ON u.id = a.owner_user_id
            GROUP BY u.id, u.name
            ORDER BY ativos DESC
        ");

        // Receita por owner (via oportunidades ganhas)
        $receitas = DB::select("
            SELECT
                o.owner_user_id,
                COUNT(CASE WHEN o.status = 'won' THEN 1 END) as won_count,
                COALESCE(SUM(CASE WHEN o.status = 'won' THEN o.value_estimated END), 0) as receita_won,
                COUNT(CASE WHEN o.status = 'open' THEN 1 END) as open_count,
                COALESCE(SUM(CASE WHEN o.status = 'open' THEN o.value_estimated END), 0) as pipeline_value
            FROM crm_opportunities o
            WHERE o.owner_user_id IS NOT NULL
            GROUP BY o.owner_user_id
        ");

        $receitaMap = [];
        foreach ($receitas as $r) {
            $receitaMap[$r->owner_user_id] = $r;
        }

        // Contas a receber por owner (via datajuri_pessoa_id → contas_receber)
        $financeiro = DB::select("
            SELECT
                a.owner_user_id,
                COUNT(cr.id) as titulos_count,
                COALESCE(SUM(cr.valor), 0) as total_valor,
                COALESCE(SUM(CASE WHEN cr.data_vencimento < CURDATE() AND cr.status = 'Não lançado' AND cr.is_stale = 0 THEN cr.valor END), 0) as total_vencido
            FROM crm_accounts a
            INNER JOIN contas_receber cr ON cr.pessoa_datajuri_id = a.datajuri_pessoa_id
            WHERE a.owner_user_id IS NOT NULL
              AND cr.is_stale = 0
              AND cr.status NOT IN ('Concluído', 'Concluido', 'Excluido', 'Excluído')
            GROUP BY a.owner_user_id
        ");

        $finMap = [];
        foreach ($financeiro as $f) {
            $finMap[$f->owner_user_id] = $f;
        }

        foreach ($owners as &$o) {
            $rec = $receitaMap[$o->user_id] ?? null;
            $fin = $finMap[$o->user_id] ?? null;
            $o->won_count = $rec->won_count ?? 0;
            $o->receita_won = $rec->receita_won ?? 0;
            $o->open_count = $rec->open_count ?? 0;
            $o->pipeline_value = $rec->pipeline_value ?? 0;
            $o->titulos_abertos = $fin->titulos_count ?? 0;
            $o->valor_aberto = $fin->total_valor ?? 0;
            $o->valor_vencido = $fin->total_vencido ?? 0;
        }

        return $owners;
    }

    /**
     * Mapa de calor de inatividade — faixas de dias sem contato.
     */
    public function heatmapInatividade(): array
    {
        $faixas = [
            ['label' => '0-15 dias',   'min' => 0,  'max' => 15,  'cor' => '#22C55E'],
            ['label' => '16-30 dias',  'min' => 16, 'max' => 30,  'cor' => '#EAB308'],
            ['label' => '31-60 dias',  'min' => 31, 'max' => 60,  'cor' => '#F97316'],
            ['label' => '61-90 dias',  'min' => 61, 'max' => 90,  'cor' => '#EF4444'],
            ['label' => '90+ dias',    'min' => 91, 'max' => 9999,'cor' => '#991B1B'],
            ['label' => 'Sem registro','min' => -1, 'max' => -1,  'cor' => '#6B7280'],
        ];

        $result = [];
        foreach ($faixas as $f) {
            if ($f['min'] === -1) {
                $qty = DB::table('crm_accounts')
                    ->where('lifecycle', 'ativo')
                    ->whereNull('last_touch_at')
                    ->count();
                $accounts = DB::table('crm_accounts')
                    ->where('lifecycle', 'ativo')
                    ->whereNull('last_touch_at')
                    ->orderBy('name')
                    ->limit(10)
                    ->get(['id', 'name', 'owner_user_id', 'last_touch_at']);
            } else {
                $from = now()->subDays($f['max'])->toDateString();
                $to   = now()->subDays($f['min'])->toDateString();
                $qty = DB::table('crm_accounts')
                    ->where('lifecycle', 'ativo')
                    ->whereNotNull('last_touch_at')
                    ->whereBetween('last_touch_at', [$from, $to])
                    ->count();
                $accounts = DB::table('crm_accounts')
                    ->where('lifecycle', 'ativo')
                    ->whereNotNull('last_touch_at')
                    ->whereBetween('last_touch_at', [$from, $to])
                    ->orderBy('last_touch_at')
                    ->limit(10)
                    ->get(['id', 'name', 'owner_user_id', 'last_touch_at']);
            }

            $result[] = [
                'label'    => $f['label'],
                'cor'      => $f['cor'],
                'qty'      => $qty,
                'accounts' => $accounts->toArray(),
            ];
        }

        return $result;
    }

    /**
     * Funil real enriquecido — com valores monetários.
     */
    public function funnelEnriched(int $months = 6): array
    {
        $stages = CrmStage::active()->ordered()->get();
        $data = [];

        foreach ($stages as $stage) {
            $opps = CrmOpportunity::where('stage_id', $stage->id);
            $total = (clone $opps)->count();
            $value = (clone $opps)->sum('value_estimated') ?: 0;

            $passedThrough = DB::table('crm_events')
                ->where('type', 'stage_changed')
                ->whereRaw("JSON_EXTRACT(payload, '$.to_stage') = ?", [$stage->slug])
                ->where('happened_at', '>=', now()->subMonths($months))
                ->count();

            $data[] = [
                'stage_name'     => $stage->name,
                'slug'           => $stage->slug,
                'color'          => $stage->color,
                'current_count'  => $total,
                'current_value'  => $value,
                'passed_through' => $passedThrough,
                'is_won'         => $stage->is_won,
                'is_lost'        => $stage->is_lost,
            ];
        }

        return $data;
    }

}
