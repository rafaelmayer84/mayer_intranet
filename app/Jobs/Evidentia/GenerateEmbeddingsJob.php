<?php

namespace App\Jobs\Evidentia;

use App\Models\EvidentiaChunk;
use App\Models\EvidentiaEmbedding;
use App\Services\Evidentia\EvidentiaOpenAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateEmbeddingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout;
    public int $tries = 2;

    private array $chunkIds;

    public function __construct(array $chunkIds)
    {
        $this->chunkIds = $chunkIds;
        $this->queue = config('evidentia.embed_queue', 'evidentia');
        $this->timeout = config('evidentia.embed_queue_timeout', 120);
    }

    public function handle(EvidentiaOpenAIService $openai): void
    {
        // Carrega chunks da connection evidentia (onde ainda estão durante migração)
        // ou do banco shardado (após migração completa)
        $chunks = $this->loadChunks();

        if ($chunks->isEmpty()) {
            return;
        }

        $batchSize = config('evidentia.embed_batch_size', 20);
        $model     = config('evidentia.openai_embedding_model');
        $dims      = config('evidentia.openai_embedding_dims');

        foreach ($chunks->chunk($batchSize) as $batch) {
            $texts = $batch->pluck('chunk_text')->toArray();

            $result = $openai->generateEmbeddings($texts);

            if (!$result['success']) {
                Log::warning('Evidentia: embedding batch falhou', [
                    'error'    => $result['error'],
                    'chunkIds' => $batch->pluck('id')->toArray(),
                ]);
                continue;
            }

            foreach ($batch->values() as $index => $chunk) {
                if (!isset($result['embeddings'][$index])) {
                    continue;
                }

                $emb = $result['embeddings'][$index];
                $conn = EvidentiaEmbedding::connectionForTribunal($chunk->tribunal);

                DB::connection($conn)->table('evidentia_embeddings')->insert([
                    'chunk_id'   => $chunk->id,
                    'model'      => $model,
                    'dims'       => $dims,
                    'vector_bin' => EvidentiaEmbedding::vectorToBin($emb['vector']),
                    'norm'       => $emb['norm'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Log::info('Evidentia: embeddings gerados', [
                'batch_size' => $batch->count(),
                'tokens'     => $result['tokens'],
                'cost'       => $result['cost'],
            ]);
        }
    }

    /**
     * Carrega chunks pendentes de embedding.
     * Busca em todos os shards + evidentia original.
     */
    private function loadChunks(): \Illuminate\Support\Collection
    {
        $allChunks = collect();

        // Busca nos bancos shardados
        $embDatabases = config('evidentia.embedding_databases', []);
        $checkedConns = [];

        foreach ($embDatabases as $tribunal => $conn) {
            if ($tribunal === 'default' || in_array($conn, $checkedConns)) {
                continue;
            }
            $checkedConns[] = $conn;

            try {
                $chunks = EvidentiaChunk::on($conn)
                    ->whereIn('id', $this->chunkIds)
                    ->whereNotExists(function ($q) use ($conn) {
                        $q->select(DB::raw(1))
                          ->from(DB::connection($conn)->getDatabaseName() . '.evidentia_embeddings')
                          ->whereColumn('evidentia_embeddings.chunk_id', 'evidentia_chunks.id');
                    })
                    ->get();
                $allChunks = $allChunks->merge($chunks);
            } catch (\Exception $e) {
                // Shard may not have these chunks
            }
        }

        // Busca no banco evidentia original (default/fallback + chunks ainda não migrados)
        $defaultConn = $embDatabases['default'] ?? 'evidentia';
        if (!in_array($defaultConn, $checkedConns)) {
            $checkedConns[] = $defaultConn;
        }

        $remainingIds = array_diff($this->chunkIds, $allChunks->pluck('id')->toArray());
        if (!empty($remainingIds)) {
            try {
                $chunks = EvidentiaChunk::on('evidentia')
                    ->whereIn('id', $remainingIds)
                    ->whereDoesntHave('embedding')
                    ->get();
                $allChunks = $allChunks->merge($chunks);
            } catch (\Exception $e) {
                Log::warning('Evidentia: falha ao carregar chunks do evidentia', ['error' => $e->getMessage()]);
            }
        }

        return $allChunks;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Evidentia: GenerateEmbeddingsJob falhou', [
            'chunkIds' => $this->chunkIds,
            'error'    => $exception->getMessage(),
        ]);
    }
}
