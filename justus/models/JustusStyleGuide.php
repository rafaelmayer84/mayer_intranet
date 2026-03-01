<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JustusStyleGuide extends Model
{
    protected $table = 'justus_style_guides';

    protected $fillable = [
        'version',
        'name',
        'system_prompt',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function active(): ?self
    {
        return static::where('is_active', true)->orderByDesc('version')->first();
    }
}
