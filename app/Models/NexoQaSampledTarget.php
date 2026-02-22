<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class NexoQaSampledTarget extends Model
{
    protected $table = 'nexo_qa_sampled_targets';

    protected $fillable = [
        'campaign_id',
        'source_type',
        'source_id',
        'phone_e164',
        'phone_hash',
        'responsible_user_id',
        'last_interaction_at',
        'sampled_at',
        'send_status',
        'skip_reason',
        'sendpulse_message_id',
        'token',
    ];

    protected $casts = [
        'sampled_at' => 'datetime',
        'last_interaction_at' => 'datetime',
        'source_id' => 'integer',
    ];

    /* ───── Boot: gerar token e phone_hash automaticamente ───── */

    protected static function booted(): void
    {
        static::creating(function (self $target) {
            if (empty($target->token)) {
                $target->token = (string) Str::uuid();
            }
            if (empty($target->phone_hash) && !empty($target->phone_e164)) {
                $target->phone_hash = hash('sha256', $target->phone_e164);
            }
        });
    }

    /* ───── Relacionamentos ───── */

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NexoQaCampaign::class, 'campaign_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'responsible_user_id');
    }

    public function responseIdentity(): HasOne
    {
        return $this->hasOne(NexoQaResponseIdentity::class, 'target_id');
    }

    public function responseContent(): HasOne
    {
        return $this->hasOne(NexoQaResponseContent::class, 'target_id');
    }

    /* ───── Scopes ───── */

    public function scopePending($query)
    {
        return $query->where('send_status', 'PENDING');
    }

    public function scopeSent($query)
    {
        return $query->where('send_status', 'SENT');
    }

    /* ───── Helpers ───── */

    public function isPending(): bool
    {
        return $this->send_status === 'PENDING';
    }

    public function isSent(): bool
    {
        return $this->send_status === 'SENT';
    }

    public function markSent(string $messageId = null): void
    {
        $this->update([
            'send_status' => 'SENT',
            'sendpulse_message_id' => $messageId,
        ]);
    }

    public function markFailed(string $reason = null): void
    {
        $this->update([
            'send_status' => 'FAILED',
            'skip_reason' => $reason ?? 'Falha no envio SendPulse',
        ]);
    }

    public function markSkipped(string $reason): void
    {
        $this->update([
            'send_status' => 'SKIPPED',
            'skip_reason' => $reason,
        ]);
    }

    /**
     * Telefone mascarado para exibição: +55*********1234
     */
    public function getMaskedPhoneAttribute(): string
    {
        $phone = $this->phone_e164;
        if (strlen($phone) < 6) {
            return '***';
        }
        $prefix = substr($phone, 0, 3);
        $suffix = substr($phone, -4);
        $middle = str_repeat('*', max(0, strlen($phone) - 7));
        return $prefix . $middle . $suffix;
    }
}
