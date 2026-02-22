<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class NexoQaResponseContent extends Model
{
    public $timestamps = false;

    protected $table = 'nexo_qa_responses_content';

    protected $fillable = [
        'target_id',
        'score_1_5',
        'nps',
        'tags',
        'free_text',
        'raw_payload',
        'created_at',
    ];

    protected $casts = [
        'score_1_5' => 'integer',
        'nps' => 'integer',
        'tags' => 'array',
        'raw_payload' => 'array',
        'created_at' => 'datetime',
    ];

    /* ───── Mutators: criptografia free_text ───── */

    public function setFreeTextAttribute(?string $value): void
    {
        $this->attributes['free_text'] = $value !== null
            ? Crypt::encryptString($value)
            : null;
    }

    public function getFreeTextAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            return '[ERRO DESCRIPTOGRAFIA]';
        }
    }

    /* ───── Relacionamentos ───── */

    public function target(): BelongsTo
    {
        return $this->belongsTo(NexoQaSampledTarget::class, 'target_id');
    }

    /* ───── Helpers NPS ───── */

    /**
     * Categoria NPS: promoter (9-10), passive (7-8), detractor (0-6)
     */
    public function getNpsCategoryAttribute(): ?string
    {
        if ($this->nps === null) {
            return null;
        }
        if ($this->nps >= 9) {
            return 'promoter';
        }
        if ($this->nps >= 7) {
            return 'passive';
        }
        return 'detractor';
    }
}
