<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BscInsightSnapshot extends Model
{
    protected $table = 'bsc_insight_snapshots';

    protected $fillable = [
        'periodo_inicio',
        'periodo_fim',
        'json_payload',
        'payload_hash',
        'created_by_user_id',
        'trigger_type',
    ];

    protected $casts = [
        'periodo_inicio' => 'date',
        'periodo_fim'    => 'date',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AiRun::class, 'snapshot_id');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(BscInsightCard::class, 'snapshot_id');
    }

    public function getPayloadAttribute(): array
    {
        return json_decode($this->json_payload, true) ?? [];
    }

    public static function hashPayload(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    public static function findByHash(string $hash, string $inicio, string $fim): ?self
    {
        return static::where('payload_hash', $hash)
            ->where('periodo_inicio', $inicio)
            ->where('periodo_fim', $fim)
            ->first();
    }
}
