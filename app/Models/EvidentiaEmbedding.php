<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidentiaEmbedding extends Model
{
    protected $table = 'evidentia_embeddings';

    protected $fillable = [
        'chunk_id',
        'model',
        'dims',
        'vector_json',
        'norm',
    ];

    protected $casts = [
        'vector_json' => 'array',
        'norm'        => 'double',
        'dims'        => 'integer',
    ];

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(EvidentiaChunk::class, 'chunk_id');
    }

    /**
     * Retorna o vetor como array de floats (cached na instância).
     */
    public function getVector(): array
    {
        return $this->vector_json;
    }

    /**
     * Calcula similaridade coseno com outro vetor.
     */
    public function cosineSimilarity(array $queryVector, float $queryNorm): float
    {
        $vector = $this->getVector();
        $dot = 0.0;
        $count = min(count($vector), count($queryVector));

        for ($i = 0; $i < $count; $i++) {
            $dot += $vector[$i] * $queryVector[$i];
        }

        $denominator = $this->norm * $queryNorm;
        if ($denominator == 0) {
            return 0.0;
        }

        return $dot / $denominator;
    }
}
