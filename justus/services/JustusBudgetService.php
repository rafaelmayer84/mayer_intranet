<?php

namespace App\Services\Justus;

use App\Models\JustusUsageMonthly;
use Illuminate\Support\Facades\DB;

class JustusBudgetService
{
    public function getMonthlyUsage(int $userId, ?int $mes = null, ?int $ano = null): JustusUsageMonthly
    {
        $mes = $mes ?? (int) now()->format('m');
        $ano = $ano ?? (int) now()->format('Y');

        return JustusUsageMonthly::firstOrCreate(
            ['user_id' => $userId, 'mes' => $mes, 'ano' => $ano],
            ['total_input_tokens' => 0, 'total_output_tokens' => 0, 'total_cost_brl' => 0, 'total_requests' => 0]
        );
    }

    public function getGlobalMonthlyUsage(?int $mes = null, ?int $ano = null): array
    {
        $mes = $mes ?? (int) now()->format('m');
        $ano = $ano ?? (int) now()->format('Y');

        $row = DB::table('justus_usage_monthly')
            ->where('mes', $mes)
            ->where('ano', $ano)
            ->selectRaw('COALESCE(SUM(total_input_tokens),0) as input_tokens')
            ->selectRaw('COALESCE(SUM(total_output_tokens),0) as output_tokens')
            ->selectRaw('COALESCE(SUM(total_cost_brl),0) as cost_brl')
            ->selectRaw('COALESCE(SUM(total_requests),0) as requests')
            ->first();

        return [
            'input_tokens' => (int) $row->input_tokens,
            'output_tokens' => (int) $row->output_tokens,
            'total_tokens' => (int) $row->input_tokens + (int) $row->output_tokens,
            'cost_brl' => (float) $row->cost_brl,
            'requests' => (int) $row->requests,
            'limit_tokens' => config('justus.token_monthly_limit'),
            'limit_brl' => config('justus.budget_monthly_max'),
        ];
    }

    public function canProceed(int $userId): array
    {
        $global = $this->getGlobalMonthlyUsage();
        $user = $this->getMonthlyUsage($userId);
        $maxGlobal = config('justus.budget_monthly_max');
        $maxUser = config('justus.budget_user_max');
        $threshold = config('justus.budget_alert_threshold');

        $blocked = false;
        $reason = null;

        if ($global['cost_brl'] >= $maxGlobal) {
            $blocked = true;
            $reason = 'Orçamento mensal global atingido (R$ ' . number_format($maxGlobal, 2, ',', '.') . ')';
        } elseif ((float) $user->total_cost_brl >= $maxUser) {
            $blocked = true;
            $reason = 'Orçamento mensal do usuário atingido (R$ ' . number_format($maxUser, 2, ',', '.') . ')';
        }

        $alertLevel = 'normal';
        $pctGlobal = $maxGlobal > 0 ? $global['cost_brl'] / $maxGlobal : 0;
        if ($pctGlobal >= $threshold) {
            $alertLevel = 'warning';
        }
        if ($pctGlobal >= 0.95) {
            $alertLevel = 'critical';
        }

        return [
            'allowed' => !$blocked,
            'blocked_reason' => $reason,
            'alert_level' => $alertLevel,
            'global' => $global,
            'user' => [
                'cost_brl' => (float) $user->total_cost_brl,
                'tokens' => $user->total_input_tokens + $user->total_output_tokens,
                'limit_brl' => $maxUser,
            ],
        ];
    }

    public function recordUsage(int $userId, int $inputTokens, int $outputTokens, float $costBrl): void
    {
        $usage = $this->getMonthlyUsage($userId);
        $usage->increment('total_input_tokens', $inputTokens);
        $usage->increment('total_output_tokens', $outputTokens);
        $usage->increment('total_requests');
        DB::table('justus_usage_monthly')
            ->where('id', $usage->id)
            ->update(['total_cost_brl' => DB::raw('total_cost_brl + ' . $costBrl)]);
    }

    public function calculateCost(int $inputTokens, int $outputTokens, string $model = null): float
    {
        $model = $model ?? config('justus.model_default');
        $pricing = config('justus.pricing.' . $model, config('justus.pricing.gpt-5.2'));

        $inputCost = ($inputTokens / 1000000) * $pricing['input_per_1m'];
        $outputCost = ($outputTokens / 1000000) * $pricing['output_per_1m'];

        return round($inputCost + $outputCost, 4);
    }
}
