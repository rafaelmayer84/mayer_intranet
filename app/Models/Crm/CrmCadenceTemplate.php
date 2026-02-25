<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class CrmCadenceTemplate extends Model
{
    protected $table = 'crm_cadence_templates';

    protected $fillable = ['name', 'description', 'steps', 'is_default', 'active'];

    protected $casts = [
        'steps'      => 'array',
        'is_default' => 'boolean',
        'active'     => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public static function getDefault(): ?self
    {
        return static::active()->where('is_default', true)->first();
    }
}
