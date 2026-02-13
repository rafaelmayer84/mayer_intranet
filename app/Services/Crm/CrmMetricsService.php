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
}
