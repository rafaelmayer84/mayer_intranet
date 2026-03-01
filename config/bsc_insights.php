<?php

return [
    // ── OpenAI ──
    'openai_api_key'  => env('BSC_INSIGHTS_OPENAI_API_KEY'),
    'openai_model'    => env('BSC_INSIGHTS_OPENAI_MODEL', 'gpt-4.1-mini'),
    'openai_model_heavy' => env('BSC_INSIGHTS_OPENAI_MODEL_HEAVY', null),
    'openai_max_tokens'  => (int) env('BSC_INSIGHTS_MAX_TOKENS', 16000),
    'openai_timeout'     => (int) env('BSC_INSIGHTS_TIMEOUT', 300),

    // ── Custos (por 1M tokens) ──
    'cost_per_1m_input_tokens'  => (float) env('BSC_INSIGHTS_COST_INPUT', 0.40),
    'cost_per_1m_output_tokens' => (float) env('BSC_INSIGHTS_COST_OUTPUT', 1.60),

    // ── Budget ──
    'ai_monthly_limit_usd'   => (float) env('AI_MONTHLY_LIMIT_USD', 50.00),
    'max_estimated_cost_usd' => 0.60,

    // ── Comportamento ──
    'schedule_enabled' => env('BSC_INSIGHTS_SCHEDULE_ENABLED', false),
    'demo_mode'        => env('BSC_INSIGHTS_DEMO_MODE', false),
    'cooldown_hours'   => (int) env('BSC_INSIGHTS_COOLDOWN_HOURS', 6),
    'cache_hours'      => (int) env('BSC_INSIGHTS_CACHE_HOURS', 12),

    // ── Limites de cards ──
    'max_cards_total'        => 24,
    'max_cards_per_perspectiva' => 6,
    'min_cards_per_perspectiva' => 3,
    'min_evidencias_per_card'   => 2,

    // ── Snapshot ──
    'snapshot_months' => 6,

    // ── Prompt ──
    'prompt_version' => '2.0',

    // ── Validator ──
    'validator' => [
        'margem_min_pct' => -100,
        'margem_max_pct' => 80,
        'dias_atraso_max' => 3650,
        'horas_mes_max_por_pessoa' => 300,
        'novos_clientes_spike_pct' => 200,
    ],
];
