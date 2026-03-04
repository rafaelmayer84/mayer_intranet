<?php

namespace App\Console\Commands\Evidentia;

use App\Jobs\Evidentia\GenerateEmbeddingsJob;
use App\Models\EvidentiaChunk;
use Illuminate\Console\Command;

class EvidentiaEmbedCommand extends Command
{
    protected $signature = 'evidentia:embed
        {--tribunal= : Tribunal específico}
        {--limit=0 : Limitar quantidade de chunks (0 = todos pendentes)}
        {--sync : Executar de forma síncrona em vez de jobs}';

    protected $description = 'Gera embeddings OpenAI para chunks pendentes do EVIDENTIA';

    public function handle(): int
    {
        $tribunal = $this->option('tribunal');
        $limit    = (int) $this->option('limit');
        $sync     = $this->option('sync');
        $batchSize = config('evidentia.embed_batch_size', 20);

        $query = EvidentiaChunk::whereDoesntHave('embedding');

        if ($tribunal) {
            $query->where('tribunal', strtoupper($tribunal));
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $pendingIds = $query->pluck('id')->toArray();
        $total = count($pendingIds);

        if ($total === 0) {
            $this->info('Nenhum chunk pendente de embedding.');
            return self::SUCCESS;
        }

        $this->info("Chunks pendentes: {$total}");

        if ($sync) {
            $this->info('Modo síncrono - processando...');
            $bar = $this->output->createProgressBar(ceil($total / $batchSize));

            foreach (array_chunk($pendingIds, $batchSize) as $batch) {
                GenerateEmbeddingsJob::dispatchSync($batch);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        } else {
            $dispatched = 0;
            foreach (array_chunk($pendingIds, $batchSize) as $batch) {
                GenerateEmbeddingsJob::dispatch($batch);
                $dispatched++;
            }
            $this->info("{$dispatched} jobs disparados na fila '" . config('evidentia.embed_queue') . "'");
        }

        $this->info('Concluído.');
        return self::SUCCESS;
    }
}
