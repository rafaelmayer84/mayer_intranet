<?php
namespace App\Services\Reports;
use Illuminate\Support\Facades\DB;

class ReportLeadsService
{
    public function funil(array $filters, int $perPage = 25)
    {
        $query = DB::table('leads')
            ->select('id','nome','telefone','email','area_interesse','sub_area','complexidade','urgencia',
                'perfil_socioeconomico','potencial_honorarios','origem_canal','cidade',
                'resumo_demanda','intencao_contratar','intencao_justificativa',
                'utm_source','utm_medium','utm_campaign','status','crm_account_id','data_entrada','created_at');
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['area'])) $query->where('area_interesse', 'LIKE', '%'.$filters['area'].'%');
        if (!empty($filters['origem'])) $query->where('origem_canal', $filters['origem']);
        if (!empty($filters['busca'])) {
            $query->where(function($q) use ($filters) {
                $q->where('nome', 'LIKE', '%'.$filters['busca'].'%')
                  ->orWhere('resumo_demanda', 'LIKE', '%'.$filters['busca'].'%');
            });
        }
        if (!empty($filters['periodo_de'])) $query->where('data_entrada', '>=', $filters['periodo_de'].'-01');
        if (!empty($filters['periodo_ate'])) {
            $p = explode('-', $filters['periodo_ate']);
            if (count($p)===2) $query->where('data_entrada', '<=', date('Y-m-t', mktime(0,0,0,(int)$p[1],1,(int)$p[0])));
        }
        $query->orderBy($filters['sort'] ?? 'data_entrada', $filters['dir'] ?? 'desc');
        return $query->paginate($perPage);
    }

    public function funilStats(): array
    {
        $byStatus = DB::table('leads')->select('status', DB::raw('COUNT(*) as qtd'))->groupBy('status')->orderByDesc('qtd')->get();
        $total = DB::table('leads')->count();
        $convertidos = DB::table('leads')->where('status', 'convertido')->count();
        return [
            'total'=>$total, 'convertidos'=>$convertidos,
            'taxa_conversao'=>$total>0 ? round($convertidos/$total*100,1) : 0,
            'por_status'=>$byStatus->toArray(),
        ];
    }

    public function marketing(array $filters): array
    {
        return DB::table('leads')
            ->select(
                DB::raw("COALESCE(NULLIF(utm_source,''), NULLIF(origem_canal,''), 'Não identificado') as fonte"),
                DB::raw("COUNT(*) as total_leads"),
                DB::raw("SUM(CASE WHEN status='convertido' THEN 1 ELSE 0 END) as convertidos"),
                DB::raw("SUM(CASE WHEN status='contatado' THEN 1 ELSE 0 END) as contatados"),
                DB::raw("SUM(CASE WHEN status='descartado' THEN 1 ELSE 0 END) as descartados"),
                DB::raw("ROUND(SUM(CASE WHEN status='convertido' THEN 1 ELSE 0 END)/COUNT(*)*100, 1) as taxa_conversao")
            )
            ->groupBy('fonte')->orderByDesc('total_leads')->get()->toArray();
    }
}
