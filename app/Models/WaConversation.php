<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaConversation extends Model
{
    protected $table = 'wa_conversations';

    protected $fillable = [
        'provider', 'contact_id', 'chat_id', 'phone', 'name', 'status',
        'assigned_user_id', 'last_message_at', 'last_incoming_at',
        'first_response_at', 'unread_count', 'linked_lead_id', 'linked_cliente_id',
        'linked_processo_id', 'category', 'priority',
    ];

    protected $casts = [
        'last_message_at'   => 'datetime',
        'last_incoming_at'  => 'datetime',
        'first_response_at' => 'datetime',
        'unread_count'      => 'integer',
    ];

    public function messages(): HasMany { return $this->hasMany(WaMessage::class, 'conversation_id'); }
    public function events(): HasMany { return $this->hasMany(WaEvent::class, 'conversation_id'); }
    public function assignedUser(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function lead(): BelongsTo { return $this->belongsTo(Lead::class, 'linked_lead_id'); }
    public function tags() { return $this->belongsToMany(\App\Models\WaTag::class, 'wa_conversation_tag', 'conversation_id', 'tag_id')->withTimestamps(); }
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class, 'linked_cliente_id'); }
    public function processo(): BelongsTo { return $this->belongsTo(Processo::class, 'linked_processo_id'); }

    public function scopeOpen($query) { return $query->where('status', 'open'); }
    public function scopeClosed($query) { return $query->where('status', 'closed'); }
    public function scopeUnread($query) { return $query->where('unread_count', '>', 0); }
    public function scopeAssignedTo($query, int $userId) { return $query->where('assigned_user_id', $userId); }

    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (!str_starts_with($digits, '55') && strlen($digits) >= 10 && strlen($digits) <= 11) {
            $digits = '55' . $digits;
        }
        return $digits;
    }

    public function getLinkTypeAttribute(): string
    {
        if ($this->linked_cliente_id) return 'cliente';
        if ($this->linked_lead_id) return 'lead';
        return 'indefinido';
    }

    public function getSlaMinutesAttribute(): ?int
    {
        if (!$this->first_response_at || !$this->created_at) return null;
        return (int) $this->created_at->diffInMinutes($this->first_response_at);
    }

    public function getMinutesSinceLastIncomingAttribute(): ?int
    {
        if (!$this->last_incoming_at) return null;
        return (int) $this->last_incoming_at->diffInMinutes(now());
    }
}
