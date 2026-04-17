<?php
// ESTÁVEL desde 17/04/2026

namespace App\Services\RelatorioCeo;

use App\Services\FinanceiroCalculatorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceiroCollector
{
    public function __construct(private FinanceiroCalculatorService $calc) {}

    public function coletar(Carbon $inicio, Carbon $fim): array
    {
        $mesAtual  = (int) $fim->format('m');
        $anoAtual  = (int) $fim->format('Y');
        $mesAnt    = (int) $inicio->copy()->subMonth()->format('m');
        $anoAnt    = (int) $inicio->copy()->subMonth()->format('Y');

        $dreAtual     = $this->calc->dre($anoAtual, $mesAtual);
        $dreAnterior  = $this->calc->dre($anoAnt, $mesAnt);
        $inadimplencia = $this->calc->inadimplencia();
        $classificacoes = $this->calc->classificacoes($anoAtual, $mesAtual);

        $variacaoReceita = $dreAnterior['receita_total'] > 0
            ? round((($dreAtual['receita_total'] - $dreAnterior['receita_total']) / $dreAnterior['receita_total']) * 100, 2)
            : 0;

        // Histórico 6 meses para gráfico
        $historico = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = $fim->copy()->subMonths($i);
            $dre = $this->calc->dre((int)$d->format('Y'), (int)$d->format('m'));
            $historico[] = [
                'mes'            => $d->format('m/Y'),
                'receita_total'  => $dre['receita_total'],
                'despesas'       => $dre['despesas'],
                'resultado'      => $dre['resultado'],
            ];
        }

        // Top classificações de despesas
        $topDespesas = collect($classificacoes)
            ->filter(fn($c) => str_starts_with(strtolower($c['classificacao']), 'despesa') || str_starts_with(strtolower($c['classificacao']), 'custo'))
            ->sortByDesc('total')
            ->take(5)
            ->values()
            ->toArray();

        // Top clientes por receita no mês (movimentos.pessoa)
        $topClientesRaw = DB::table('movimentos')
            ->where('mes', $mesAtual)
            ->where('ano', $anoAtual)
            ->whereIn('classificacao', ['RECEITA_PF', 'RECEITA_PJ', 'RECEITA_FINANCEIRA', 'OUTRAS_RECEITAS'])
            ->whereNotNull('pessoa')
            ->where('pessoa', '!=', '')
            ->select('pessoa', DB::raw('SUM(ABS(valor)) as receita_total'), DB::raw('COUNT(*) as transacoes'))
            ->groupBy('pessoa')
            ->orderByDesc('receita_total')
            ->take(10)
            ->get()
            ->toArray();

        $receitaTotal = $dreAtual['receita_total'] ?: 1;
        $topClientesReceita = array_map(fn($c) => [
            'cliente'             => ((array)$c)['pessoa'],
            'receita_total'       => round(((array)$c)['receita_total'], 2),
            'percentual_receita'  => round(((array)$c)['receita_total'] / $receitaTotal * 100, 1),
            'transacoes'          => ((array)$c)['transacoes'],
        ], $topClientesRaw);

        // Top devedores (inadimplentes por cliente)
        $topDevedoresRaw = DB::table('contas_receber')
            ->where('status', 'Não lançado')
            ->whereNotNull('data_vencimento')
            ->where('data_vencimento', '<', now())
            ->whereNotNull('cliente')
            ->where('cliente', '!=', '')
            ->select('cliente', DB::raw('SUM(ABS(valor)) as valor_devido'), DB::raw('COUNT(*) as titulos'))
            ->groupBy('cliente')
            ->orderByDesc('valor_devido')
            ->take(8)
            ->get()
            ->toArray();

        $topDevedores = array_map(fn($d) => [
            'cliente'      => ((array)$d)['cliente'],
            'valor_devido' => round(((array)$d)['valor_devido'], 2),
            'titulos'      => ((array)$d)['titulos'],
        ], $topDevedoresRaw);

        return [
            'periodo'              => "{$inicio->format('d/m/Y')} a {$fim->format('d/m/Y')}",
            'mes_referencia'       => $fim->format('F/Y'),
            'dre_atual'            => $dreAtual,
            'dre_anterior'         => $dreAnterior,
            'variacao_receita_pct' => $variacaoReceita,
            'inadimplencia'        => $inadimplencia,
            'historico_6meses'     => $historico,
            'top_despesas'         => $topDespesas,
            'top_clientes_receita' => $topClientesReceita,
            'top_devedores'        => $topDevedores,
        ];
    }
}
