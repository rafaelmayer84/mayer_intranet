<?php

namespace App\Models\Crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmActivity extends Model
{
    protected $table = 'crm_activities';

    protected $fillable = [
        'opportunity_id', 'account_id', 'type', 'purpose', 'title', 'body',
        'decisions', 'pending_items',
        'due_at', 'done_at', 'created_by_user_id',
    ];

    protected $casts = [
        'due_at'  => 'datetime',
        'done_at' => 'datetime',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class, 'opportunity_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

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

    public function isDone(): bool
    {
        return $this->done_at !== null;
    }
}
