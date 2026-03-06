<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidentiaCitationBlock extends Model
{
    protected $connection = 'evidentia';
    protected $table = 'evidentia_citation_blocks';

    protected $fillable = [
        'search_id',
        'user_id',
        'sintese_objetiva',
        'bloco_precedentes',
        'jurisprudence_ids_used',
        'tokens_in',
        'tokens_out',
        'cost_usd',
    ];

    protected $casts = [
        'jurisprudence_ids_used' => 'array',
        'tokens_in'              => 'integer',
        'tokens_out'             => 'integer',
        'cost_usd'               => 'decimal:6',
    ];

    public function search(): BelongsTo
    {
        return $this->belongsTo(EvidentiaSearch::class, 'search_id');
    }
}
