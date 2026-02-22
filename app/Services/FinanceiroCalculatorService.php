<?php

namespace App\Services;

use App\Models\Movimento;
use App\Models\ContaReceber;

/**
 * Fonte única de cálculo financeiro (DRE).
 * Todos os módulos (Dashboard, BSC, Auditoria) devem usar este serviço.
 */
class FinanceiroCalculatorService
{
    /**
     * Retorna o DRE completo de uma competência.
     */
    public function dre(int $ano, int $mes): array
    {
        $receitaPf  = $this->sum($ano, $mes, ['RECEITA_PF']);
        $receitaPj  = $this->sum($ano, $mes, ['RECEITA_PJ']);
        $receitaFin = $this->sum($ano, $mes, ['RECEITA_FINANCEIRA', 'OUTRAS_RECEITAS']);
        $receitaTotal = $receitaPf + $receitaPj + $receitaFin;

        $deducoes = $this->sum($ano, $mes, ['DEDUCAO_RECEITA']);
        $despesas = $this->sumLike($ano, $mes, 'DESPESA%');

        $resultado = $receitaTotal - $deducoes - $despesas;

        return [
            'receita_pf'    => $receitaPf,
            'receita_pj'    => $receitaPj,
            'receita_fin'   => $receitaFin,
            'receita_total' => $receitaTotal,
            'deducoes'      => $deducoes,
            'despesas'      => $despesas,
            'resultado'     => $resultado,
        ];
    }

    /**
     * Soma absoluta por classificações exatas.
     */
    public function sum(int $ano, int $mes, array $classificacoes): float
    {
        return (float) abs(
            Movimento::where('ano', $ano)
                ->where('mes', $mes)
                ->whereIn('classificacao', $classificacoes)
                ->sum('valor')
        );
    }

    /**
     * Soma absoluta por classificação LIKE.
     */
    public function sumLike(int $ano, int $mes, string $pattern): float
    {
        return (float) abs(
            Movimento::where('ano', $ano)
                ->where('mes', $mes)
                ->where('classificacao', 'LIKE', $pattern)
                ->sum('valor')
        );
    }

    /**
     * Inadimplência: títulos vencidos em ContaReceber.
     */
    public function inadimplencia(): array
    {
        $query = ContaReceber::where('status', 'Não lançado')
            ->whereNotNull('data_vencimento')
            ->where('data_vencimento', '<', now());

        return [
            'qtd'   => (clone $query)->count(),
            'valor' => (float) abs((clone $query)->sum('valor')),
        ];
    }

    /**
     * Classificações presentes num período.
     */
    public function classificacoes(int $ano, int $mes): array
    {
        return Movimento::where('ano', $ano)
            ->where('mes', $mes)
            ->select('classificacao')
            ->selectRaw('COUNT(*) as qtd')
            ->selectRaw('ABS(SUM(valor)) as total')
            ->groupBy('classificacao')
            ->orderBy('classificacao')
            ->get()
            ->toArray();
    }
}
