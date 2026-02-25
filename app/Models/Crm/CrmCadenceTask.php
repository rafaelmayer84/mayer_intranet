<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmCadenceTask extends Model
{
    protected $table = 'crm_cadence_tasks';

    protected $fillable = [
        'opportunity_id', 'account_id', 'cadence_template_id', 'step_number',
        'title', 'description', 'due_date', 'completed_at',
        'assigned_user_id', 'notified', 'notified_email',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'completed_at' => 'datetime',
        'notified'     => 'boolean',
        'notified_email' => 'boolean',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class, 'opportunity_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_user_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CrmCadenceTemplate::class, 'cadence_template_id');
    }

    public function scopePending($query)
    {
        return $query->whereNull('completed_at');
    }

    public function scopeDueToday($query)
    {
        return $query->pending()->whereDate('due_date', today());
    }

    public function scopeOverdue($query)
    {
        return $query->pending()->where('due_date', '<', today());
    }

    public function isDone(): bool
    {
        return $this->completed_at !== null;
    }
}
