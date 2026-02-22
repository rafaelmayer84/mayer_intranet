<?php

namespace App\Console\Commands;

use App\Models\Movimento;
use Illuminate\Console\Command;

class AuditFinance extends Command
{
    protected $signature = 'resultados:audit-finance
                            {--ano= : Ano (default: atual)}
                            {--mes= : Mes (default: atual)}';

    protected $description = 'Auditoria financeira: valida receitas, deducoes, despesas, resultado e inadimplencia';

    public function handle(): int
    {
        $ano = (int) ($this->option('ano') ?: date('Y'));
        $mes = (int) ($this->option('mes') ?: date('m'));

        $this->info("=== AUDITORIA FINANCEIRA {$mes}/{$ano} ===");
        $this->newLine();

        // Classificacoes usadas
        $classificacoes = Movimento::where('ano', $ano)->where('mes', $mes)
            ->select('classificacao')
            ->distinct()
            ->orderBy('classificacao')
            ->pluck('classificacao')
            ->toArray();

        // Receitas
        $receitaPf = (float) abs(Movimento::where('ano', $ano)->where('mes', $mes)
            ->whereIn('classificacao', ['RECEITA_PF'])->sum('valor'));
        $receitaPj = (float) abs(Movimento::where('ano', $ano)->where('mes', $mes)
            ->whereIn('classificacao', ['RECEITA_PJ'])->sum('valor'));
        $receitaFin = (float) abs(Movimento::where('ano', $ano)->where('mes', $mes)
            ->whereIn('classificacao', ['RECEITA_FINANCEIRA', 'OUTRAS_RECEITAS'])->sum('valor'));
        $receitaTotal = $receitaPf + $receitaPj + $receitaFin;

        // Deducoes
        $deducoes = (float) abs(Movimento::where('ano', $ano)->where('mes', $mes)
            ->whereIn('classificacao', ['DEDUCAO_RECEITA'])->sum('valor'));

        // Despesas
        $despesas = (float) abs(Movimento::where('ano', $ano)->where('mes', $mes)
            ->where('classificacao', 'LIKE', 'DESPESA%')->sum('valor'));

        // Resultado DRE
        $resultado = $receitaTotal - $deducoes - $despesas;

        // Inadimplencia (ContaReceber, nao Movimento)
        $inadTotal = \App\Models\ContaReceber::where('status', 'Não lançado')
            ->whereNotNull('data_vencimento')
            ->where('data_vencimento', '<', now())
            ->count();
        $inadValor = (float) abs(\App\Models\ContaReceber::where('status', 'Não lançado')
            ->whereNotNull('data_vencimento')
            ->where('data_vencimento', '<', now())
            ->sum('valor'));

        // Totais de movimentos
        $totalMovs = Movimento::where('ano', $ano)->where('mes', $mes)->count();

        // Output
        $this->table(['Indicador', 'Valor'], [
            ['Receita PF', 'R$ ' . number_format($receitaPf, 2, ',', '.')],
            ['Receita PJ', 'R$ ' . number_format($receitaPj, 2, ',', '.')],
            ['Receita Financeira', 'R$ ' . number_format($receitaFin, 2, ',', '.')],
            ['RECEITA TOTAL', 'R$ ' . number_format($receitaTotal, 2, ',', '.')],
            ['(-) Deducoes', 'R$ ' . number_format($deducoes, 2, ',', '.')],
            ['(-) Despesas', 'R$ ' . number_format($despesas, 2, ',', '.')],
            ['= RESULTADO', 'R$ ' . number_format($resultado, 2, ',', '.')],
            ['', ''],
            ['Inadimplencia (qtd)', $inadTotal . ' movimentos'],
            ['Inadimplencia (valor)', 'R$ ' . number_format($inadValor, 2, ',', '.')],
            ['Total movimentos', $totalMovs],
        ]);

        $this->newLine();
        $this->info('Classificacoes encontradas no periodo:');
        foreach ($classificacoes as $cl) {
            $qtd = Movimento::where('ano', $ano)->where('mes', $mes)
                ->where('classificacao', $cl)->count();
            $val = (float) abs(Movimento::where('ano', $ano)->where('mes', $mes)
                ->where('classificacao', $cl)->sum('valor'));
            $this->line("  {$cl}: {$qtd} movs | R$ " . number_format($val, 2, ',', '.'));
        }

        $this->newLine();
        $this->info('Criterios de calculo:');
        $this->line('  Receita = RECEITA_PF + RECEITA_PJ + RECEITA_FINANCEIRA + OUTRAS_RECEITAS');
        $this->line('  Deducoes = DEDUCAO_RECEITA');
        $this->line('  Despesas = DESPESA%');
        $this->line('  Resultado = Receita - Deducoes - Despesas');
        $this->line('  Inadimplencia = ContaReceber status "Nao lancado" + vencimento < hoje');

        return self::SUCCESS;
    }
}
