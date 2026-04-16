<?php

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

        return [
            'periodo'            => "{$inicio->format('d/m/Y')} a {$fim->format('d/m/Y')}",
            'mes_referencia'     => $fim->format('F/Y'),
            'dre_atual'          => $dreAtual,
            'dre_anterior'       => $dreAnterior,
            'variacao_receita_pct' => $variacaoReceita,
            'inadimplencia'      => $inadimplencia,
            'historico_6meses'   => $historico,
            'top_despesas'       => $topDespesas,
        ];
    }
}
