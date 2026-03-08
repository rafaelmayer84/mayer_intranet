<?php
namespace App\Services\Reports;
use Illuminate\Support\Facades\DB;

class ReportSisrhService
{
    public function folha(array $filters, int $perPage = 25)
    {
        $query = DB::table('sisrh_apuracoes as sa')
            ->leftJoin('users as u', 'u.id', '=', 'sa.user_id')
            ->leftJoin('sisrh_vinculos as sv', 'sv.user_id', '=', 'sa.user_id')
            ->select(
                'sa.id', 'u.name as colaborador', 'sv.nivel_senioridade',
                'sa.mes', 'sa.ano', 'sa.rb_valor', 'sa.captacao_valor',
                'sa.gdp_score', 'sa.percentual_faixa', 'sa.rv_bruta',
                'sa.reducao_total_pct', 'sa.rv_aplicada', 'sa.status'
            );
        if (!empty($filters['colaborador'])) $query->where('u.name', 'LIKE', '%'.$filters['colaborador'].'%');
        if (!empty($filters['competencia'])) {
            $p = explode('-', $filters['competencia']);
            if (count($p)===2) { $query->where('sa.ano', (int)$p[0])->where('sa.mes', (int)$p[1]); }
        }
        $query->orderByDesc('sa.ano')->orderByDesc('sa.mes');
        return $query->paginate($perPage);
    }

    public function custos(array $filters): array
    {
        return DB::table('sisrh_apuracoes as sa')
            ->leftJoin('users as u', 'u.id', '=', 'sa.user_id')
            ->select(
                DB::raw("CONCAT(sa.ano, '-', LPAD(sa.mes, 2, '0')) as periodo"),
                DB::raw("SUM(sa.rb_valor) as total_rb"),
                DB::raw("SUM(sa.rv_aplicada) as total_rv"),
                DB::raw("SUM(sa.rb_valor + sa.rv_aplicada) as custo_total"),
                DB::raw("COUNT(DISTINCT sa.user_id) as colaboradores")
            )
            ->groupBy('periodo')->orderByDesc('periodo')->get()->toArray();
    }
}
