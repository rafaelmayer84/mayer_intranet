<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiRun extends Model
{
    protected $table = 'ai_runs';

    protected $fillable = [
        'feature',
        'snapshot_id',
        'model',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'estimated_cost_usd',
        'status',
        'error_message',
        'created_by_user_id',
    ];

    protected $casts = [
        'input_tokens'       => 'integer',
        'output_tokens'      => 'integer',
        'total_tokens'       => 'integer',
        'estimated_cost_usd' => 'float',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(BscInsightSnapshot::class, 'snapshot_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(BscInsightCard::class, 'run_id');
    }

    public function scopeFeature($query, string $feature)
    {
        return $query->where('feature', $feature);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public static function lastSuccessfulBscRun(): ?self
    {
        return static::where('feature', 'bsc_insights')
            ->where('status', 'success')
            ->latest()
            ->first();
    }
}
