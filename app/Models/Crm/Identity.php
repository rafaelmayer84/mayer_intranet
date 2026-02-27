<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Identity extends Model
{
    protected $table = 'crm_identities';

    protected $fillable = [
        'account_id', 'kind', 'value', 'value_norm',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    // ── Normalização estática ──────────────────────────────

    /**
     * Normaliza phone para E.164 BR: 55DDNNNNNNNNN (somente dígitos)
     */
    public static function normalizePhone(?string $phone): ?string
    {
        return \App\Helpers\PhoneHelper::normalize($phone);
    }

    /**
     * Normaliza email para lowercase trim
     */
    public static function normalizeEmail(?string $email): ?string
    {
        if (empty($email)) {
            return null;
        }

        return mb_strtolower(trim($email));
    }

    /**
     * Normaliza documento para somente dígitos
     */
    public static function normalizeDoc(?string $doc): ?string
    {
        if (empty($doc)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $doc);

        return strlen($digits) >= 11 ? $digits : null;
    }
}
