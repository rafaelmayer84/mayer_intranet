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
        $chunks = EvidentiaChunk::whereIn('id', $this->chunkIds)
            ->whereDoesntHave('embedding')
            ->get();

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

                EvidentiaEmbedding::create([
                    'chunk_id'    => $chunk->id,
                    'model'       => $model,
                    'dims'        => $dims,
                    'vector_json' => $emb['vector'],
                    'norm'        => $emb['norm'],
                ]);
            }

            Log::info('Evidentia: embeddings gerados', [
                'batch_size' => $batch->count(),
                'tokens'     => $result['tokens'],
                'cost'       => $result['cost'],
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Evidentia: GenerateEmbeddingsJob falhou', [
            'chunkIds' => $this->chunkIds,
            'error'    => $exception->getMessage(),
        ]);
    }
}
