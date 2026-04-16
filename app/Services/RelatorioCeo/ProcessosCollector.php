<?php

namespace App\Services\RelatorioCeo;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProcessosCollector
{
    public function coletar(Carbon $inicio, Carbon $fim): array
    {
        $inicioStr = $inicio->toDateTimeString();
        $fimStr    = $fim->toDateTimeString();

        // Carteira ativa
        $totalAtivos = DB::table('processos')->whereNull('data_encerramento')->count();
        $totalEncerrados = DB::table('processos')->whereNotNull('data_encerramento')->count();

        // Por tipo de ação (top 10)
        $porTipoAcao = DB::table('processos')
            ->whereNull('data_encerramento')
            ->whereNotNull('tipo_acao')
            ->select('tipo_acao', DB::raw('count(*) as total'), DB::raw('SUM(valor_causa) as valor_total'))
            ->groupBy('tipo_acao')
            ->orderByDesc('total')
            ->take(10)
            ->get()
            ->toArray();

        // Por proprietário (advogado responsável)
        $porProprietario = DB::table('processos as p')
            ->join('users as u', 'u.id', '=', 'p.proprietario_id')
            ->whereNull('p.data_encerramento')
            ->whereNotNull('p.proprietario_id')
            ->select('u.name', DB::raw('count(*) as total'), DB::raw('SUM(p.valor_causa) as valor_total'))
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('total')
            ->get()
            ->toArray();

        // Prazos críticos próximos (próximos 30 dias)
        $prazos = DB::table('atividades_datajuri as a')
            ->join('users as u', 'u.id', '=', 'a.proprietario_id')
            ->whereNull('a.data_conclusao')
            ->whereNotNull('a.data_prazo_fatal')
            ->whereBetween('a.data_prazo_fatal', [now()->toDateTimeString(), now()->addDays(30)->toDateTimeString()])
            ->select('a.processo_pasta', 'a.data_prazo_fatal', 'a.status', 'u.name as responsavel')
            ->orderBy('a.data_prazo_fatal')
            ->take(20)
            ->get()
            ->toArray();

        // Prazos vencidos (atrasados)
        $prazosVencidos = DB::table('atividades_datajuri')
            ->whereNull('data_conclusao')
            ->whereNotNull('data_prazo_fatal')
            ->where('data_prazo_fatal', '<', now()->toDateTimeString())
            ->count();

        // Andamentos no período
        $andamentosPeriodo = DB::table('andamentos_fase')
            ->whereBetween('data_andamento', [$inicioStr, $fimStr])
            ->count();

        $andamentosPorTipo = DB::table('andamentos_fase')
            ->whereBetween('data_andamento', [$inicioStr, $fimStr])
            ->whereNotNull('tipo')
            ->select('tipo', DB::raw('count(*) as total'))
            ->groupBy('tipo')
            ->orderByDesc('total')
            ->take(10)
            ->pluck('total', 'tipo')
            ->toArray();

        // Novos processos no período
        $novosProcessos = DB::table('processos')
            ->whereBetween('created_at', [$inicioStr, $fimStr])
            ->count();

        // Processos encerrados no período (ganho/perda)
        $encerradosPeriodo = DB::table('processos')
            ->whereBetween('data_encerramento', [$inicio->toDateString(), $fim->toDateString()])
            ->select('ganho_causa', DB::raw('count(*) as total'))
            ->groupBy('ganho_causa')
            ->pluck('total', 'ganho_causa')
            ->toArray();

        // Valor total da carteira
        $valorCarteira = DB::table('processos')
            ->whereNull('data_encerramento')
            ->sum('valor_causa');

        $valorProvisionado = DB::table('processos')
            ->whereNull('data_encerramento')
            ->sum('valor_provisionado');

        return [
            'total_ativos'           => $totalAtivos,
            'total_encerrados'       => $totalEncerrados,
            'novos_no_periodo'       => $novosProcessos,
            'encerrados_no_periodo'  => $encerradosPeriodo,
            'valor_carteira'         => (float)$valorCarteira,
            'valor_provisionado'     => (float)$valorProvisionado,
            'por_tipo_acao'          => array_map(fn($t) => (array)$t, $porTipoAcao),
            'por_proprietario'       => array_map(fn($p) => (array)$p, $porProprietario),
            'prazos_proximos'        => array_map(fn($p) => (array)$p, $prazos),
            'prazos_vencidos'        => $prazosVencidos,
            'andamentos_periodo'     => $andamentosPeriodo,
            'andamentos_por_tipo'    => $andamentosPorTipo,
        ];
    }
}
