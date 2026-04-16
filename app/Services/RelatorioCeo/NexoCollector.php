<?php

namespace App\Services\RelatorioCeo;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NexoCollector
{
    public function coletar(Carbon $inicio, Carbon $fim): array
    {
        $inicioStr = $inicio->toDateTimeString();
        $fimStr    = $fim->toDateTimeString();

        // Volume de conversas no período
        $conversasPeriodo = DB::table('wa_conversations')
            ->whereBetween('created_at', [$inicioStr, $fimStr])
            ->orWhereBetween('last_message_at', [$inicioStr, $fimStr])
            ->count();

        $novasConversas = DB::table('wa_conversations')
            ->whereBetween('created_at', [$inicioStr, $fimStr])
            ->count();

        // Status breakdown
        $statusBreakdown = DB::table('wa_conversations')
            ->whereBetween('last_message_at', [$inicioStr, $fimStr])
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // Prioridades
        $prioridades = DB::table('wa_conversations')
            ->whereBetween('last_message_at', [$inicioStr, $fimStr])
            ->select('priority', DB::raw('count(*) as total'))
            ->groupBy('priority')
            ->pluck('total', 'priority')
            ->toArray();

        // Por atendente (assigned_user_id)
        $porAtendente = DB::table('wa_conversations as wc')
            ->join('users as u', 'u.id', '=', 'wc.assigned_user_id')
            ->whereBetween('wc.last_message_at', [$inicioStr, $fimStr])
            ->whereNotNull('wc.assigned_user_id')
            ->select('u.name', DB::raw('count(*) as total_atendimentos'))
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('total_atendimentos')
            ->get()
            ->toArray();

        // Mensagens no período
        $totalMensagens = DB::table('wa_messages')
            ->whereBetween('sent_at', [$inicioStr, $fimStr])
            ->count();

        $mensagensIncoming = DB::table('wa_messages')
            ->whereBetween('sent_at', [$inicioStr, $fimStr])
            ->where('direction', 1)
            ->count();

        $mensagensHumanas = DB::table('wa_messages')
            ->whereBetween('sent_at', [$inicioStr, $fimStr])
            ->where('is_human', 1)
            ->count();

        // Tempo médio de primeira resposta (em minutos)
        $tempoMedioResposta = DB::table('wa_conversations')
            ->whereBetween('created_at', [$inicioStr, $fimStr])
            ->whereNotNull('first_response_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) as avg_min')
            ->value('avg_min');

        // QA scores (nexo_qa_aggregates_weekly)
        $qaData = DB::table('nexo_qa_aggregates_weekly as qa')
            ->join('users as u', 'u.id', '=', 'qa.responsible_user_id')
            ->where('qa.week_start', '>=', $inicio->toDateString())
            ->where('qa.week_start', '<=', $fim->toDateString())
            ->select('u.name', DB::raw('AVG(qa.avg_score) as score_medio'), DB::raw('AVG(qa.nps_score) as nps_medio'), DB::raw('SUM(qa.responses_count) as respostas'))
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('score_medio')
            ->get()
            ->toArray();

        // Tickets no período
        $tickets = DB::table('nexo_tickets')
            ->whereBetween('created_at', [$inicioStr, $fimStr])
            ->select('status', 'tipo', DB::raw('count(*) as total'))
            ->groupBy('status', 'tipo')
            ->get()
            ->toArray();

        // Categorias das conversas (campo category)
        $categorias = DB::table('wa_conversations')
            ->whereBetween('last_message_at', [$inicioStr, $fimStr])
            ->whereNotNull('category')
            ->select('category', DB::raw('count(*) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->take(10)
            ->pluck('total', 'category')
            ->toArray();

        // Volume diário (últimos 15 dias)
        $volumeDiario = DB::table('wa_conversations')
            ->whereBetween('created_at', [$inicioStr, $fimStr])
            ->selectRaw('DATE(created_at) as dia, count(*) as novas')
            ->groupBy('dia')
            ->orderBy('dia')
            ->get()
            ->toArray();

        return [
            'conversas_periodo'       => $conversasPeriodo,
            'novas_conversas'         => $novasConversas,
            'total_mensagens'         => $totalMensagens,
            'mensagens_incoming'      => $mensagensIncoming,
            'mensagens_humanas'       => $mensagensHumanas,
            'status_breakdown'        => $statusBreakdown,
            'prioridades'             => $prioridades,
            'por_atendente'           => array_map(fn($a) => (array)$a, $porAtendente),
            'tempo_medio_resposta_min' => round((float)$tempoMedioResposta, 1),
            'qa_scores'               => array_map(fn($q) => (array)$q, $qaData),
            'tickets'                 => array_map(fn($t) => (array)$t, $tickets),
            'categorias'              => $categorias,
            'volume_diario'           => array_map(fn($v) => (array)$v, $volumeDiario),
        ];
    }
}
