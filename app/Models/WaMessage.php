<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaMessage extends Model
{
    protected $table = 'wa_messages';

    protected $fillable = [
        'conversation_id',
        'provider_message_id',
        'direction',
        'is_human',
        'message_type',
        'body',
        'media_url',
        'media_mime_type',
        'media_filename',
        'media_caption',
        'reply_to_message_id',
        'raw_payload',
        'sent_at',
    ];

    protected $casts = [
        'direction'    => 'integer',
        'is_human'     => 'boolean',
        'raw_payload'  => 'array',
        'sent_at'      => 'datetime',
    ];

    // ─── Constantes ────────────────────────────────────────

    const DIRECTION_INCOMING = 1;
    const DIRECTION_OUTGOING = 2;

    // ─── Relacionamentos ───────────────────────────────────

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WaConversation::class, 'conversation_id');
    }

    // ─── Scopes ────────────────────────────────────────────

    public function scopeIncoming($query)
    {
        return $query->where('direction', self::DIRECTION_INCOMING);
    }

    public function scopeOutgoing($query)
    {
        return $query->where('direction', self::DIRECTION_OUTGOING);
    }

    public function scopeHuman($query)
    {
        return $query->where('is_human', true);
    }

    // ─── Helpers ───────────────────────────────────────────

    public function isIncoming(): bool
    {
        return $this->direction === self::DIRECTION_INCOMING;
    }

    public function isOutgoing(): bool
    {
        return $this->direction === self::DIRECTION_OUTGOING;
    }

    /**
     * Extrai texto do body ou raw_payload com fallback robusto.
     * Prioridade: body > raw_payload.data.text.body > raw_payload.text.body
     */
    public function getTextContent(): string
    {
        if (!empty($this->body)) {
            return $this->body;
        }

        $payload = $this->raw_payload;
        if (!is_array($payload)) {
            return '';
        }

        // Cadeia de resolução SendPulse
        return data_get($payload, 'data.text.body')
            ?? data_get($payload, 'text.body')
            ?? data_get($payload, 'data.body')
            ?? data_get($payload, 'body')
            ?? data_get($payload, 'message')
            ?? '';
    }
}
