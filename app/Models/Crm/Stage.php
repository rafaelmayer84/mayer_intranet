<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stage extends Model
{
    protected $table = 'crm_stages';

    protected $fillable = [
        'name', 'sort', 'is_won', 'is_lost', 'is_active', 'color',
    ];

    protected $casts = [
        'is_won' => 'boolean',
        'is_lost' => 'boolean',
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'stage_id');
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort');
    }

    public function scopeWorkable($query)
    {
        return $query->where('is_won', false)->where('is_lost', false)->where('is_active', true);
    }

    // ── Helpers ────────────────────────────────────────────

    public function isTerminal(): bool
    {
        return $this->is_won || $this->is_lost;
    }
}
