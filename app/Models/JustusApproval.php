<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JustusApproval extends Model
{
    protected $table = 'justus_approvals';

    protected $fillable = [
        'conversation_id',
        'message_id',
        'requested_by',
        'reviewed_by',
        'status',
        'reviewer_notes',
        'quality_flags',
    ];

    protected $casts = [
        'quality_flags' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(JustusConversation::class, 'conversation_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(JustusMessage::class, 'message_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewed_by');
    }
}
