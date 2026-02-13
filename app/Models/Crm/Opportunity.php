<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class Opportunity extends Model
{
    protected $table = 'crm_opportunities';

    protected $fillable = [
        'account_id', 'stage_id', 'title', 'area', 'source',
        'value_estimated', 'owner_user_id', 'next_action_at',
        'status', 'lost_reason', 'won_at', 'lost_at', 'lead_id',
    ];

    protected $casts = [
        'value_estimated' => 'decimal:2',
        'next_action_at' => 'datetime',
        'won_at' => 'datetime',
        'lost_at' => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'stage_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'opportunity_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'opportunity_id');
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeWon($query)
    {
        return $query->where('status', 'won');
    }

    public function scopeLost($query)
    {
        return $query->where('status', 'lost');
    }

    public function scopeOverdueNextAction($query)
    {
        return $query->where('status', 'open')
                     ->whereNotNull('next_action_at')
                     ->where('next_action_at', '<', now());
    }

    public function scopeByOwner($query, $userId)
    {
        return $query->where('owner_user_id', $userId);
    }

    public function scopeBySource($query, $source)
    {
        return $query->where('source', $source);
    }

    public function scopeByArea($query, $area)
    {
        return $query->where('area', $area);
    }

    public function scopeCreatedInPeriod($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    // ── Helpers ────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isOverdue(): bool
    {
        return $this->isOpen()
            && $this->next_action_at
            && $this->next_action_at->isPast();
    }

    public function pendingActivities(): HasMany
    {
        return $this->activities()->whereNull('done_at');
    }

    public function daysInCurrentStage(): int
    {
        $lastStageEvent = $this->events()
            ->where('type', 'stage_changed')
            ->latest('happened_at')
            ->first();

        $since = $lastStageEvent ? $lastStageEvent->happened_at : $this->created_at;

        return (int) $since->diffInDays(now());
    }
}
