<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class AiMonthlyBudget extends Model
{
    protected $table = 'ai_monthly_budgets';

    protected $fillable = [
        'mes',
        'limit_usd',
        'spent_usd',
        'total_runs',
    ];

    protected $casts = [
        'limit_usd'  => 'float',
        'spent_usd'  => 'float',
        'total_runs' => 'integer',
    ];

    public static function currentMonth(): self
    {
        $mes = Carbon::now()->format('Y-m');
        $defaultLimit = (float) config('bsc_insights.ai_monthly_limit_usd', 10.00);

        return static::firstOrCreate(
            ['mes' => $mes],
            ['limit_usd' => $defaultLimit, 'spent_usd' => 0, 'total_runs' => 0]
        );
    }

    public function hasAvailableBudget(float $estimatedCost = 0.50): bool
    {
        return ($this->spent_usd + $estimatedCost) <= $this->limit_usd;
    }

    public function recordSpend(float $cost): void
    {
        $this->increment('spent_usd', $cost);
        $this->increment('total_runs');
    }

    public function getRemainingAttribute(): float
    {
        return max(0, $this->limit_usd - $this->spent_usd);
    }

    public function getUsagePercentAttribute(): float
    {
        if ($this->limit_usd <= 0) {
            return 100.0;
        }
        return min(100, round(($this->spent_usd / $this->limit_usd) * 100, 1));
    }
}
