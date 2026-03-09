<?php

namespace App\Console\Commands\Evidentia;

use App\Jobs\Evidentia\GenerateEmbeddingsJob;
use App\Models\EvidentiaChunk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EvidentiaEmbedCommand extends Command
{
    protected $signature = 'evidentia:embed
        {--tribunal= : Tribunal específico}
        {--limit=0 : Limitar quantidade de chunks (0 = todos pendentes)}
        {--sync : Executar de forma síncrona em vez de jobs}';

    protected $description = 'Gera embeddings OpenAI para chunks pendentes do EVIDENTIA (sharded)';

    public function handle(): int
    {
        $tribunal = $this->option('tribunal');
        $limit    = (int) $this->option('limit');
        $sync     = $this->option('sync');
        $batchSize = config('evidentia.embed_batch_size', 20);

        // Determina quais tribunais processar
        $tribunals = $tribunal
            ? [strtoupper($tribunal)]
            : array_keys(config('evidentia.tribunal_databases', []));

        $totalPending = 0;
        $allPendingIds = [];

        foreach ($tribunals as $trib) {
            $conn = EvidentiaChunk::connectionForTribunal($trib);

            // Busca chunks sem embedding no banco shardado
            $pendingQuery = DB::connection($conn)->table('evidentia_chunks as c')
                ->leftJoin('evidentia_embeddings as e', 'e.chunk_id', '=', 'c.id')
                ->where('c.tribunal', $trib)
                ->whereNull('e.id')
                ->select('c.id');

            if ($limit > 0) {
                $remaining = $limit - $totalPending;
                if ($remaining <= 0) break;
                $pendingQuery->limit($remaining);
            }

            $ids = $pendingQuery->pluck('c.id')->toArray();
            $totalPending += count($ids);
            $allPendingIds = array_merge($allPendingIds, $ids);

            if (count($ids) > 0) {
                $this->info("{$trib} ({$conn}): " . count($ids) . " chunks pendentes");
            }
        }

        if ($totalPending === 0) {
            $this->info('Nenhum chunk pendente de embedding.');
            return self::SUCCESS;
        }

        $this->info("Total chunks pendentes: {$totalPending}");

        if ($sync) {
            $this->info('Modo síncrono - processando...');
            $bar = $this->output->createProgressBar(ceil($totalPending / $batchSize));

            foreach (array_chunk($allPendingIds, $batchSize) as $batch) {
                GenerateEmbeddingsJob::dispatchSync($batch);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        } else {
            $dispatched = 0;
            foreach (array_chunk($allPendingIds, $batchSize) as $batch) {
                GenerateEmbeddingsJob::dispatch($batch);
                $dispatched++;
            }
            $this->info("{$dispatched} jobs disparados na fila '" . config('evidentia.embed_queue') . "'");
        }

        $this->info('Concluído.');
        return self::SUCCESS;
    }
}
