<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JustusPromptTemplate extends Model
{
    protected $table = 'justus_prompt_templates';

    protected $fillable = [
        'category', 'label', 'description', 'mode', 'type',
        'prompt_text', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function getActiveByCategory(): array
    {
        return self::where('is_active', true)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category')
            ->toArray();
    }
}
