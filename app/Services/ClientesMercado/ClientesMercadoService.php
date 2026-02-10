<?php

namespace App\Services\ClientesMercado;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ClientesMercadoService
{
    private array $closedStatusValues = [
        'encerrado',
        'arquivado',
        'baixado',
        'finalizado',
        'extinto',
        'transitado',
        'concluído',
        'cancelado',
        'suspenso definitivo'
    ];

    public function buildDashboard(int $ano, int $mes, int $inativosMeses): array
    {
        return [
            'kpis' => $this->calculateKpis($ano, $mes),
            'charts' => $this->buildCharts($ano, $mes),
            'tables' => $this->buildTables($ano, $mes, $inativosMeses),
        ];
    }

    private function calculateKpis(int $ano, int $mes): array
    {
        $dataInicio = Carbon::createFromDate($ano, $mes, 1)->startOfMonth();
        $dataFim = Carbon::createFromDate($ano, $mes, 1)->endOfMonth();

        // Total de Clientes (geral, sem filtro de período)
        $totalClientes = DB::table('clientes')->count();

        // Clientes Ativos (geral - com processos não fechados)
        $clientesAtivosGeral = DB::table('processos')
            ->select('cliente_id')
            ->where(function($q) {
                $q->whereNotIn(DB::raw('LOWER(status)'), $this->closedStatusValues)
                  ->orWhereNull('status');
            })
            ->distinct('cliente_id')
            ->count();

        // % Base Ativa
        $percBaseAtiva = $totalClientes > 0 ? ($clientesAtivosGeral / $totalClientes) * 100 : 0;

        // Clientes Inativos
        $clientesInativos = $totalClientes - $clientesAtivosGeral;

        // Processos em Andamento (apenas no período selecionado)
        $processosAndamento = DB::table('processos')
            ->whereBetween('created_at', [$dataInicio, $dataFim])
            ->where(function($q) {
                $q->whereNotIn(DB::raw('LOWER(status)'), $this->closedStatusValues)
                  ->orWhereNull('status');
            })
            ->count();

        // Novos Clientes no Período (primeira vez que aparecem em processos)
        $novosClientes = DB::table('processos')
            ->select('cliente_id')
            ->whereBetween('created_at', [$dataInicio, $dataFim])
            ->distinct('cliente_id')
            ->count();

        // Pipeline do Mês (soma de leads abertos no período)
        $pipelineDoMes = DB::table('leads')
            ->whereBetween('created_at', [$dataInicio, $dataFim])
            ->whereNotIn('status', ['convertido', 'perdido'])
            ->count() * 5000; // Estimativa: R$ 5.000 por lead

        // Oportunidades Abertas (geral, não apenas do mês)
        $oportunidadesAbertas = DB::table('leads')
            ->whereNotIn('status', ['convertido', 'perdido'])
            ->count();

        return [
            'total_clientes' => [
                'label' => 'Total de Clientes',
                'value' => number_format($totalClientes, 0, ',', '.'),
                'meta' => '',
                'percent' => 0,
                'icon' => '👥',
                'accent' => 'blue',
                'raw' => $totalClientes
            ],
            'clientes_ativos' => [
                'label' => 'Clientes Ativos',
                'value' => number_format($clientesAtivosGeral, 0, ',', '.'),
                'meta' => '200',
                'percent' => $clientesAtivosGeral > 0 ? min(($clientesAtivosGeral / 200) * 100, 100) : 0,
                'icon' => '✅',
                'accent' => 'green',
                'raw' => $clientesAtivosGeral
            ],
            'perc_base_ativa' => [
                'label' => '% Base Ativa',
                'value' => number_format($percBaseAtiva, 1, ',', '.') . '%',
                'meta' => '70%',
                'percent' => min($percBaseAtiva / 70 * 100, 100),
                'icon' => '📊',
                'accent' => 'purple',
                'raw' => $percBaseAtiva
            ],
            'clientes_inativos' => [
                'label' => 'Clientes Inativos',
                'value' => number_format($clientesInativos, 0, ',', '.'),
                'meta' => '',
                'percent' => 0,
                'icon' => '⏸️',
                'accent' => 'orange',
                'raw' => $clientesInativos
            ],
            'processos_andamento' => [
                'label' => 'Processos em Andamento',
                'value' => number_format($processosAndamento, 0, ',', '.'),
                'meta' => '300',
                'percent' => $processosAndamento > 0 ? min(($processosAndamento / 300) * 100, 100) : 0,
                'icon' => '⚖️',
                'accent' => 'blue',
                'raw' => $processosAndamento
            ],
            'valor_pipeline' => [
                'label' => 'Valor Pipeline',
                'value' => 'R$ ' . number_format($pipelineDoMes, 2, ',', '.'),
                'meta' => 'R$ 500.000',
                'percent' => min(($pipelineDoMes / 500000) * 100, 100),
                'icon' => '💰',
                'accent' => 'green',
                'raw' => $pipelineDoMes
            ],
            'oportunidades_abertas' => [
                'label' => 'Oportunidades Abertas',
                'value' => number_format($oportunidadesAbertas, 0, ',', '.'),
                'meta' => '100',
                'percent' => min(($oportunidadesAbertas / 100) * 100, 100),
                'icon' => '🎯',
                'accent' => 'orange',
                'raw' => $oportunidadesAbertas
            ],
            'novos_mes' => [
                'label' => 'Novos no Mês',
                'value' => number_format($novosClientes, 0, ',', '.'),
                'meta' => '50',
                'percent' => $novosClientes > 0 ? min(($novosClientes / 50) * 100, 100) : 0,
                'icon' => '⭐',
                'accent' => 'purple',
                'raw' => $novosClientes
            ]
        ];
    }

    private function buildCharts(int $ano, int $mes): array
    {
        // Gráfico 1: Evolução de Clientes Ativos (12 meses do ano)
        $ativosPorMes = [];
        for ($m = 1; $m <= 12; $m++) {
            $mInicio = Carbon::createFromDate($ano, $m, 1)->startOfMonth();
            $mFim = Carbon::createFromDate($ano, $m, 1)->endOfMonth();
            
            $count = DB::table('processos')
                ->select('cliente_id')
                ->where(function($q) {
                    $q->whereNotIn(DB::raw('LOWER(status)'), $this->closedStatusValues)
                      ->orWhereNull('status');
                })
                ->whereBetween('created_at', [$mInicio, $mFim])
                ->distinct('cliente_id')
                ->count();
            
            $ativosPorMes[] = [
                'mes' => $m,
                'valor' => $count
            ];
        }

        // Gráfico 2: Processos por Status (Top 12)
        $processosPorStatus = DB::table('processos')
            ->select(DB::raw('COALESCE(status, "Sem Status") as status'), DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderBy('total', 'desc')
            ->limit(12)
            ->get();

        $procPorStatus = [];
        foreach ($processosPorStatus as $item) {
            $procPorStatus[] = [
                'status' => $item->status,
                'total' => $item->total
            ];
        }

        // Gráfico 3: Pipeline por Estágio (todos os leads)
        $pipelinePorEstagio = [];

        return [
            'ativos_por_mes' => $ativosPorMes,
            'processos_por_status' => $procPorStatus,
            'processos_por_status_full' => $procPorStatus,
            'pipeline_por_estagio' => $pipelinePorEstagio
        ];
    }

    private function buildTables(int $ano, int $mes, int $inativosMeses): array
    {
        $dataInicio = Carbon::createFromDate($ano, $mes, 1)->startOfMonth();
        $dataFim = Carbon::createFromDate($ano, $mes, 1)->endOfMonth();

        // Tabela 1: Top Clientes por Processos Ativos
        $topClientes = DB::table('processos')
            ->select('cliente_id', DB::raw('COUNT(*) as total'))
            ->where(function($q) {
                $q->whereNotIn(DB::raw('LOWER(status)'), $this->closedStatusValues)
                  ->orWhereNull('status');
            })
            ->groupBy('cliente_id')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();

        $topClientesFormatted = [];
        foreach ($topClientes as $item) {
            $cliente = DB::table('clientes')->where('id', $item->cliente_id)->first();
            $topClientesFormatted[] = [
                'cliente' => $cliente->nome ?? 'Cliente #' . $item->cliente_id,
                'cliente_id' => $item->cliente_id,
                'processos_ativos' => $item->total
            ];
        }

        // Tabela 2: Clientes para Reativação (sem atividade há X meses)
        $dataLimite = Carbon::now()->subMonths($inativosMeses)->endOfDay();
        
        $reativacao = DB::table('processos')
            ->select('cliente_id', DB::raw('MAX(updated_at) as ultima_atividade'))
            ->groupBy('cliente_id')
            ->having('ultima_atividade', '<', $dataLimite)
            ->orderBy('ultima_atividade', 'asc')
            ->limit(10)
            ->get();

        $reativacaoFormatted = [];
        foreach ($reativacao as $item) {
            $cliente = DB::table('clientes')->where('id', $item->cliente_id)->first();
            $mesesInativo = Carbon::parse($item->ultima_atividade)->diffInMonths(Carbon::now());
            $reativacaoFormatted[] = [
                'cliente' => $cliente->nome ?? 'Cliente #' . $item->cliente_id,
                'cliente_id' => $item->cliente_id,
                'ultima_atividade' => Carbon::parse($item->ultima_atividade)->format('d/m/Y'),
                'meses_sem_atividade' => $mesesInativo
            ];
        }

        return [
            'top_clientes' => $topClientesFormatted,
            'reativacao' => $reativacaoFormatted
        ];
    }
}
