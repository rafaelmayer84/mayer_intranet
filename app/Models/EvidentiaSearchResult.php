<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidentiaSearchResult extends Model
{
    protected $table = 'evidentia_search_results';

    protected $fillable = [
        'search_id',
        'jurisprudence_id',
        'tribunal',
        'source_db',
        'score_text',
        'score_semantic',
        'score_rerank',
        'final_score',
        'highlights_json',
        'rerank_justification',
        'final_rank',
    ];

    protected $casts = [
        'highlights_json' => 'array',
        'score_text'      => 'double',
        'score_semantic'  => 'double',
        'score_rerank'    => 'double',
        'final_score'     => 'double',
        'final_rank'      => 'integer',
    ];

    public function search(): BelongsTo
    {
        return $this->belongsTo(EvidentiaSearch::class, 'search_id');
    }

    /**
     * Carrega jurisprudência do banco de origem (cross-database).
     */
    public function getJurisprudence(): ?object
    {
        $config = config("evidentia.tribunal_databases.{$this->tribunal}");
        if (!$config) {
            return null;
        }

        return \DB::connection($config['connection'])
            ->table($config['table'])
            ->where('id', $this->jurisprudence_id)
            ->first();
    }

    /**
     * Retorna highlights formatados para exibição.
     */
    public function getHighlights(): array
    {
        return $this->highlights_json ?? [];
    }
}
