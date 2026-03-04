<?php

namespace App\Console\Commands\Evidentia;

use App\Models\EvidentiaChunk;
use App\Models\EvidentiaEmbedding;
use App\Models\EvidentiaSearch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EvidentiaStatsCommand extends Command
{
    protected $signature = 'evidentia:stats';

    protected $description = 'Exibe estatísticas do sistema EVIDENTIA';

    public function handle(): int
    {
        $this->info('=== EVIDENTIA - Estatísticas ===');
        $this->newLine();

        // Acervo
        $this->info('ACERVO DE JURISPRUDÊNCIA:');
        foreach (config('evidentia.tribunal_databases') as $tribunal => $config) {
            try {
                $count = DB::connection($config['connection'])
                    ->table($config['table'])
                    ->where('tribunal', $tribunal)
                    ->count();
                $this->line("  {$tribunal}: {$count} acórdãos");
            } catch (\Exception $e) {
                $this->line("  {$tribunal}: ERRO ({$e->getMessage()})");
            }
        }

        $this->newLine();
        $this->info('CHUNKS:');
        $totalChunks = EvidentiaChunk::count();
        $this->line("  Total: {$totalChunks}");
        $byTribunal = EvidentiaChunk::select('tribunal', DB::raw('count(*) as total'))
            ->groupBy('tribunal')
            ->pluck('total', 'tribunal');
        foreach ($byTribunal as $trib => $count) {
            $this->line("  {$trib}: {$count}");
        }

        $this->newLine();
        $this->info('EMBEDDINGS:');
        $totalEmb = EvidentiaEmbedding::count();
        $pendingEmb = EvidentiaChunk::whereDoesntHave('embedding')->count();
        $this->line("  Gerados: {$totalEmb}");
        $this->line("  Pendentes: {$pendingEmb}");

        $this->newLine();
        $this->info('BUSCAS:');
        $totalSearches = EvidentiaSearch::count();
        $todaySearches = EvidentiaSearch::whereDate('created_at', today())->count();
        $this->line("  Total: {$totalSearches}");
        $this->line("  Hoje: {$todaySearches}");

        $this->newLine();
        $this->info('CUSTOS:');
        $todayBudget = (float) Cache::get('evidentia_budget_' . now()->toDateString(), 0);
        $dailyLimit  = config('evidentia.daily_budget_usd');
        $totalCost   = EvidentiaSearch::sum('cost_usd');
        $this->line("  Hoje: \${$todayBudget} / \${$dailyLimit} (limite)");
        $this->line("  Acumulado total: \$" . number_format((float) $totalCost, 4));

        return self::SUCCESS;
    }
}
