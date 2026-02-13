<?php

namespace App\Models\Crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmEvent extends Model
{
    protected $table = 'crm_events';

    protected $fillable = [
        'account_id', 'opportunity_id', 'type', 'payload',
        'happened_at', 'created_by_user_id',
    ];

    protected $casts = [
        'payload'     => 'array',
        'happened_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class, 'opportunity_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
