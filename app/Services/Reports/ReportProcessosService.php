<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;

class ReportProcessosService
{
    // ── REL-P01: Carteira de Processos ───────────────────────
    public function carteira(array $filters, int $perPage = 25)
    {
        $query = DB::table('processos as p')
            ->leftJoin('fases_processo as fp', function ($j) {
                $j->on('fp.processo_id_datajuri', '=', 'p.datajuri_id')
                  ->where('fp.fase_atual', '=', 'Sim');
            })
            ->select(
                'p.id', 'p.pasta', 'p.cliente_nome', 'p.natureza', 'p.tipo_acao',
                'p.advogado_responsavel', 'p.status', 'p.data_abertura',
                'p.valor_causa', 'p.posicao_cliente',
                'fp.data_ultimo_andamento',
                DB::raw("DATEDIFF(CURDATE(), fp.data_ultimo_andamento) as dias_parado")
            );

        if (!empty($filters['status']) && $filters['status'] !== '') {
            $query->where('p.status', $filters['status']);
        }
        if (!empty($filters['natureza'])) {
            $query->where('p.natureza', 'LIKE', '%' . $filters['natureza'] . '%');
        }
        if (!empty($filters['advogado'])) {
            $query->where('p.advogado_responsavel', 'LIKE', '%' . $filters['advogado'] . '%');
        }
        if (!empty($filters['cliente'])) {
            $query->where('p.cliente_nome', 'LIKE', '%' . $filters['cliente'] . '%');
        }

        $sort = $filters['sort'] ?? 'p.data_abertura';
        $dir = $filters['dir'] ?? 'desc';
        $allowed = ['p.pasta','p.cliente_nome','p.advogado_responsavel','p.status','p.data_abertura','p.valor_causa','dias_parado'];
        if (!in_array($sort, $allowed)) $sort = 'p.data_abertura';
        $query->orderBy($sort, $dir);

        return $query->paginate($perPage);
    }

    // ── REL-P02: Movimentações Processuais ───────────────────
    public function movimentacoes(array $filters, int $perPage = 25)
    {
        $query = DB::table('andamentos_fase as af')
            ->leftJoin('fases_processo as fp', 'fp.datajuri_id', '=', 'af.fase_processo_id_datajuri')
            ->select(
                'af.data_andamento', 'af.descricao', 'af.tipo',
                DB::raw("COALESCE(af.processo_pasta, fp.processo_pasta) as processo_pasta"),
                DB::raw("COALESCE(af.proprietario_nome, fp.proprietario_nome) as advogado")
            );

        if (!empty($filters['periodo_de'])) {
            $query->where('af.data_andamento', '>=', $filters['periodo_de'] . '-01');
        }
        if (!empty($filters['periodo_ate'])) {
            $parts = explode('-', $filters['periodo_ate']);
            if (count($parts) === 2) {
                $lastDay = date('Y-m-t', mktime(0, 0, 0, (int)$parts[1], 1, (int)$parts[0]));
                $query->where('af.data_andamento', '<=', $lastDay);
            }
        }
        if (!empty($filters['advogado'])) {
            $query->where(function($q) use ($filters) {
                $q->where('af.proprietario_nome', 'LIKE', '%'.$filters['advogado'].'%')
                  ->orWhere('fp.proprietario_nome', 'LIKE', '%'.$filters['advogado'].'%');
            });
        }
        if (!empty($filters['busca'])) {
            $query->where('af.descricao', 'LIKE', '%' . $filters['busca'] . '%');
        }

        $sort = $filters['sort'] ?? 'af.data_andamento';
        $dir = $filters['dir'] ?? 'desc';
        $query->orderBy($sort, $dir);

        return $query->paginate($perPage);
    }

    // ── REL-P03: Processos Parados ───────────────────────────
    public function parados(array $filters, int $perPage = 25)
    {
        $diasMinimo = (int) ($filters['dias_minimo'] ?? 30);

        $query = DB::table('processos as p')
            ->join('fases_processo as fp', function ($j) {
                $j->on('fp.processo_id_datajuri', '=', 'p.datajuri_id')
                  ->where('fp.fase_atual', '=', 'Sim');
            })
            ->select(
                'p.pasta', 'p.cliente_nome', 'p.advogado_responsavel', 'p.natureza',
                'p.tipo_acao', 'fp.data_ultimo_andamento',
                DB::raw("DATEDIFF(CURDATE(), fp.data_ultimo_andamento) as dias_parado"),
                DB::raw("CASE
                    WHEN DATEDIFF(CURDATE(), fp.data_ultimo_andamento) > 180 THEN 'CRITICO'
                    WHEN DATEDIFF(CURDATE(), fp.data_ultimo_andamento) > 90 THEN 'ALERTA'
                    ELSE 'ATENCAO'
                END as nivel")
            )
            ->where('p.status', 'Ativo')
            ->whereNotNull('fp.data_ultimo_andamento')
            ->whereRaw("DATEDIFF(CURDATE(), fp.data_ultimo_andamento) >= ?", [$diasMinimo]);

        if (!empty($filters['advogado'])) {
            $query->where('p.advogado_responsavel', 'LIKE', '%' . $filters['advogado'] . '%');
        }
        if (!empty($filters['natureza'])) {
            $query->where('p.natureza', 'LIKE', '%' . $filters['natureza'] . '%');
        }

        $query->orderByDesc('dias_parado');

        return $query->paginate($perPage);
    }

    // ── REL-P04: Prazos e SLA ────────────────────────────────
    public function prazosSla(array $filters, int $perPage = 25)
    {
        $query = DB::table('fases_processo as fp')
            ->leftJoin('processos as p', 'p.datajuri_id', '=', 'fp.processo_id_datajuri')
            ->select(
                'fp.processo_pasta', 'p.cliente_nome', 'fp.tipo_fase', 'fp.instancia',
                'fp.localidade', 'fp.data as data_fase', 'fp.data_ultimo_andamento',
                'fp.dias_fase_ativa', 'fp.fase_atual', 'fp.proprietario_nome',
                DB::raw("CASE
                    WHEN fp.fase_atual = 'Sim' AND fp.dias_fase_ativa > 90 THEN 'CRITICO'
                    WHEN fp.fase_atual = 'Sim' AND fp.dias_fase_ativa > 60 THEN 'ALERTA'
                    WHEN fp.fase_atual = 'Sim' THEN 'OK'
                    ELSE 'ENCERRADA'
                END as status_sla")
            );

        if (!empty($filters['status_sla'])) {
            switch ($filters['status_sla']) {
                case 'critico':
                    $query->where('fp.fase_atual', 'Sim')->where('fp.dias_fase_ativa', '>', 90);
                    break;
                case 'alerta':
                    $query->where('fp.fase_atual', 'Sim')->whereBetween('fp.dias_fase_ativa', [61, 90]);
                    break;
                case 'ok':
                    $query->where('fp.fase_atual', 'Sim')->where('fp.dias_fase_ativa', '<=', 60);
                    break;
            }
        }
        if (!empty($filters['advogado'])) {
            $query->where('fp.proprietario_nome', 'LIKE', '%' . $filters['advogado'] . '%');
        }
        if (!empty($filters['instancia'])) {
            $query->where('fp.instancia', $filters['instancia']);
        }

        $sort = $filters['sort'] ?? 'fp.dias_fase_ativa';
        $dir = $filters['dir'] ?? 'desc';
        $query->orderBy($sort, $dir);

        return $query->paginate($perPage);
    }

    // ── REL-P05: Contratos ───────────────────────────────────
    public function contratos(array $filters, int $perPage = 25)
    {
        $query = DB::table('contratos')
            ->select('numero', 'contratante_nome', 'valor', 'data_assinatura', 'proprietario_nome');

        if (!empty($filters['cliente'])) {
            $query->where('contratante_nome', 'LIKE', '%' . $filters['cliente'] . '%');
        }
        if (!empty($filters['advogado'])) {
            $query->where('proprietario_nome', 'LIKE', '%' . $filters['advogado'] . '%');
        }
        if (!empty($filters['periodo_de'])) {
            $query->where('data_assinatura', '>=', $filters['periodo_de'] . '-01');
        }
        if (!empty($filters['periodo_ate'])) {
            $parts = explode('-', $filters['periodo_ate']);
            if (count($parts) === 2) {
                $lastDay = date('Y-m-t', mktime(0, 0, 0, (int)$parts[1], 1, (int)$parts[0]));
                $query->where('data_assinatura', '<=', $lastDay);
            }
        }

        $sort = $filters['sort'] ?? 'data_assinatura';
        $dir = $filters['dir'] ?? 'desc';
        $query->orderBy($sort, $dir);

        return $query->paginate($perPage);
    }
}
