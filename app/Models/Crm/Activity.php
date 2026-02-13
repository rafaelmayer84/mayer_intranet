<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class Activity extends Model
{
    protected $table = 'crm_activities';

    protected $fillable = [
        'opportunity_id', 'type', 'title', 'body',
        'due_at', 'done_at', 'created_by_user_id',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'done_at' => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->whereNull('done_at');
    }

    public function scopeDone($query)
    {
        return $query->whereNotNull('done_at');
    }

    public function scopeOverdue($query)
    {
        return $query->whereNull('done_at')
                     ->whereNotNull('due_at')
                     ->where('due_at', '<', now());
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ── Helpers ────────────────────────────────────────────

    public function isDone(): bool
    {
        return $this->done_at !== null;
    }

    public function isOverdue(): bool
    {
        return !$this->isDone() && $this->due_at && $this->due_at->isPast();
    }

    public function markDone(): void
    {
        $this->update(['done_at' => now()]);
    }
}
