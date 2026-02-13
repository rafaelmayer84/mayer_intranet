<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class Account extends Model
{
    protected $table = 'crm_accounts';

    protected $fillable = [
        'type', 'name', 'doc_digits', 'notes', 'owner_user_id',
    ];

    // ── Relations ──────────────────────────────────────────

    public function identities(): HasMany
    {
        return $this->hasMany(Identity::class, 'account_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'account_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'account_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    // ── Helpers ────────────────────────────────────────────

    public function phone(): ?string
    {
        return $this->identities()->where('kind', 'phone')->value('value');
    }

    public function email(): ?string
    {
        return $this->identities()->where('kind', 'email')->value('value');
    }

    public function datajuriId(): ?string
    {
        return $this->identities()->where('kind', 'datajuri')->value('value');
    }

    public function openOpportunities(): HasMany
    {
        return $this->opportunities()->where('status', 'open');
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopePf($query)
    {
        return $query->where('type', 'PF');
    }

    public function scopePj($query)
    {
        return $query->where('type', 'PJ');
    }
}
