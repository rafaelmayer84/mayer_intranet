<?php

namespace App\Services\Justus;

use App\Models\JustusDocumentChunk;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JustusEmbeddingService
{
    private string $model = 'text-embedding-3-small';
    private int $dimensions = 1536;

    /**
     * Gera embedding para um texto via OpenAI API.
     */
    public function generateEmbedding(string $text): ?array
    {
        $apiKey = config('justus.openai_api_key');
        if (empty($apiKey)) return null;

        // Truncar texto para limite do modelo (~8191 tokens, ~32000 chars)
        $text = mb_substr($text, 0, 30000);

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => $this->model,
                    'input' => $text,
                    'dimensions' => $this->dimensions,
                ]);

            if (!$response->successful()) {
                Log::warning('JUSTUS Embedding: HTTP ' . $response->status());
                return null;
            }

            $data = $response->json();
            return $data['data'][0]['embedding'] ?? null;
        } catch (\Exception $e) {
            Log::warning('JUSTUS Embedding: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Gera e salva embedding para um chunk.
     */
    public function embedChunk(JustusDocumentChunk $chunk): bool
    {
        $embedding = $this->generateEmbedding($chunk->content);
        if (!$embedding) return false;

        $chunk->update([
            'embedding' => json_encode($embedding),
            'embedding_model' => $this->model,
        ]);
        return true;
    }

    /**
     * Gera embeddings para todos os chunks de um attachment.
     */
    public function embedAllChunks(int $attachmentId): int
    {
        $chunks = JustusDocumentChunk::where('attachment_id', $attachmentId)
            ->whereNull('embedding')
            ->get();

        $count = 0;
        foreach ($chunks as $chunk) {
            if ($this->embedChunk($chunk)) {
                $count++;
            }
            // Rate limit: ~3000 RPM para embeddings, mas ser conservador
            usleep(50000); // 50ms entre requests
        }

        Log::info("JUSTUS Embedding: {$count} chunks embeddados para attachment {$attachmentId}");
        return $count;
    }

    /**
     * Calcula similaridade coseno entre dois vetores.
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0, $len = count($a); $i < $len; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0 || $normB == 0) return 0.0;

        return $dotProduct / ($normA * $normB);
    }

    /**
     * Busca chunks mais similares semanticamente a uma query.
     * Retorna Collection de chunks ordenados por similaridade.
     */
    public function searchSimilarChunks(string $query, int $attachmentId, int $limit = 15, array $feedbackAdjustments = []): \Illuminate\Support\Collection
    {
        $queryEmbedding = $this->generateEmbedding($query);
        if (!$queryEmbedding) {
            return collect();
        }

        $chunks = JustusDocumentChunk::where('attachment_id', $attachmentId)
            ->whereNotNull('embedding')
            ->get();

        if ($chunks->isEmpty()) {
            return collect();
        }

        // Calcular similaridade para cada chunk
        $scored = $chunks->map(function ($chunk) use ($queryEmbedding, $feedbackAdjustments) {
            $chunkEmbedding = json_decode($chunk->embedding, true);
            if (!$chunkEmbedding) {
                $chunk->similarity = 0;
                return $chunk;
            }

            $similarity = $this->cosineSimilarity($queryEmbedding, $chunkEmbedding);

            // Aplicar ajuste de feedback se existir
            if (isset($feedbackAdjustments[$chunk->id])) {
                $similarity += $feedbackAdjustments[$chunk->id];
            }

            $chunk->similarity = $similarity;
            return $chunk;
        });

        // Ordenar por similaridade e retornar top N
        return $scored->sortByDesc('similarity')->take($limit)->values();
    }

    /**
     * Retorna o embedding da query (para armazenar no feedback).
     */
    public function getQueryEmbedding(string $query): ?string
    {
        $embedding = $this->generateEmbedding($query);
        return $embedding ? json_encode($embedding) : null;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getDimensions(): int
    {
        return $this->dimensions;
    }
}
