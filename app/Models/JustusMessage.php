<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JustusMessage extends Model
{
    protected $table = 'justus_messages';

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'input_tokens',
        'output_tokens',
        'cost_brl',
        'model_used',
        'style_version',
        'citations',
        'metadata',
    ];

    protected $casts = [
        'citations' => 'array',
        'metadata' => 'array',
        'cost_brl' => 'decimal:4',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(JustusConversation::class, 'conversation_id');
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }
}
