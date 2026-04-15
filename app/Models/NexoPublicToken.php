<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NexoPublicToken extends Model
{
    protected $table = 'nexo_public_tokens';

    protected $fillable = [
        'token',
        'tipo',
        'cliente_id',
        'telefone',
        'payload',
        'expires_at',
        'access_count',
        'last_accessed_at',
    ];

    protected $casts = [
        'payload'          => 'array',
        'expires_at'       => 'datetime',
        'last_accessed_at' => 'datetime',
    ];

    // ── Helpers ────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function recordAccess(): void
    {
        $this->increment('access_count');
        $this->update(['last_accessed_at' => now()]);
    }

    public function getUrl(): string
    {
        return url('/a/' . $this->token);
    }

    // ── Scopes ─────────────────────────────────────────────────

    public function scopeValido($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeExpirado($query)
    {
        return $query->where('expires_at', '<=', now());
    }
}
