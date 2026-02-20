<?php

namespace App\Services\BscInsights;

use App\Models\AiMonthlyBudget;
use App\Models\AiRun;
use Carbon\Carbon;

class AiBudgetGuard
{
    private AiMonthlyBudget $budget;

    public function __construct()
    {
        $this->budget = AiMonthlyBudget::currentMonth();
    }

    /**
     * Verifica se há orçamento disponível para executar.
     */
    public function canRun(): bool
    {
        $maxEstimated = (float) config('bsc_insights.max_estimated_cost_usd', 0.60);
        return $this->budget->hasAvailableBudget($maxEstimated);
    }

    /**
     * Registra execução bloqueada por falta de budget.
     */
    public function registerBlocked(?int $snapshotId = null, ?int $userId = null): AiRun
    {
        $run = AiRun::create([
            'feature'            => 'bsc_insights',
            'snapshot_id'        => $snapshotId,
            'model'              => config('bsc_insights.openai_model', 'gpt-5-mini'),
            'status'             => 'blocked',
            'error_message'      => sprintf(
                'Limite mensal atingido: $%.2f / $%.2f',
                $this->budget->spent_usd,
                $this->budget->limit_usd
            ),
            'created_by_user_id' => $userId,
        ]);

        return $run;
    }

    /**
     * Registra gasto real após execução bem-sucedida.
     */
    public function recordSpend(float $costUsd): void
    {
        $this->budget->recordSpend($costUsd);
    }

    /**
     * Calcula custo estimado com base nos tokens.
     */
    public static function estimateCost(int $inputTokens, int $outputTokens): float
    {
        $costInput  = config('bsc_insights.cost_per_1m_input_tokens', 0.40);
        $costOutput = config('bsc_insights.cost_per_1m_output_tokens', 1.60);

        return round(
            ($inputTokens / 1_000_000) * $costInput +
            ($outputTokens / 1_000_000) * $costOutput,
            5
        );
    }

    /**
     * Verifica cooldown entre execuções manuais.
     */
    public function isInCooldown(): bool
    {
        $hours = (int) config('bsc_insights.cooldown_hours', 6);

        $lastRun = AiRun::where('feature', 'bsc_insights')
            ->where('status', 'success')
            ->where('created_at', '>=', Carbon::now()->subHours($hours))
            ->exists();

        return $lastRun;
    }

    /**
     * Retorna dados do budget atual para exibição na UI.
     */
    public function getBudgetInfo(): array
    {
        return [
            'mes'           => $this->budget->mes,
            'limit_usd'     => $this->budget->limit_usd,
            'spent_usd'     => $this->budget->spent_usd,
            'remaining_usd' => $this->budget->remaining,
            'usage_pct'     => $this->budget->usage_percent,
            'total_runs'    => $this->budget->total_runs,
            'can_run'       => $this->canRun(),
            'in_cooldown'   => $this->isInCooldown(),
        ];
    }
}
