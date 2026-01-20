<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Movimento;
use Illuminate\Support\Facades\Log;

class FinanceiroBackfillClassificacao extends Command
{
    protected $signature = 'financeiro:backfill-classificacao {--year=} {--dry-run}';
    protected $description = 'Backfill seguro da coluna movimentos.classificacao com base em regras locais (plano de contas).';

    public function handle(): int
    {
        $year = $this->option('year') ? (int)$this->option('year') : null;
        $dryRun = (bool)$this->option('dry-run');

        $pf = config('plano_contas.receita_pf_prefix', []);
        $pj = config('plano_contas.receita_pj_prefix', []);
        $fin = config('plano_contas.receita_financeira_prefix', []);
        $desprezar = config('plano_contas.desprezar_prefix', []);

        $query = Movimento::query();
        if ($year) {
            $query->where('ano', $year);
        }

        $total = (clone $query)->count();
        $this->info("Total a avaliar: {$total}" . ($year ? " (ano {$year})" : "") . ($dryRun ? " [DRY-RUN]" : ""));

        $counts = [
            Movimento::RECEITA_PF => 0,
            Movimento::RECEITA_PJ => 0,
            Movimento::RECEITA_FINANCEIRA => 0,
            Movimento::DESPESA => 0,
            Movimento::PENDENTE_CLASSIFICACAO => 0,
        ];

        $amostraPendentes = [];

        $query->orderBy('id')->chunkById(500, function ($rows) use (&$counts, &$amostraPendentes, $dryRun, $pf, $pj, $fin, $desprezar) {
            foreach ($rows as $mov) {
                $novo = $this->classificar($mov, $pf, $pj, $fin, $desprezar);
                $counts[$novo]++;

                if ($novo === Movimento::PENDENTE_CLASSIFICACAO && count($amostraPendentes) < 20) {
                    $amostraPendentes[] = $mov->id;
                }

                if (!$dryRun && $mov->classificacao !== $novo) {
                    $mov->classificacao = $novo;
                    $mov->save();
                }
            }
        });

        $this->line("");
        $this->info("Resumo:");
        foreach ($counts as $k => $v) {
            $this->line(" - {$k}: {$v}");
        }
        if (count($amostraPendentes)) {
            $this->warn("Amostra de IDs pendentes (até 20): " . implode(',', $amostraPendentes));
        }

        Log::info('financeiro:backfill-classificacao', [
            'year' => $year,
            'dry_run' => $dryRun,
            'counts' => $counts,
            'sample_pendentes' => $amostraPendentes,
        ]);

        return self::SUCCESS;
    }

    private function classificar(Movimento $mov, array $pf, array $pj, array $fin, array $desprezar): string
    {
        $codigo = (string)($mov->codigo_plano ?? '');
        $plano = (string)($mov->plano_contas ?? '');
        $hay = $codigo !== '' ? $codigo : $plano;

        if ($hay === '') {
            return Movimento::PENDENTE_CLASSIFICACAO;
        }

        foreach ($desprezar as $p) {
            if (str_starts_with($hay, $p)) {
                return Movimento::PENDENTE_CLASSIFICACAO;
            }
        }

        foreach ($pf as $p) {
            if (str_starts_with($hay, $p)) return Movimento::RECEITA_PF;
        }
        foreach ($pj as $p) {
            if (str_starts_with($hay, $p)) return Movimento::RECEITA_PJ;
        }
        foreach ($fin as $p) {
            if (str_starts_with($hay, $p)) return Movimento::RECEITA_FINANCEIRA;
        }

        $valor = (float)$mov->valor;
        if ($valor < 0) return Movimento::DESPESA;

        return Movimento::PENDENTE_CLASSIFICACAO;
    }
}
