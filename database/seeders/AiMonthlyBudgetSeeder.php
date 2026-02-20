<?php

namespace Database\Seeders;

use App\Models\AiMonthlyBudget;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AiMonthlyBudgetSeeder extends Seeder
{
    public function run(): void
    {
        $mes = Carbon::now()->format('Y-m');

        AiMonthlyBudget::firstOrCreate(
            ['mes' => $mes],
            [
                'limit_usd'  => (float) config('bsc_insights.ai_monthly_limit_usd', 10.00),
                'spent_usd'  => 0,
                'total_runs' => 0,
            ]
        );

        $this->command->info("Budget {$mes}: OK");
    }
}
