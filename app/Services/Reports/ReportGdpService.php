<?php
namespace App\Services\Reports;
use Illuminate\Support\Facades\DB;

class ReportGdpService
{
    public function scorecard(array $filters, int $perPage = 25)
    {
        $query = DB::table('gdp_snapshots as gs')
            ->leftJoin('users as u', 'u.id', '=', 'gs.user_id')
            ->leftJoin('gdp_ciclos as gc', 'gc.id', '=', 'gs.ciclo_id')
            ->select('gs.*', 'u.name as advogado', 'gc.nome as ciclo_nome');
        if (!empty($filters['user_id'])) $query->where('gs.user_id', $filters['user_id']);
        if (!empty($filters['mes'])) $query->where('gs.mes', $filters['mes']);
        $query->orderBy('gs.ranking', 'asc');
        return $query->paginate($perPage);
    }

    public function penalizacoes(array $filters, int $perPage = 25)
    {
        $query = DB::table('gdp_penalizacoes as gp')
            ->leftJoin('users as u', 'u.id', '=', 'gp.user_id')
            ->leftJoin('gdp_penalizacao_tipos as pt', 'pt.id', '=', 'gp.tipo_id')
            ->select('gp.*', 'u.name as advogado', 'pt.codigo', 'pt.nome as tipo_nome');
        if (!empty($filters['user_id'])) $query->where('gp.user_id', $filters['user_id']);
        if (!empty($filters['mes'])) $query->where('gp.mes', $filters['mes']);
        $query->orderByDesc('gp.created_at');
        return $query->paginate($perPage);
    }

    public function avaliacoes180(array $filters, int $perPage = 25)
    {
        $query = DB::table('gdp_eval180_responses as er')
            ->leftJoin('gdp_eval180_forms as ef', 'ef.id', '=', 'er.form_id')
            ->leftJoin('users as avaliado', 'avaliado.id', '=', 'ef.user_id')
            ->leftJoin('users as avaliador', 'avaliador.id', '=', 'er.rater_user_id')
            ->select('er.id','er.rater_type','er.total_score','er.comment_text','er.submitted_at',
                'avaliado.name as avaliado_nome','avaliador.name as avaliador_nome');
        if (!empty($filters['avaliado'])) $query->where('avaliado.name', 'LIKE', '%'.$filters['avaliado'].'%');
        $query->orderByDesc('er.submitted_at');
        return $query->paginate($perPage);
    }
}
