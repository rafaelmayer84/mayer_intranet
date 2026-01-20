<?php

namespace App\Console\Commands;

use App\Models\Configuracao;
use App\Models\ContaReceber;
use App\Models\Movimento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FinanceiroSanityCheck extends Command
{
    protected $signature = 'financeiro:sanity-check';

    protected $description = 'Checagens rápidas de integridade (movimentos.classificacao, contas_receber, metas).';

    public function handle(): int
    {
        $movTotal = (int) Movimento::query()->count();
        $movClass = (int) Movimento::query()->whereNotNull('classificacao')->where('classificacao', '<>', '')->count();

        $this->info("Movimentos total: {$movTotal}");
        $this->info("Movimentos com classificacao: {$movClass}");

        $by = Movimento::query()
            ->selectRaw('classificacao, COUNT(*) as total')
            ->whereNotNull('classificacao')
            ->where('classificacao', '<>', '')
            ->groupBy('classificacao')
            ->orderByDesc('total')
            ->get();

        if ($by->isNotEmpty()) {
            $this->line("Distribuição por classificacao:");
            foreach ($by as $r) {
                $this->line("- {$r->classificacao}: {$r->total}");
            }
        }

        $crTotal = (int) ContaReceber::query()->count();
        $this->newLine();
        $this->info("Contas a Receber total: {$crTotal}");

        $metasCount = (int) Configuracao::query()->where('chave', 'like', 'meta_%')->count();
        $this->info("Metas (meta_*) cadastradas: {$metasCount}");

        if ($movTotal > 0 && $movClass === 0) {
            $this->warn("ATENÇÃO: movimentos sem classificacao -> KPIs PF/PJ tendem a ficar 0. Rode financeiro:backfill-classificacao.");
        }
        if ($crTotal === 0) {
            $this->warn("ATENÇÃO: contas_receber vazia -> KPIs de atraso/cobrança ficam 0. Rode /api/sync/contas-receber.");
        }
        if ($metasCount === 0) {
            $this->warn("ATENÇÃO: metas não cadastradas -> metas ficam 0. Rode financeiro:seed-metas ou preencha na UI.");
        }

        return self::SUCCESS;
    }
}
