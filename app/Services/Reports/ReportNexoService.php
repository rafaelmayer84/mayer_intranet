<?php
namespace App\Services\Reports;
use Illuminate\Support\Facades\DB;

class ReportNexoService
{
    public function conversas(array $filters, int $perPage = 25)
    {
        $query = DB::table('wa_conversations as wc')
            ->leftJoin('users as u', 'u.id', '=', 'wc.assigned_user_id')
            ->select(
                'wc.id', 'wc.phone', 'wc.name', 'wc.status', 'wc.priority', 'wc.category',
                'wc.bot_ativo', 'wc.created_at', 'wc.last_message_at', 'wc.first_response_at',
                'u.name as atendente',
                DB::raw("(SELECT COUNT(*) FROM wa_messages wm WHERE wm.conversation_id = wc.id) as total_msgs"),
                DB::raw("CASE WHEN wc.first_response_at IS NOT NULL AND wc.created_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, wc.created_at, wc.first_response_at) ELSE NULL END as min_1a_resposta")
            );
        if (!empty($filters['status'])) $query->where('wc.status', $filters['status']);
        if (!empty($filters['atendente'])) $query->where('u.name', 'LIKE', '%'.$filters['atendente'].'%');
        if (!empty($filters['periodo_de'])) $query->where('wc.created_at', '>=', $filters['periodo_de'].'-01');
        if (!empty($filters['periodo_ate'])) {
            $p = explode('-', $filters['periodo_ate']);
            if (count($p)===2) $query->where('wc.created_at', '<=', date('Y-m-t', mktime(0,0,0,(int)$p[1],1,(int)$p[0])).' 23:59:59');
        }
        $query->orderBy($filters['sort'] ?? 'wc.created_at', $filters['dir'] ?? 'desc');
        return $query->paginate($perPage);
    }

    public function conversasStats(): array
    {
        $total = DB::table('wa_conversations')->count();
        $abertas = DB::table('wa_conversations')->where('status', 'open')->count();
        $fechadas = DB::table('wa_conversations')->where('status', 'closed')->count();
        $avgResp = DB::table('wa_conversations')
            ->whereNotNull('first_response_at')->whereNotNull('created_at')
            ->avg(DB::raw("TIMESTAMPDIFF(MINUTE, created_at, first_response_at)"));
        return ['total'=>$total, 'abertas'=>$abertas, 'fechadas'=>$fechadas, 'avg_resposta_min'=>round((float)($avgResp??0))];
    }

    public function tickets(array $filters, int $perPage = 25)
    {
        $query = DB::table('nexo_tickets')
            ->select('id','protocolo','nome_cliente','telefone','tipo','assunto','status','prioridade','atendente','origem','created_at','resolvido_at',
                DB::raw("CASE WHEN resolvido_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, resolvido_at) ELSE NULL END as horas_resolucao"));
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['prioridade'])) $query->where('prioridade', $filters['prioridade']);
        $query->orderBy($filters['sort'] ?? 'created_at', $filters['dir'] ?? 'desc');
        return $query->paginate($perPage);
    }

    public function qa(array $filters, int $perPage = 25)
    {
        $query = DB::table('nexo_qa_responses_content as rc')
            ->leftJoin('nexo_qa_responses_identity as ri', 'ri.target_id', '=', 'rc.target_id')
            ->select('rc.id', 'rc.score_1_5', 'rc.nps', 'rc.tags', 'rc.created_at',
                'ri.answered_at',
                DB::raw("CASE WHEN rc.nps >= 9 THEN 'Promotor' WHEN rc.nps >= 7 THEN 'Neutro' ELSE 'Detrator' END as nps_class"));
        if (!empty($filters['nps_class'])) {
            switch($filters['nps_class']) {
                case 'promotor': $query->where('rc.nps', '>=', 9); break;
                case 'neutro': $query->whereBetween('rc.nps', [7,8]); break;
                case 'detrator': $query->where('rc.nps', '<', 7); break;
            }
        }
        $query->orderByDesc('ri.answered_at');
        return $query->paginate($perPage);
    }

    public function performanceAtendentes(array $filters): array
    {
        return DB::table('wa_conversations as wc')
            ->leftJoin('users as u', 'u.id', '=', 'wc.assigned_user_id')
            ->whereNotNull('u.name')
            ->select(
                'u.name as atendente',
                DB::raw("COUNT(*) as total_conversas"),
                DB::raw("SUM(CASE WHEN wc.status='closed' THEN 1 ELSE 0 END) as fechadas"),
                DB::raw("AVG(CASE WHEN wc.first_response_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, wc.created_at, wc.first_response_at) ELSE NULL END) as avg_1a_resp_min"),
                DB::raw("(SELECT COUNT(*) FROM nexo_tickets nt WHERE nt.atendente = u.name) as tickets_resolvidos")
            )
            ->groupBy('u.name')->orderByDesc('total_conversas')->get()->toArray();
    }
}
