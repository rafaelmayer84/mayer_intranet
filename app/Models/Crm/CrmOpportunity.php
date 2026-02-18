<?php

namespace App\Models\Crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmOpportunity extends Model
{
    protected $table = 'crm_opportunities';

    protected $fillable = [
        'account_id', 'stage_id', 'type', 'title', 'area', 'source',
        'value_estimated', 'owner_user_id', 'next_action_at', 'status',
        'lost_reason', 'tipo_demanda', 'lead_source', 'espo_id',
        'amount', 'currency', 'probability', 'close_date',
        'won_at', 'lost_at',
        'value_closed', 'sipex_proposal_id',
        'datajuri_contrato_id', 'datajuri_processo_id',
    ];

    protected $casts = [
        'value_estimated' => 'decimal:2',
        'next_action_at'  => 'datetime',
        'won_at'          => 'datetime',
        'lost_at'         => 'datetime',
    ];

    // --- Relationships ---

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmStage::class, 'stage_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'opportunity_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CrmEvent::class, 'opportunity_id');
    }

    // --- Scopes ---

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

    public function scopeOverdue($query)
    {
        return $query->where('status', 'open')
                     ->whereNotNull('next_action_at')
                     ->where('next_action_at', '<', now());
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByOwner($query, int $userId)
    {
        return $query->where('owner_user_id', $userId);
    }

    // --- Helpers ---

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

    public function overdueDays(): int
    {
        if (!$this->isOverdue()) return 0;
        return (int) $this->next_action_at->diffInDays(now());
    }

    public function sipexProposal()
    {
        return $this->belongsTo(\App\Models\PricingProposal::class, 'sipex_proposal_id');
    }

}
