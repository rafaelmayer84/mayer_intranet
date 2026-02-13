<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmStage extends Model
{
    protected $table = 'crm_stages';

    protected $fillable = [
        'name', 'slug', 'color', 'order', 'is_won', 'is_lost', 'active',
    ];

    protected $casts = [
        'is_won'  => 'boolean',
        'is_lost' => 'boolean',
        'active'  => 'boolean',
    ];

    public function opportunities(): HasMany
    {
        return $this->hasMany(CrmOpportunity::class, 'stage_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    public function scopeOpen($query)
    {
        return $query->where('is_won', false)->where('is_lost', false);
    }
}
