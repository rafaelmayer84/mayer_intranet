<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JustusStyleGuide extends Model
{
    protected $table = 'justus_style_guides';

    protected $fillable = [
        'version',
        'name',
        'mode',
        'system_prompt',
        'ad003_disclaimer',
        'behavior_rules',
        'tone',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function active(?string $mode = null): ?self
    {
        $q = static::where('is_active', true);
        if ($mode) {
            $q->where('mode', $mode);
        }
        return $q->orderByDesc('version')->first();
    }

    public static function forMode(string $mode): ?self
    {
        return static::where('mode', $mode)->where('is_active', true)->orderByDesc('version')->first();
    }
}
