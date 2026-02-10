<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'direction',
        'message_text',
        'message_type',
        'raw_data',
        'sent_at'
    ];

    protected $casts = [
        'raw_data' => 'array',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relacionamento com lead
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Verifica se é mensagem do cliente
     */
    public function isInbound(): bool
    {
        return $this->direction === 'in';
    }

    /**
     * Verifica se é mensagem do bot
     */
    public function isOutbound(): bool
    {
        return $this->direction === 'out';
    }

    /**
     * Retorna remetente formatado
     */
    public function getSender(): string
    {
        return $this->isInbound() ? 'Cliente' : 'Bot';
    }
}
