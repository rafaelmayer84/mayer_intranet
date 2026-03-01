<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JustusUsageMonthly extends Model
{
    protected $table = 'justus_usage_monthly';

    protected $fillable = [
        'user_id',
        'mes',
        'ano',
        'total_input_tokens',
        'total_output_tokens',
        'total_cost_brl',
        'total_requests',
    ];

    protected $casts = [
        'total_cost_brl' => 'decimal:4',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function getTotalTokensAttribute(): int
    {
        return $this->total_input_tokens + $this->total_output_tokens;
    }
}
