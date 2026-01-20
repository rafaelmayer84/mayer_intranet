<?php

namespace App\Services;

use App\Models\Advogado;
use App\Models\Processo;
use App\Models\Atividade;
use App\Models\ContaReceber;
use App\Models\HoraTrabalhada;
use App\Models\Movimento;
use App\Models\Configuracao;
use Illuminate\Support\Facades\DB;

class KpiService
{
    /**
     * Obtém KPIs financeiros gerais do escritório
     * Agora usa a tabela movimentos para dados de pagamento efetivo com classificação PF/PJ
     */
    public function getKpisFinanceiros(): array
    {
        $ano = Configuracao::get('ano_filtro', 2025);
        
        // Faturamento total de receitas (Créditos)
        $faturamentoTotal = Movimento::where('tipo', 'Crédito')
            ->whereYear('data', $ano)
            ->sum('valor');
        
        // Faturamento PF
        $faturamentoPF = Movimento::receitaPF()
            ->ano($ano)
            ->sum('valor');
        
        // Faturamento PJ
        $faturamentoPJ = Movimento::receitaPJ()
            ->ano($ano)
            ->sum('valor');
        
        // Despesas totais (Débitos)
        $despesasTotal = Movimento::despesas()
            ->ano($ano)
            ->sum('valor');
        
        // Lucro líquido
        $lucroLiquido = $faturamentoTotal - $despesasTotal;
        
        // Percentuais PF/PJ
        $percentualPF = $faturamentoTotal > 0 ? ($faturamentoPF / $faturamentoTotal) * 100 : 0;
        $percentualPJ = $faturamentoTotal > 0 ? ($faturamentoPJ / $faturamentoTotal) * 100 : 0;
        
        // Faturamento por mês (para gráfico)
        $faturamentoPorMes = Movimento::where('tipo', 'Crédito')
            ->whereYear('data', $ano)
            ->select(
                DB::raw('MONTH(data) as mes'),
                DB::raw('SUM(valor) as total'),
                DB::raw('SUM(CASE WHEN classificacao = "PF" THEN valor ELSE 0 END) as total_pf'),
                DB::raw('SUM(CASE WHEN classificacao = "PJ" THEN valor ELSE 0 END) as total_pj')
            )
            ->groupBy(DB::raw('MONTH(data)'))
            ->orderBy('mes')
            ->get()
            ->keyBy('mes')
            ->toArray();
        
        // Preencher meses vazios
        $meses = [];
        for ($i = 1; $i <= 12; $i++) {
            $meses[$i] = [
                'mes' => $i,
                'total' => $faturamentoPorMes[$i]['total'] ?? 0,
                'total_pf' => $faturamentoPorMes[$i]['total_pf'] ?? 0,
                'total_pj' => $faturamentoPorMes[$i]['total_pj'] ?? 0,
            ];
        }
        
        // Contas a receber pendentes (do módulo ContaReceber)
        $contasPendentes = ContaReceber::whereIn('status', ['Pendente', 'Vencido', 'Não iniciado'])
            ->whereYear('data_vencimento', $ano)
            ->sum('valor');
        
        // Contas vencidas
        $contasVencidas = ContaReceber::where('status', 'Vencido')
            ->orWhere(function($q) {
                $q->whereIn('status', ['Pendente', 'Não iniciado'])
                  ->where('data_vencimento', '<', now());
            })
            ->whereYear('data_vencimento', $ano)
            ->sum('valor');
        
        return [
            'faturamento_total' => $faturamentoTotal,
            'faturamento_pf' => $faturamentoPF,
            'faturamento_pj' => $faturamentoPJ,
            'percentual_pf' => round($percentualPF, 1),
            'percentual_pj' => round($percentualPJ, 1),
            'despesas_total' => $despesasTotal,
            'lucro_liquido' => $lucroLiquido,
            'contas_pendentes' => $contasPendentes,
            'contas_vencidas' => $contasVencidas,
            'faturamento_por_mes' => array_values($meses),
            'ano' => $ano,
        ];
    }

    /**
     * Obtém KPIs de processos
     */
    public function getKpisProcessos(): array
    {
        $ano = Configuracao::get('ano_filtro', 2025);
        
        $processosAtivos = Processo::where('status', '!=', 'Concluído')
            ->whereNull('data_encerrado')
            ->count();
        
        $processosConcluidos = Processo::where('status', 'Concluído')
            ->whereYear('data_encerrado', $ano)
            ->count();
        
        $processosNovos = Processo::whereYear('data_cadastro', $ano)->count();
        
        $valorCausaTotal = Processo::where('status', '!=', 'Concluído')
            ->sum('valor_causa');
        
        // Processos por status
        $porStatus = Processo::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status')
            ->toArray();
        
        // Processos por advogado
        $porAdvogado = Processo::select('advogado_nome', DB::raw('COUNT(*) as total'))
            ->whereNotNull('advogado_nome')
            ->groupBy('advogado_nome')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->toArray();
        
        return [
            'ativos' => $processosAtivos,
            'concluidos' => $processosConcluidos,
            'novos' => $processosNovos,
            'valor_causa_total' => $valorCausaTotal,
            'por_status' => $porStatus,
            'por_advogado' => $porAdvogado,
        ];
    }

    /**
     * Obtém KPIs de atividades
     */
    public function getKpisAtividades(): array
    {
        $ano = Configuracao::get('ano_filtro', 2025);
        
        $atividadesPendentes = Atividade::whereIn('status', ['Não iniciado', 'Em andamento'])
            ->count();
        
        $atividadesConcluidas = Atividade::where('status', 'Concluído')
            ->whereYear('data_conclusao', $ano)
            ->count();
        
        $atividadesVencidas = Atividade::whereIn('status', ['Não iniciado', 'Em andamento'])
            ->where('data_vencimento', '<', now())
            ->count();
        
        // Por responsável
        $porResponsavel = Atividade::select(
                'responsavel_nome',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "Concluído" THEN 1 ELSE 0 END) as concluidas')
            )
            ->whereNotNull('responsavel_nome')
            ->groupBy('responsavel_nome')
            ->orderByDesc('total')
            ->get()
            ->toArray();
        
        return [
            'pendentes' => $atividadesPendentes,
            'concluidas' => $atividadesConcluidas,
            'vencidas' => $atividadesVencidas,
            'por_responsavel' => $porResponsavel,
        ];
    }

    /**
     * Obtém KPIs de horas trabalhadas
     */
    public function getKpisHoras(): array
    {
        $ano = Configuracao::get('ano_filtro', 2025);
        
        $totalHoras = HoraTrabalhada::whereYear('data_lancamento', $ano)
            ->sum('horas');
        
        $valorTotal = HoraTrabalhada::whereYear('data_lancamento', $ano)
            ->sum('valor_total');
        
        // Por advogado
        $porAdvogado = HoraTrabalhada::select(
                'responsavel_nome',
                DB::raw('SUM(horas) as total_horas'),
                DB::raw('SUM(valor_total) as valor_total')
            )
            ->whereYear('data_lancamento', $ano)
            ->whereNotNull('responsavel_nome')
            ->groupBy('responsavel_nome')
            ->orderByDesc('total_horas')
            ->get()
            ->toArray();
        
        return [
            'total_horas' => round($totalHoras, 2),
            'valor_total' => $valorTotal,
            'por_advogado' => $porAdvogado,
        ];
    }

    /**
     * Obtém KPIs de um advogado específico
     * @param int $advogadoId - ID interno do advogado (não datajuri_id)
     */
    public function getKpisAdvogado(int $advogadoId): array
    {
        $ano = Configuracao::get('ano_filtro', 2025);
        
        // Faturamento do advogado (usando ContaReceber por enquanto)
        $faturamento = ContaReceber::where('status', 'Concluído')
            ->where('responsavel_id', $advogadoId)
            ->whereYear('data_vencimento', $ano)
            ->sum('valor');
        
        // Processos ativos
        $processosAtivos = Processo::where('advogado_id', $advogadoId)
            ->where('status', '!=', 'Concluído')
            ->count();
        
        // Total de processos
        $processosTotal = Processo::where('advogado_id', $advogadoId)->count();
        
        // Atividades
        $atividadesConcluidas = Atividade::where('responsavel_id', $advogadoId)
            ->where('status', 'Concluído')
            ->count();
        
        $atividadesPendentes = Atividade::where('responsavel_id', $advogadoId)
            ->whereIn('status', ['Não iniciado', 'Em andamento'])
            ->count();
        
        // Horas
        $horas = HoraTrabalhada::where('responsavel_id', $advogadoId)
            ->whereYear('data_lancamento', $ano)
            ->sum('horas');
        
        $valorHoras = HoraTrabalhada::where('responsavel_id', $advogadoId)
            ->whereYear('data_lancamento', $ano)
            ->sum('valor_total');
        
        // Metas (valores padrão, podem ser configurados)
        $metaFaturamento = Configuracao::get('meta_faturamento_' . $advogadoId, 100000);
        $metaHoras = Configuracao::get('meta_horas_' . $advogadoId, 1200);
        $metaProcessos = Configuracao::get('meta_processos_' . $advogadoId, 50);
        
        // Progresso das metas
        $progressoFaturamento = $metaFaturamento > 0 ? ($faturamento / $metaFaturamento) * 100 : 0;
        $progressoHoras = $metaHoras > 0 ? ($horas / $metaHoras) * 100 : 0;
        $progressoProcessos = $metaProcessos > 0 ? ($processosTotal / $metaProcessos) * 100 : 0;
        
        // Score BSC simplificado
        $bscFinanceiro = min(100, $progressoFaturamento);
        $bscProcessos = min(100, $progressoProcessos);
        $bscProdutividade = min(100, $progressoHoras);
        $bscQualidade = 100; // Placeholder - pode ser calculado com base em outros critérios
        
        $scoreBsc = ($bscFinanceiro * 0.4) + ($bscProcessos * 0.2) + ($bscProdutividade * 0.2) + ($bscQualidade * 0.2);
        
        return [
            'faturamento' => $faturamento,
            'processos_ativos' => $processosAtivos,
            'processos_total' => $processosTotal,
            'atividades_concluidas' => $atividadesConcluidas,
            'atividades_pendentes' => $atividadesPendentes,
            'horas_trabalhadas' => round($horas, 2),
            'valor_horas' => $valorHoras,
            'progresso_faturamento' => round($progressoFaturamento, 1),
            'progresso_horas' => round($progressoHoras, 1),
            'progresso_processos' => round($progressoProcessos, 1),
            'bsc_financeiro' => round($bscFinanceiro, 0),
            'bsc_processos' => round($bscProcessos, 0),
            'bsc_produtividade' => round($bscProdutividade, 0),
            'bsc_qualidade' => round($bscQualidade, 0),
            'score_bsc' => round($scoreBsc, 0),
        ];
    }

    public function getRankingAdvogados(): array
    {
        $ano = Configuracao::get('ano_filtro', 2025);
        $advogados = Advogado::where('ativo', true)->get();
        
        $ranking = [];
        foreach ($advogados as $advogado) {
            // Usar o ID interno ao invés de datajuri_id
            $kpis = $this->getKpisAdvogado($advogado->id);
            
            // Score simples baseado em faturamento
            $score = min(100, ($kpis['faturamento'] / 100000) * 100);
            
            $ranking[] = [
                'id' => $advogado->id,
                'nome' => $advogado->nome,
                'faturamento' => $kpis['faturamento'],
                'processos' => $kpis['processos_ativos'],
                'atividades' => $kpis['atividades_concluidas'],
                'horas' => $kpis['horas_trabalhadas'],
                'score' => round($score, 0),
            ];
        }
        
        // Ordenar por faturamento
        usort($ranking, fn($a, $b) => $b['faturamento'] <=> $a['faturamento']);
        
        return $ranking;
    }
}
