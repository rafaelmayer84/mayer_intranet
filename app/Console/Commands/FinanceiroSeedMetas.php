<?php

namespace App\Console\Commands;

use App\Models\Configuracao;
use Illuminate\Console\Command;

class FinanceiroSeedMetas extends Command
{
    protected $signature = 'financeiro:seed-metas {--year=} {--from-last-year}';

    protected $description = 'Cria/copias metas financeiras no padrão meta_{kpi}_{ano}_{mes} (compatível com o que o dashboard usa).';

    public function handle(): int
    {
        $year = (int) ($this->option('year') ?: date('Y'));
        $fromLast = (bool) $this->option('from-last-year');

        $kpisMensais = [
            'pf',
            'pj',
            'despesas',
            'resultado',
            'margem',
            'dias_atraso',
            'taxa_cobranca',
        ];

        $created = 0;
        $updated = 0;

        foreach ($kpisMensais as $kpi) {
            for ($mes = 1; $mes <= 12; $mes++) {
                $key = "meta_{$kpi}_{$year}_{$mes}";

                $value = 0;

                if ($fromLast) {
                    $prevKey = "meta_{$kpi}_" . ($year - 1) . "_{$mes}";
                    $prevVal = Configuracao::get($prevKey, null);

                    if ($prevVal !== null && $prevVal !== '') {
                        $value = (float) $prevVal;
                    }
                }

                $existing = Configuracao::query()->where('chave', $key)->first();

                if ($existing) {
                    $existing->valor = $value;
                    $existing->save();
                    $updated++;
                } else {
                    Configuracao::set($key, $value);
                    $created++;
                }
            }
        }

        $this->info("Metas processadas para {$year}.");
        $this->info("Criadas: {$created}");
        $this->info("Atualizadas: {$updated}");

        if ($fromLast) {
            $this->info("Fonte: ano anterior (" . ($year - 1) . ").");
        } else {
            $this->warn("Valores default 0. Use como placeholder e ajuste em /configurar-metas?ano={$year}.");
        }

        return self::SUCCESS;
    }
}
