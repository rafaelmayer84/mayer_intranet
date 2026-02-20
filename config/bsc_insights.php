<?php

return [
    'openai_api_key'  => env('BSC_INSIGHTS_OPENAI_API_KEY'),
    'openai_model'    => env('BSC_INSIGHTS_OPENAI_MODEL', 'gpt-5-mini'),

    'cost_per_1m_input_tokens'  => (float) env('BSC_INSIGHTS_COST_INPUT', 0.40),
    'cost_per_1m_output_tokens' => (float) env('BSC_INSIGHTS_COST_OUTPUT', 1.60),

    'ai_monthly_limit_usd'     => (float) env('AI_MONTHLY_LIMIT_USD', 10.00),
    'max_estimated_cost_usd'   => 0.60,

    'schedule_enabled' => env('BSC_INSIGHTS_SCHEDULE_ENABLED', false),
    'demo_mode'        => env('BSC_INSIGHTS_DEMO_MODE', false),

    'cooldown_hours'         => 6,
    'max_cards_total'        => 32,
    'max_cards_per_universo' => 8,

    'snapshot_weeks'  => 12,
    'snapshot_months' => 6,
];
