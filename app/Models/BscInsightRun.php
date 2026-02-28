<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BscInsightRun extends Model
{
    protected $table = 'bsc_insight_runs';

    protected $fillable = [
        'snapshot_hash',
        'snapshot_json',
        'periodo_inicio',
        'periodo_fim',
        'status',
        'model_used',
        'prompt_version',
        'input_tokens',
        'output_tokens',
        'cost_usd_estimated',
        'duration_ms',
        'validator_issues_json',
        'normalizer_log_json',
        'derived_metrics_json',
        'error_message',
        'cache_hit',
        'force_requested',
        'created_by_user_id',
    ];

    protected $casts = [
        'periodo_inicio'        => 'date',
        'periodo_fim'           => 'date',
        'input_tokens'          => 'integer',
        'output_tokens'         => 'integer',
        'cost_usd_estimated'    => 'float',
        'duration_ms'           => 'integer',
        'cache_hit'             => 'boolean',
        'force_requested'       => 'boolean',
    ];

    // ── Relationships ──

    public function cards(): HasMany
    {
        return $this->hasMany(BscInsightCardV2::class, 'run_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // ── Accessors ──

    public function getValidatorIssuesAttribute(): array
    {
        $raw = $this->attributes['validator_issues_json'] ?? null;
        return $raw ? (json_decode($raw, true) ?? []) : [];
    }

    public function getNormalizerLogAttribute(): array
    {
        $raw = $this->attributes['normalizer_log_json'] ?? null;
        return $raw ? (json_decode($raw, true) ?? []) : [];
    }

    public function getDerivedMetricsAttribute(): array
    {
        $raw = $this->attributes['derived_metrics_json'] ?? null;
        return $raw ? (json_decode($raw, true) ?? []) : [];
    }

    public function getSnapshotAttribute(): array
    {
        return json_decode($this->attributes['snapshot_json'] ?? '{}', true) ?? [];
    }

    public function getTotalTokensAttribute(): int
    {
        return $this->input_tokens + $this->output_tokens;
    }

    // ── Scopes ──

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    // ── Static helpers ──

    public static function lastSuccessful(): ?self
    {
        return static::where('status', 'success')
            ->latest()
            ->first();
    }

    public static function findCached(string $hash, int $maxAgeHours = 12): ?self
    {
        return static::where('snapshot_hash', $hash)
            ->where('status', 'success')
            ->where('created_at', '>=', now()->subHours($maxAgeHours))
            ->latest()
            ->first();
    }

    // ── Status transitions ──

    public function markRunning(): void
    {
        $this->update(['status' => 'running']);
    }

    public function markValidating(): void
    {
        $this->update(['status' => 'validating']);
    }

    public function markCallingAi(): void
    {
        $this->update(['status' => 'calling_ai']);
    }

    public function markProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markSuccess(array $aiData = []): void
    {
        $this->update(array_merge(['status' => 'success'], $aiData));
    }

    public function markFailed(string $error, array $extra = []): void
    {
        $this->update(array_merge([
            'status'        => 'failed',
            'error_message' => mb_substr($error, 0, 2000),
        ], $extra));
    }
}
