<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class ReportFinanceiroService
{
    // ── REL-F01: DRE ─────────────────────────────────────────
    public function dre(int $ano, int $mesIni = 1, int $mesFim = 12): array
    {
        $rows = DB::table('movimentos')
            ->select('codigo_plano', 'plano_contas', 'classificacao', 'mes', DB::raw('SUM(valor) as total'))
            ->where('ano', $ano)
            ->whereBetween('mes', [$mesIni, $mesFim])
            ->whereNotIn('classificacao', ['PENDENTE_CLASSIFICACAO', 'PENDENTE', 'IGNORAR', 'TRANSITO'])
            ->groupBy('codigo_plano', 'plano_contas', 'classificacao', 'mes')
            ->orderBy('codigo_plano')
            ->get();

        // Pivotar por mês
        $pivot = [];
        foreach ($rows as $r) {
            $key = $r->codigo_plano;
            if (!isset($pivot[$key])) {
                // Extrair nome curto do plano_contas
                $parts = explode(':', $r->plano_contas);
                $nomeCurto = trim(end($parts));
                $pivot[$key] = [
                    'codigo' => $r->codigo_plano,
                    'rubrica' => $nomeCurto,
                    'classificacao' => $r->classificacao,
                ];
                for ($m = $mesIni; $m <= $mesFim; $m++) {
                    $pivot[$key]['mes_' . $m] = 0;
                }
                $pivot[$key]['total'] = 0;
            }
            $pivot[$key]['mes_' . $r->mes] += (float) $r->total;
            $pivot[$key]['total'] += (float) $r->total;
        }

        return array_values($pivot);
    }

    // ── REL-F02: Extrato de Receitas ─────────────────────────
    public function receitas(array $filters, int $perPage = 25)
    {
        $query = DB::table('movimentos')
            ->whereIn('classificacao', ['RECEITA_PF', 'RECEITA_PJ'])
            ->where('valor', '>', 0)
            ->select('data', 'mes', 'ano', 'pessoa as cliente', 'processo_pasta', 'descricao', 'plano_contas', 'codigo_plano', 'classificacao', 'valor', 'proprietario_nome');

        $this->applyCommonFilters($query, $filters);

        if (!empty($filters['tipo']) && $filters['tipo'] !== '') {
            $query->where('classificacao', $filters['tipo']);
        }
        if (!empty($filters['cliente'])) {
            $query->where('pessoa', 'LIKE', '%' . $filters['cliente'] . '%');
        }
        if (!empty($filters['advogado'])) {
            $query->where('proprietario_nome', 'LIKE', '%' . $filters['advogado'] . '%');
        }

        $sort = $filters['sort'] ?? 'data';
        $dir = $filters['dir'] ?? 'desc';
        $query->orderBy($sort, $dir);

        return $query->paginate($perPage);
    }

    public function receitasTotals(array $filters): array
    {
        $query = DB::table('movimentos')
            ->whereIn('classificacao', ['RECEITA_PF', 'RECEITA_PJ'])
            ->where('valor', '>', 0);

        $this->applyCommonFilters($query, $filters);

        if (!empty($filters['tipo']) && $filters['tipo'] !== '') {
            $query->where('classificacao', $filters['tipo']);
        }
        if (!empty($filters['cliente'])) {
            $query->where('pessoa', 'LIKE', '%' . $filters['cliente'] . '%');
        }

        return [
            'valor' => (float) $query->sum('valor'),
        ];
    }

    // ── REL-F03: Extrato de Despesas ─────────────────────────
    public function despesas(array $filters, int $perPage = 25)
    {
        $query = DB::table('movimentos')
            ->where('classificacao', 'DESPESA')
            ->where('valor', '<', 0)
            ->select('data', 'mes', 'ano', 'descricao', 'plano_contas', 'codigo_plano', DB::raw('ABS(valor) as valor'), 'pessoa', 'proprietario_nome');

        $this->applyCommonFilters($query, $filters);

        if (!empty($filters['busca'])) {
            $query->where('descricao', 'LIKE', '%' . $filters['busca'] . '%');
        }
        if (!empty($filters['categoria'])) {
            $query->where('codigo_plano', 'LIKE', $filters['categoria'] . '%');
        }

        $sort = $filters['sort'] ?? 'data';
        $dir = $filters['dir'] ?? 'desc';
        $query->orderBy($sort, $dir);

        return $query->paginate($perPage);
    }

    public function despesasTotals(array $filters): array
    {
        $query = DB::table('movimentos')
            ->where('classificacao', 'DESPESA')
            ->where('valor', '<', 0);

        $this->applyCommonFilters($query, $filters);

        if (!empty($filters['busca'])) {
            $query->where('descricao', 'LIKE', '%' . $filters['busca'] . '%');
        }

        $total = (float) $query->sum(DB::raw('ABS(valor)'));

        // % sobre receita do período
        $recQuery = DB::table('movimentos')
            ->whereIn('classificacao', ['RECEITA_PF', 'RECEITA_PJ'])
            ->where('valor', '>', 0);
        $this->applyCommonFilters($recQuery, $filters);
        $receita = (float) $recQuery->sum('valor');

        return [
            'valor' => $total,
            'pct_receita' => $receita > 0 ? round($total / $receita * 100, 1) : 0,
        ];
    }

    // ── REL-F04: Contas a Receber & Inadimplência ────────────
    public function contasReceber(array $filters, int $perPage = 25)
    {
        $query = DB::table('contas_receber')
            ->select(
                'id', 'cliente', 'descricao', 'valor', 'data_vencimento', 'data_pagamento', 'status', 'tipo',
                DB::raw("CASE
                    WHEN data_pagamento IS NOT NULL AND data_pagamento != '' THEN 'Pago'
                    WHEN data_vencimento < CURDATE() THEN 'Vencido'
                    ELSE 'Em aberto'
                END as status_calc"),
                DB::raw("CASE
                    WHEN data_pagamento IS NULL OR data_pagamento = '' THEN DATEDIFF(CURDATE(), data_vencimento)
                    ELSE 0
                END as dias_atraso"),
                DB::raw("CASE
                    WHEN (data_pagamento IS NULL OR data_pagamento = '') AND DATEDIFF(CURDATE(), data_vencimento) BETWEEN 0 AND 30 THEN '0-30'
                    WHEN (data_pagamento IS NULL OR data_pagamento = '') AND DATEDIFF(CURDATE(), data_vencimento) BETWEEN 31 AND 60 THEN '31-60'
                    WHEN (data_pagamento IS NULL OR data_pagamento = '') AND DATEDIFF(CURDATE(), data_vencimento) BETWEEN 61 AND 90 THEN '61-90'
                    WHEN (data_pagamento IS NULL OR data_pagamento = '') AND DATEDIFF(CURDATE(), data_vencimento) BETWEEN 91 AND 180 THEN '91-180'
                    WHEN (data_pagamento IS NULL OR data_pagamento = '') AND DATEDIFF(CURDATE(), data_vencimento) > 180 THEN '180+'
                    ELSE '-'
                END as faixa_aging")
            );

        if (!empty($filters['status_filtro'])) {
            switch ($filters['status_filtro']) {
                case 'vencido':
                    $query->whereRaw("(data_pagamento IS NULL OR data_pagamento = '')")
                          ->where('data_vencimento', '<', now()->toDateString());
                    break;
                case 'aberto':
                    $query->whereRaw("(data_pagamento IS NULL OR data_pagamento = '')")
                          ->where('data_vencimento', '>=', now()->toDateString());
                    break;
                case 'pago':
                    $query->whereNotNull('data_pagamento')->where('data_pagamento', '!=', '');
                    break;
            }
        }

        if (!empty($filters['cliente'])) {
            $query->where('cliente', 'LIKE', '%' . $filters['cliente'] . '%');
        }

        if (!empty($filters['venc_de'])) {
            $query->where('data_vencimento', '>=', $filters['venc_de']);
        }
        if (!empty($filters['venc_ate'])) {
            $query->where('data_vencimento', '<=', $filters['venc_ate']);
        }

        $sort = $filters['sort'] ?? 'data_vencimento';
        $dir = $filters['dir'] ?? 'asc';
        $query->orderBy($sort, $dir);

        return $query->paginate($perPage);
    }

    public function contasReceberTotals(array $filters): array
    {
        $base = DB::table('contas_receber');

        $total = (float) $base->sum('valor');

        $vencido = (float) DB::table('contas_receber')
            ->whereRaw("(data_pagamento IS NULL OR data_pagamento = '')")
            ->where('data_vencimento', '<', now()->toDateString())
            ->sum('valor');

        return [
            'valor' => $total,
            'vencido' => $vencido,
            'pct_inadimplencia' => $total > 0 ? round($vencido / $total * 100, 1) : 0,
        ];
    }

    // ── REL-F05: Fluxo de Caixa Mensal ──────────────────────
    public function fluxoCaixa(int $ano, int $mesIni = 1, int $mesFim = 12): array
    {
        $entradas = DB::table('movimentos')
            ->select('mes', DB::raw('SUM(valor) as total'))
            ->where('ano', $ano)
            ->whereBetween('mes', [$mesIni, $mesFim])
            ->whereIn('classificacao', ['RECEITA_PF', 'RECEITA_PJ', 'RECEITA_FINANCEIRA', 'OUTRAS_RECEITAS'])
            ->where('valor', '>', 0)
            ->groupBy('mes')
            ->pluck('total', 'mes');

        $saidas = DB::table('movimentos')
            ->select('mes', DB::raw('SUM(ABS(valor)) as total'))
            ->where('ano', $ano)
            ->whereBetween('mes', [$mesIni, $mesFim])
            ->where('classificacao', 'DESPESA')
            ->where('valor', '<', 0)
            ->groupBy('mes')
            ->pluck('total', 'mes');

        $meses = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $result = [];
        $acumulado = 0;

        for ($m = $mesIni; $m <= $mesFim; $m++) {
            $ent = (float) ($entradas[$m] ?? 0);
            $sai = (float) ($saidas[$m] ?? 0);
            $saldo = $ent - $sai;
            $acumulado += $saldo;

            $result[] = [
                'periodo'    => $meses[$m] . '/' . $ano,
                'entradas'   => $ent,
                'saidas'     => $sai,
                'saldo'      => $saldo,
                'acumulado'  => $acumulado,
            ];
        }

        return $result;
    }

    // ── REL-F06: Receita por Advogado ────────────────────────
    public function receitaAdvogado(array $filters): array
    {
        $query = DB::table('movimentos')
            ->select(
                'proprietario_nome as advogado',
                DB::raw("SUM(CASE WHEN classificacao = 'RECEITA_PF' THEN valor ELSE 0 END) as receita_pf"),
                DB::raw("SUM(CASE WHEN classificacao = 'RECEITA_PJ' THEN valor ELSE 0 END) as receita_pj"),
                DB::raw("SUM(valor) as receita_total"),
                DB::raw("COUNT(*) as num_movimentos"),
                DB::raw("ROUND(SUM(valor) / COUNT(*), 2) as ticket_medio")
            )
            ->whereIn('classificacao', ['RECEITA_PF', 'RECEITA_PJ'])
            ->where('valor', '>', 0)
            ->whereNotNull('proprietario_nome')
            ->where('proprietario_nome', '!=', '');

        $this->applyCommonFilters($query, $filters);

        if (!empty($filters['advogado'])) {
            $query->where('proprietario_nome', 'LIKE', '%' . $filters['advogado'] . '%');
        }

        $query->groupBy('proprietario_nome')
              ->orderByDesc('receita_total');

        return $query->get()->toArray();
    }

    // ── Helper: filtros comuns de período ─────────────────────
    private function applyCommonFilters($query, array $filters): void
    {
        if (!empty($filters['periodo_de'])) {
            $parts = explode('-', $filters['periodo_de']);
            if (count($parts) === 2) {
                $query->where(function ($q) use ($parts) {
                    $q->where('ano', '>', (int) $parts[0])
                      ->orWhere(function ($q2) use ($parts) {
                          $q2->where('ano', (int) $parts[0])
                             ->where('mes', '>=', (int) $parts[1]);
                      });
                });
            }
        }
        if (!empty($filters['periodo_ate'])) {
            $parts = explode('-', $filters['periodo_ate']);
            if (count($parts) === 2) {
                $query->where(function ($q) use ($parts) {
                    $q->where('ano', '<', (int) $parts[0])
                      ->orWhere(function ($q2) use ($parts) {
                          $q2->where('ano', (int) $parts[0])
                             ->where('mes', '<=', (int) $parts[1]);
                      });
                });
            }
        }
    }
}
