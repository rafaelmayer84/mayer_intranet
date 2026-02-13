<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class Event extends Model
{
    protected $table = 'crm_events';

    protected $fillable = [
        'opportunity_id', 'account_id', 'type',
        'payload', 'happened_at', 'created_by_user_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'happened_at' => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('happened_at', '>=', now()->subDays($days));
    }

    // ── Factory helpers ────────────────────────────────────

    public static function log(
        string $type,
        ?int $opportunityId = null,
        ?int $accountId = null,
        ?array $payload = null,
        ?int $userId = null
    ): self {
        return self::create([
            'type' => $type,
            'opportunity_id' => $opportunityId,
            'account_id' => $accountId,
            'payload' => $payload,
            'happened_at' => now(),
            'created_by_user_id' => $userId ?? auth()->id(),
        ]);
    }
}
