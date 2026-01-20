<?php

namespace App\Services;

use App\Models\Movimento;
use App\Models\Meta;
use Illuminate\Support\Facades\DB;

class KpiFinanceiroService
{
    /**
     * Obter resumo financeiro de um mês específico
     */
    public function getResumoMes(int $mes, int $ano): array
    {
        $query = Movimento::where('mes', $mes)
                          ->where('ano', $ano)
                          ->where('valor', '>', 0);

        $receitaPF = (clone $query)->where('classificacao', Movimento::RECEITA_PF)->sum('valor');
        $receitaPJ = (clone $query)->where('classificacao', Movimento::RECEITA_PJ)->sum('valor');
        $receitaFinanceira = (clone $query)->where('classificacao', Movimento::RECEITA_FINANCEIRA)->sum('valor');
        $pendentes = (clone $query)->where('classificacao', Movimento::PENDENTE_CLASSIFICACAO)->count();

        $totalReceita = $receitaPF + $receitaPJ + $receitaFinanceira;

        // Buscar metas do mês (se existirem)
        $metas = $this->getMetasMes($mes, $ano);

        return [
            'mes' => $mes,
            'ano' => $ano,
            'nome_mes' => $this->getNomeMes($mes),
            'receita_pf' => round($receitaPF, 2),
            'receita_pj' => round($receitaPJ, 2),
            'receita_financeira' => round($receitaFinanceira, 2),
            'total_receita' => round($totalReceita, 2),
            'pendentes_classificacao' => $pendentes,
            'meta_pf' => $metas['meta_pf'] ?? 0,
            'meta_pj' => $metas['meta_pj'] ?? 0,
            'meta_financeira' => $metas['meta_financeira'] ?? 0,
            'meta_total' => ($metas['meta_pf'] ?? 0) + ($metas['meta_pj'] ?? 0) + ($metas['meta_financeira'] ?? 0),
            'percentual_pf' => $metas['meta_pf'] > 0 ? round(($receitaPF / $metas['meta_pf']) * 100, 1) : 0,
            'percentual_pj' => $metas['meta_pj'] > 0 ? round(($receitaPJ / $metas['meta_pj']) * 100, 1) : 0,
            'percentual_financeira' => $metas['meta_financeira'] > 0 ? round(($receitaFinanceira / $metas['meta_financeira']) * 100, 1) : 0,
        ];
    }

    /**
     * Obter resumo financeiro do ano inteiro
     */
    public function getResumoAno(int $ano): array
    {
        $meses = [];
        $totais = [
            'receita_pf' => 0,
            'receita_pj' => 0,
            'receita_financeira' => 0,
            'total_receita' => 0,
            'meta_pf' => 0,
            'meta_pj' => 0,
            'meta_financeira' => 0,
        ];

        for ($mes = 1; $mes <= 12; $mes++) {
            $resumoMes = $this->getResumoMes($mes, $ano);
            $meses[] = $resumoMes;

            $totais['receita_pf'] += $resumoMes['receita_pf'];
            $totais['receita_pj'] += $resumoMes['receita_pj'];
            $totais['receita_financeira'] += $resumoMes['receita_financeira'];
            $totais['total_receita'] += $resumoMes['total_receita'];
            $totais['meta_pf'] += $resumoMes['meta_pf'];
            $totais['meta_pj'] += $resumoMes['meta_pj'];
            $totais['meta_financeira'] += $resumoMes['meta_financeira'];
        }

        return [
            'ano' => $ano,
            'meses' => $meses,
            'totais' => $totais,
            'percentual_pf' => $totais['meta_pf'] > 0 ? round(($totais['receita_pf'] / $totais['meta_pf']) * 100, 1) : 0,
            'percentual_pj' => $totais['meta_pj'] > 0 ? round(($totais['receita_pj'] / $totais['meta_pj']) * 100, 1) : 0,
            'percentual_financeira' => $totais['meta_financeira'] > 0 ? round(($totais['receita_financeira'] / $totais['meta_financeira']) * 100, 1) : 0,
        ];
    }

    /**
     * Obter comparativo entre meses
     */
    public function getComparativoMeses(int $mesInicio, int $anoInicio, int $mesFim, int $anoFim): array
    {
        $meses = [];
        
        $dataInicio = "{$anoInicio}-{$mesInicio}-01";
        $dataFim = "{$anoFim}-{$mesFim}-01";

        $resultados = Movimento::select(
                DB::raw('mes'),
                DB::raw('ano'),
                DB::raw('classificacao'),
                DB::raw('SUM(valor) as total')
            )
            ->where('valor', '>', 0)
            ->whereIn('classificacao', [
                Movimento::RECEITA_PF,
                Movimento::RECEITA_PJ,
                Movimento::RECEITA_FINANCEIRA
            ])
            ->groupBy('ano', 'mes', 'classificacao')
            ->orderBy('ano')
            ->orderBy('mes')
            ->get();

        // Organizar por mês/ano
        $dados = [];
        foreach ($resultados as $r) {
            $key = "{$r->ano}-{$r->mes}";
            if (!isset($dados[$key])) {
                $dados[$key] = [
                    'mes' => $r->mes,
                    'ano' => $r->ano,
                    'nome_mes' => $this->getNomeMes($r->mes),
                    'receita_pf' => 0,
                    'receita_pj' => 0,
                    'receita_financeira' => 0,
                ];
            }
            
            switch ($r->classificacao) {
                case Movimento::RECEITA_PF:
                    $dados[$key]['receita_pf'] = round($r->total, 2);
                    break;
                case Movimento::RECEITA_PJ:
                    $dados[$key]['receita_pj'] = round($r->total, 2);
                    break;
                case Movimento::RECEITA_FINANCEIRA:
                    $dados[$key]['receita_financeira'] = round($r->total, 2);
                    break;
            }
        }

        return array_values($dados);
    }

    /**
     * Obter metas do mês
     */
    protected function getMetasMes(int $mes, int $ano): array
    {
        try {
            $meta = Meta::where('mes', $mes)->where('ano', $ano)->first();
            
            if ($meta) {
                return [
                    'meta_pf' => $meta->meta_receita_pf ?? 0,
                    'meta_pj' => $meta->meta_receita_pj ?? 0,
                    'meta_financeira' => $meta->meta_receita_financeira ?? 0,
                ];
            }
        } catch (\Exception $e) {
            // Tabela de metas pode não existir ainda
        }

        return [
            'meta_pf' => 0,
            'meta_pj' => 0,
            'meta_financeira' => 0,
        ];
    }

    /**
     * Obter nome do mês
     */
    protected function getNomeMes(int $mes): string
    {
        $meses = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março',
            4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
            7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro',
            10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
        ];
        return $meses[$mes] ?? '';
    }

    /**
     * Obter dados para gráfico de evolução mensal
     */
    public function getDadosGraficoEvolucao(int $ano): array
    {
        $labels = [];
        $dataPF = [];
        $dataPJ = [];
        $dataFinanceira = [];

        for ($mes = 1; $mes <= 12; $mes++) {
            $resumo = $this->getResumoMes($mes, $ano);
            $labels[] = substr($resumo['nome_mes'], 0, 3);
            $dataPF[] = $resumo['receita_pf'];
            $dataPJ[] = $resumo['receita_pj'];
            $dataFinanceira[] = $resumo['receita_financeira'];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Receita PF',
                    'data' => $dataPF,
                    'backgroundColor' => 'rgba(40, 167, 69, 0.7)',
                    'borderColor' => 'rgb(40, 167, 69)',
                ],
                [
                    'label' => 'Receita PJ',
                    'data' => $dataPJ,
                    'backgroundColor' => 'rgba(0, 123, 255, 0.7)',
                    'borderColor' => 'rgb(0, 123, 255)',
                ],
                [
                    'label' => 'Receita Financeira',
                    'data' => $dataFinanceira,
                    'backgroundColor' => 'rgba(23, 162, 184, 0.7)',
                    'borderColor' => 'rgb(23, 162, 184)',
                ],
            ]
        ];
    }
}
