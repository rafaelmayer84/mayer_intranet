<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EvidentiaSearch extends Model
{
    protected $table = 'evidentia_searches';

    protected $fillable = [
        'user_id',
        'query',
        'filters_json',
        'expanded_terms_json',
        'topk',
        'tokens_in',
        'tokens_out',
        'cost_usd',
        'latency_ms',
        'status',
        'error_message',
        'degraded_mode',
    ];

    protected $casts = [
        'filters_json'        => 'array',
        'expanded_terms_json' => 'array',
        'topk'                => 'integer',
        'tokens_in'           => 'integer',
        'tokens_out'          => 'integer',
        'cost_usd'            => 'decimal:6',
        'latency_ms'          => 'integer',
        'degraded_mode'       => 'boolean',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(EvidentiaSearchResult::class, 'search_id')
                    ->orderBy('final_rank');
    }

    public function citationBlock(): HasOne
    {
        return $this->hasOne(EvidentiaCitationBlock::class, 'search_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function isComplete(): bool
    {
        return $this->status === 'complete';
    }

    public function isDegraded(): bool
    {
        return $this->degraded_mode;
    }

    /**
     * Acumula tokens e custo.
     */
    public function addTokenUsage(int $tokensIn, int $tokensOut, string $model): void
    {
        $this->tokens_in  += $tokensIn;
        $this->tokens_out += $tokensOut;

        $pricing = config("evidentia.pricing.{$model}", ['input' => 0, 'output' => 0]);
        $cost = ($tokensIn / 1_000_000) * ($pricing['input'] ?? 0)
              + ($tokensOut / 1_000_000) * ($pricing['output'] ?? 0);

        $this->cost_usd = (float) $this->cost_usd + $cost;
        $this->save();
    }
}
