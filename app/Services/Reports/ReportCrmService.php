<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;

class ReportCrmService
{
    // ── REL-C01: Base de Clientes Consolidada ────────────────
    public function baseClientes(array $filters, int $perPage = 25)
    {
        $query = DB::table('crm_accounts as ca')
            ->leftJoin('users as u', 'u.id', '=', 'ca.owner_user_id')
            ->select(
                'ca.id', 'ca.name', 'ca.doc_digits', 'ca.email', 'ca.phone_e164',
                'ca.kind', 'ca.lifecycle', 'ca.health_score', 'ca.segment',
                'ca.last_touch_at', 'ca.created_at',
                'u.name as responsavel',
                DB::raw("DATEDIFF(CURDATE(), ca.last_touch_at) as dias_sem_contato"),
                DB::raw("(SELECT COUNT(*) FROM processos p WHERE p.cliente_datajuri_id = ca.datajuri_pessoa_id AND p.status = 'Ativo') as processos_ativos"),
                DB::raw("(SELECT COALESCE(SUM(m.valor), 0) FROM movimentos m WHERE m.pessoa_id_datajuri = ca.datajuri_pessoa_id AND m.classificacao IN ('RECEITA_PF','RECEITA_PJ') AND m.valor > 0) as receita_acumulada"),
                DB::raw("(SELECT COUNT(*) FROM crm_opportunities o WHERE o.account_id = ca.id AND o.status = 'won') as oportunidades_ganhas"),
                DB::raw("(SELECT COUNT(*) FROM crm_activities act WHERE act.account_id = ca.id) as total_atividades")
            );

        if (!empty($filters['kind'])) $query->where('ca.kind', $filters['kind']);
        if (!empty($filters['lifecycle'])) $query->where('ca.lifecycle', $filters['lifecycle']);
        if (!empty($filters['segment'])) $query->where('ca.segment', $filters['segment']);
        if (!empty($filters['responsavel'])) $query->where('u.name', 'LIKE', '%'.$filters['responsavel'].'%');
        if (!empty($filters['busca'])) {
            $query->where(function($q) use ($filters) {
                $q->where('ca.name', 'LIKE', '%'.$filters['busca'].'%')
                  ->orWhere('ca.email', 'LIKE', '%'.$filters['busca'].'%')
                  ->orWhere('ca.doc_digits', 'LIKE', '%'.$filters['busca'].'%');
            });
        }
        if (!empty($filters['health_min'])) $query->where('ca.health_score', '>=', (int)$filters['health_min']);
        if (!empty($filters['health_max'])) $query->where('ca.health_score', '<=', (int)$filters['health_max']);
        if (!empty($filters['sem_contato_dias'])) {
            $query->whereRaw("DATEDIFF(CURDATE(), ca.last_touch_at) >= ?", [(int)$filters['sem_contato_dias']]);
        }

        $sort = $filters['sort'] ?? 'ca.name';
        $dir = $filters['dir'] ?? 'asc';
        $allowed = ['ca.name','ca.kind','ca.lifecycle','ca.health_score','ca.last_touch_at','ca.created_at','dias_sem_contato','receita_acumulada','processos_ativos'];
        if (!in_array($sort, $allowed)) $sort = 'ca.name';
        $query->orderBy($sort, $dir);

        return $query->paginate($perPage);
    }

    public function baseClientesStats(): array
    {
        return [
            'total' => DB::table('crm_accounts')->count(),
            'clientes' => DB::table('crm_accounts')->where('kind', 'client')->count(),
            'prospects' => DB::table('crm_accounts')->where('kind', 'prospect')->count(),
            'ativos' => DB::table('crm_accounts')->where('lifecycle', 'ativo')->count(),
            'sem_contato_30d' => DB::table('crm_accounts')
                ->whereNotNull('last_touch_at')
                ->whereRaw("DATEDIFF(CURDATE(), last_touch_at) > 30")->count(),
            'sem_score' => DB::table('crm_accounts')->whereNull('health_score')->count(),
        ];
    }

    // ── REL-C02: Pipeline de Oportunidades ───────────────────
    public function pipeline(array $filters, int $perPage = 25)
    {
        $query = DB::table('crm_opportunities as o')
            ->leftJoin('crm_accounts as ca', 'ca.id', '=', 'o.account_id')
            ->leftJoin('crm_stages as s', 's.id', '=', 'o.stage_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.owner_user_id')
            ->whereNull('o.espo_id')
            ->select(
                'o.id', 'o.title', 'o.type', 'o.status', 'o.value_estimated', 'o.value_closed',
                'o.tipo_demanda', 'o.lead_source', 'o.lost_reason',
                'o.won_at', 'o.lost_at', 'o.created_at as criado_em',
                'ca.name as account_name', 'ca.kind as account_kind',
                's.name as estagio', 's.order as stage_order',
                'u.name as responsavel',
                DB::raw("DATEDIFF(CURDATE(), o.created_at) as dias_pipeline"),
                DB::raw("CASE WHEN o.status = 'won' THEN DATEDIFF(o.won_at, o.created_at) WHEN o.status = 'lost' THEN DATEDIFF(o.lost_at, o.created_at) ELSE NULL END as dias_ate_desfecho")
            );

        if (!empty($filters['status'])) $query->where('o.status', $filters['status']);
        if (!empty($filters['stage'])) $query->where('s.name', $filters['stage']);
        if (!empty($filters['tipo_demanda'])) $query->where('o.tipo_demanda', $filters['tipo_demanda']);
        if (!empty($filters['responsavel'])) $query->where('u.name', 'LIKE', '%'.$filters['responsavel'].'%');
        if (!empty($filters['busca'])) {
            $query->where(function($q) use ($filters) {
                $q->where('o.title', 'LIKE', '%'.$filters['busca'].'%')
                  ->orWhere('ca.name', 'LIKE', '%'.$filters['busca'].'%');
            });
        }
        if (!empty($filters['periodo_de'])) $query->where('o.created_at', '>=', $filters['periodo_de'].'-01');
        if (!empty($filters['periodo_ate'])) {
            $p = explode('-', $filters['periodo_ate']);
            if (count($p) === 2) $query->where('o.created_at', '<=', date('Y-m-t', mktime(0,0,0,(int)$p[1],1,(int)$p[0])).' 23:59:59');
        }

        $sort = $filters['sort'] ?? 'o.created_at';
        $dir = $filters['dir'] ?? 'desc';
        $query->orderBy($sort, $dir);

        return $query->paginate($perPage);
    }

    public function pipelineStats(): array
    {
        $total = DB::table('crm_opportunities')->whereNull('espo_id')->count();
        $won = DB::table('crm_opportunities')->whereNull('espo_id')->where('status', 'won')->count();
        $lost = DB::table('crm_opportunities')->whereNull('espo_id')->where('status', 'lost')->count();
        $open = $total - $won - $lost;
        $valorWon = (float) DB::table('crm_opportunities')->whereNull('espo_id')->where('status', 'won')->sum('value_estimated');
        $valorOpen = (float) DB::table('crm_opportunities')->whereNull('espo_id')->whereNotIn('status', ['won','lost'])->sum('value_estimated');
        $avgDays = DB::table('crm_opportunities')->where('status', 'won')->whereNotNull('won_at')
            ->avg(DB::raw("DATEDIFF(won_at, created_at)"));

        return [
            'total' => $total, 'won' => $won, 'lost' => $lost, 'open' => $open,
            'taxa_conversao' => $total > 0 ? round($won / $total * 100, 1) : 0,
            'valor_ganho' => $valorWon, 'valor_pipeline' => $valorOpen,
            'dias_medio_conversao' => round((float)($avgDays ?? 0)),
        ];
    }

    // ── REL-C03: Health Score & Segmentação ──────────────────
    public function healthSegmentacao(array $filters, int $perPage = 25)
    {
        $query = DB::table('crm_accounts as ca')
            ->leftJoin('users as u', 'u.id', '=', 'ca.owner_user_id')
            ->select(
                'ca.id', 'ca.name', 'ca.kind', 'ca.lifecycle', 'ca.health_score',
                'ca.segment', 'ca.segment_summary', 'ca.segment_cached_at',
                'ca.last_touch_at', 'u.name as responsavel',
                DB::raw("CASE
                    WHEN ca.health_score >= 80 THEN 'Excelente'
                    WHEN ca.health_score >= 60 THEN 'Bom'
                    WHEN ca.health_score >= 40 THEN 'Atenção'
                    WHEN ca.health_score >= 20 THEN 'Crítico'
                    WHEN ca.health_score IS NOT NULL THEN 'Perdido'
                    ELSE 'Sem Score'
                END as faixa_score"),
                DB::raw("(SELECT COALESCE(SUM(m.valor),0) FROM movimentos m WHERE m.pessoa_id_datajuri = ca.datajuri_pessoa_id AND m.classificacao IN ('RECEITA_PF','RECEITA_PJ') AND m.valor > 0 AND m.ano >= YEAR(CURDATE()) - 1) as receita_12m")
            );

        if (!empty($filters['segment'])) $query->where('ca.segment', $filters['segment']);
        if (!empty($filters['faixa'])) {
            switch ($filters['faixa']) {
                case 'excelente': $query->where('ca.health_score', '>=', 80); break;
                case 'bom': $query->whereBetween('ca.health_score', [60, 79]); break;
                case 'atencao': $query->whereBetween('ca.health_score', [40, 59]); break;
                case 'critico': $query->whereBetween('ca.health_score', [20, 39]); break;
                case 'perdido': $query->where('ca.health_score', '<', 20)->whereNotNull('ca.health_score'); break;
                case 'sem': $query->whereNull('ca.health_score'); break;
            }
        }
        if (!empty($filters['kind'])) $query->where('ca.kind', $filters['kind']);
        if (!empty($filters['busca'])) $query->where('ca.name', 'LIKE', '%'.$filters['busca'].'%');

        $sort = $filters['sort'] ?? 'ca.health_score';
        $dir = $filters['dir'] ?? 'desc';
        $query->orderBy($sort, $dir);

        return $query->paginate($perPage);
    }

    // ── REL-C04: Atividades CRM ──────────────────────────────
    public function atividades(array $filters, int $perPage = 25)
    {
        $query = DB::table('crm_activities as act')
            ->leftJoin('crm_accounts as ca', 'ca.id', '=', 'act.account_id')
            ->leftJoin('users as uc', 'uc.id', '=', 'act.created_by_user_id')
            ->leftJoin('users as ud', 'ud.id', '=', 'act.completed_by_user_id')
            ->select(
                'act.id', 'act.type', 'act.purpose', 'act.title', 'act.body',
                'act.done_at', 'act.created_at as criado_em',
                'act.visit_location', 'act.visit_receptivity',
                'act.resolution_status', 'act.resolution_notes',
                'ca.name as account_name', 'ca.kind as account_kind',
                'uc.name as criado_por', 'ud.name as concluido_por'
            );

        if (!empty($filters['type'])) $query->where('act.type', $filters['type']);
        if (!empty($filters['purpose'])) $query->where('act.purpose', $filters['purpose']);
        if (!empty($filters['busca'])) {
            $query->where(function($q) use ($filters) {
                $q->where('act.title', 'LIKE', '%'.$filters['busca'].'%')
                  ->orWhere('act.body', 'LIKE', '%'.$filters['busca'].'%')
                  ->orWhere('ca.name', 'LIKE', '%'.$filters['busca'].'%');
            });
        }
        if (!empty($filters['periodo_de'])) $query->where('act.created_at', '>=', $filters['periodo_de'].'-01');
        if (!empty($filters['periodo_ate'])) {
            $p = explode('-', $filters['periodo_ate']);
            if (count($p) === 2) $query->where('act.created_at', '<=', date('Y-m-t', mktime(0,0,0,(int)$p[1],1,(int)$p[0])).' 23:59:59');
        }

        $sort = $filters['sort'] ?? 'act.created_at';
        $dir = $filters['dir'] ?? 'desc';
        $query->orderBy($sort, $dir);

        return $query->paginate($perPage);
    }

    public function atividadesStats(): array
    {
        $byType = DB::table('crm_activities')
            ->select('type', DB::raw('COUNT(*) as qtd'))
            ->groupBy('type')->orderByDesc('qtd')->get()->toArray();

        return [
            'total' => DB::table('crm_activities')->count(),
            'por_tipo' => $byType,
            'ultimos_7d' => DB::table('crm_activities')->where('created_at', '>=', now()->subDays(7))->count(),
            'ultimos_30d' => DB::table('crm_activities')->where('created_at', '>=', now()->subDays(30))->count(),
        ];
    }

    // ── REL-IA01: SIRIC — Análises de Crédito IA ────────────
    public function siricConsultas(array $filters, int $perPage = 25)
    {
        $query = DB::table('siric_consultas as sc')
            ->leftJoin('users as u', 'u.id', '=', 'sc.user_id')
            ->select(
                'sc.id', 'sc.nome', 'sc.cpf_cnpj', 'sc.valor_total', 'sc.parcelas_desejadas',
                'sc.renda_declarada', 'sc.rating', 'sc.score', 'sc.recomendacao',
                'sc.parcelas_max_sugeridas', 'sc.decisao_humana', 'sc.nota_decisao',
                'sc.status', 'sc.created_at', 'u.name as analista',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(sc.actions_ia, '$.relatorio.resumo_executivo')) as resumo_ia")
            );
        if (!empty($filters['rating'])) $query->where('sc.rating', $filters['rating']);
        if (!empty($filters['recomendacao'])) $query->where('sc.recomendacao', $filters['recomendacao']);
        if (!empty($filters['busca'])) {
            $query->where(function($q) use ($filters) {
                $q->where('sc.nome', 'LIKE', '%'.$filters['busca'].'%')
                  ->orWhere('sc.cpf_cnpj', 'LIKE', '%'.$filters['busca'].'%');
            });
        }
        $query->orderByDesc('sc.created_at');
        return $query->paginate($perPage);
    }

    // ── REL-IA02: SIPEX — Propostas de Precificação IA ──────
    public function sipexPropostas(array $filters, int $perPage = 25)
    {
        $query = DB::table('pricing_proposals as pp')
            ->leftJoin('users as u', 'u.id', '=', 'pp.user_id')
            ->select(
                'pp.id', 'pp.nome_proponente', 'pp.tipo_pessoa', 'pp.area_direito',
                'pp.tipo_acao', 'pp.valor_causa', 'pp.valor_economico',
                'pp.recomendacao_ia', 'pp.justificativa_ia',
                'pp.proposta_escolhida', 'pp.valor_final', 'pp.status',
                'pp.created_at', 'u.name as advogado',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(pp.proposta_rapida, '$.valor_honorarios')) as valor_rapida"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(pp.proposta_equilibrada, '$.valor_honorarios')) as valor_equilibrada"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(pp.proposta_premium, '$.valor_honorarios')) as valor_premium")
            );
        if (!empty($filters['area'])) $query->where('pp.area_direito', 'LIKE', '%'.$filters['area'].'%');
        if (!empty($filters['status'])) $query->where('pp.status', $filters['status']);
        if (!empty($filters['recomendacao'])) $query->where('pp.recomendacao_ia', $filters['recomendacao']);
        if (!empty($filters['busca'])) {
            $query->where(function($q) use ($filters) {
                $q->where('pp.nome_proponente', 'LIKE', '%'.$filters['busca'].'%')
                  ->orWhere('pp.tipo_acao', 'LIKE', '%'.$filters['busca'].'%');
            });
        }
        $query->orderByDesc('pp.created_at');
        return $query->paginate($perPage);
    }

}
