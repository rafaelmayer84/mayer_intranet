<?php

namespace App\Console\Commands\Evidentia;

use App\Jobs\Evidentia\GenerateEmbeddingsJob;
use App\Models\EvidentiaChunk;
use App\Services\Evidentia\EvidentiaChunkService;
use Illuminate\Console\Command;

class EvidentiaChunkCommand extends Command
{
    protected $signature = 'evidentia:chunk
        {--tribunal= : Tribunal específico (TJSC, STJ, TRF4, TRT12). Omitir = todos}
        {--limit=500 : Registros por lote}
        {--offset=0 : Offset inicial}
        {--with-embeddings : Disparar jobs de embedding após chunking}';

    protected $description = 'Gera chunks das ementas de jurisprudência para busca semântica EVIDENTIA';

    public function handle(EvidentiaChunkService $chunkService): int
    {
        $tribunal = $this->option('tribunal');
        $limit    = (int) $this->option('limit');
        $offset   = (int) $this->option('offset');
        $withEmb  = $this->option('with-embeddings');

        $tribunais = $tribunal
            ? [strtoupper($tribunal)]
            : array_keys(config('evidentia.tribunal_databases'));

        $totalChunks = 0;
        $totalSkipped = 0;

        foreach ($tribunais as $trib) {
            $this->info("Processando {$trib}...");

            $currentOffset = $offset;
            $tribunalChunks = 0;

            do {
                $stats = $chunkService->processarLoteTribunal($trib, $limit, $currentOffset);

                if (isset($stats['error'])) {
                    $this->error("  Erro: {$stats['error']}");
                    break;
                }

                $tribunalChunks += $stats['chunks_created'];
                $totalSkipped   += $stats['skipped'];

                $this->line("  Offset {$currentOffset}: {$stats['chunks_created']} chunks criados, {$stats['skipped']} já existentes ({$stats['total']} processados)");

                $currentOffset += $limit;

                // Se retornou menos que o limit, acabou
                if ($stats['total'] < $limit) {
                    break;
                }
            } while (true);

            $totalChunks += $tribunalChunks;
            $this->info("  {$trib}: {$tribunalChunks} chunks totais criados");
        }

        $this->newLine();
        $this->info("TOTAL: {$totalChunks} chunks criados, {$totalSkipped} já existentes");

        // Dispara jobs de embedding se solicitado
        if ($withEmb && $totalChunks > 0) {
            $this->info('Disparando jobs de embedding...');
            $this->dispatchEmbeddingJobs();
        }

        return self::SUCCESS;
    }

    private function dispatchEmbeddingJobs(): void
    {
        $batchSize = config('evidentia.embed_batch_size', 20);

        $pendingIds = EvidentiaChunk::whereDoesntHave('embedding')
            ->pluck('id')
            ->toArray();

        $total = count($pendingIds);
        $dispatched = 0;

        foreach (array_chunk($pendingIds, $batchSize) as $batch) {
            GenerateEmbeddingsJob::dispatch($batch);
            $dispatched++;
        }

        $this->info("  {$dispatched} jobs disparados para {$total} chunks pendentes");
    }
}
