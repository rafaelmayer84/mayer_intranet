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
        $totalChunks = 0;
        $byTribunal = [];
        $embDbs = config('evidentia.embedding_databases', []);
        $countedConns = [];
        foreach ($embDbs as $trib => $conn) {
            if ($trib === 'default' || in_array($conn, $countedConns)) continue;
            $countedConns[] = $conn;
            try {
                $rows = \DB::connection($conn)->table('evidentia_chunks')
                    ->select('tribunal', \DB::raw('count(*) as total'))
                    ->groupBy('tribunal')->get();
                foreach ($rows as $r) {
                    $byTribunal[$r->tribunal] = ($byTribunal[$r->tribunal] ?? 0) + $r->total;
                    $totalChunks += $r->total;
                }
            } catch (\Exception $e) {}
        }
        $defConn = $embDbs['default'] ?? 'evidentia';
        if (!in_array($defConn, $countedConns)) {
            try {
                $rows = \DB::connection($defConn)->table('evidentia_chunks')
                    ->select('tribunal', \DB::raw('count(*) as total'))
                    ->groupBy('tribunal')->get();
                foreach ($rows as $r) {
                    $byTribunal[$r->tribunal] = ($byTribunal[$r->tribunal] ?? 0) + $r->total;
                    $totalChunks += $r->total;
                }
            } catch (\Exception $e) {}
        }
        $this->line("  Total: {$totalChunks}");
        foreach ($byTribunal as $trib => $count) {
            $this->line("  {$trib}: {$count}");
        }

        $this->newLine();
        $this->info('EMBEDDINGS:');
        // Count embeddings across all shards
        $totalEmb = 0;
        $pendingEmb = 0;
        $embDatabases = config('evidentia.embedding_databases', []);
        $checkedConns = [];
        foreach ($embDatabases as $trib => $conn) {
            if ($trib === 'default' || in_array($conn, $checkedConns)) continue;
            $checkedConns[] = $conn;
            try {
                $totalEmb += \DB::connection($conn)->table('evidentia_embeddings')->count();
                $pendingEmb += \DB::connection($conn)->table('evidentia_chunks as c')
                    ->leftJoin('evidentia_embeddings as e', 'e.chunk_id', '=', 'c.id')
                    ->whereNull('e.id')->count();
            } catch (\Exception $e) {}
        }
        // Also count default/evidentia for TRF4/TRT12
        $defaultConn = $embDatabases['default'] ?? 'evidentia';
        if (!in_array($defaultConn, $checkedConns)) {
            try {
                $totalEmb += \DB::connection($defaultConn)->table('evidentia_embeddings')->count();
                $pendingEmb += \DB::connection($defaultConn)->table('evidentia_chunks as c')
                    ->leftJoin('evidentia_embeddings as e', 'e.chunk_id', '=', 'c.id')
                    ->whereNull('e.id')->count();
            } catch (\Exception $e) {}
        }
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
