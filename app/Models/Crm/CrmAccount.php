<?php

namespace App\Models\Crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmAccount extends Model
{
    protected $table = 'crm_accounts';

    protected $fillable = [
        'datajuri_pessoa_id', 'kind', 'name', 'doc_digits', 'email',
        'phone_e164', 'owner_user_id', 'lifecycle', 'health_score',
        'last_touch_at', 'next_touch_at', 'tags', 'notes',
        'segment', 'segment_summary', 'segment_cached_at',
    ];

    protected $casts = [
        'health_score'   => 'integer',
        'last_touch_at'  => 'datetime',
        'next_touch_at'       => 'datetime',
        'segment_cached_at'   => 'datetime',
    ];

    // --- Relationships ---

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function identities(): HasMany
    {
        return $this->hasMany(CrmIdentity::class, 'account_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(CrmOpportunity::class, 'account_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'account_id');
    }

    public function serviceRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CrmServiceRequest::class, 'account_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CrmDocument::class, 'account_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CrmEvent::class, 'account_id');
    }

    // --- Scopes ---

    public function scopeClients($query)
    {
        return $query->where('kind', 'client');
    }

    public function scopeProspects($query)
    {
        return $query->where('kind', 'prospect');
    }

    public function scopeByLifecycle($query, string $lifecycle)
    {
        return $query->where('lifecycle', $lifecycle);
    }

    public function scopeWithoutContactSince($query, int $days)
    {
        return $query->where(function ($q) use ($days) {
            $q->whereNull('last_touch_at')
              ->orWhere('last_touch_at', '<', now()->subDays($days));
        });
    }

    public function scopeOverdueNextTouch($query)
    {
        return $query->whereNotNull('next_touch_at')
                     ->where('next_touch_at', '<', now());
    }

    public function scopeByOwner($query, int $userId)
    {
        return $query->where('owner_user_id', $userId);
    }

    // --- Helpers ---

    public function isClient(): bool
    {
        return $this->kind === 'client';
    }

    public function isProspect(): bool
    {
        return $this->kind === 'prospect';
    }

    public function getTagsArray(): array
    {
        if (empty($this->tags)) return [];
        $decoded = json_decode($this->tags, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function setTagsFromArray(array $tags): void
    {
        $this->tags = json_encode(array_values(array_unique($tags)));
    }

    /**
     * Busca dados do DataJuri no cache local (tabela clientes).
     * Retorna null se não houver vínculo ou dados.
     */
    public function datajuriCache(): ?object
    {
        if (!$this->datajuri_pessoa_id) return null;

        return \DB::table('clientes')
            ->where('datajuri_id', $this->datajuri_pessoa_id)
            ->first();
    }

    /**
     * Resumo financeiro do cache DataJuri.
     */
    public function datajuriFinanceiro(): array
    {
        $cache = $this->datajuriCache();
        if (!$cache) return [];

        return [
            'total_contas_receber'  => $cache->total_contas_receber ?? 0,
            'total_contas_vencidas' => $cache->total_contas_vencidas ?? 0,
            'valor_contas_abertas'  => $cache->valor_contas_abertas ?? 0,
            'valor_hora'            => $cache->valor_hora ?? null,
        ];
    }
}
