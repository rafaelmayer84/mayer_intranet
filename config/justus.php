<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenAI Configuration
    |--------------------------------------------------------------------------
    */
    'openai_api_key' => env('JUSTUS_OPENAI_API_KEY', env('OPENAI_API_KEY')),
    'model_default' => env('JUSTUS_MODEL_DEFAULT', 'gpt-5.2'),
    'model_economico' => env('JUSTUS_MODEL_ECONOMICO', 'gpt-5-mini'),

    /*
    |--------------------------------------------------------------------------
    | Budget Limits (BRL)
    |--------------------------------------------------------------------------
    */
    'budget_monthly_max' => (float) env('JUSTUS_BUDGET_MONTHLY_MAX', 6000.00),
    'budget_user_max' => (float) env('JUSTUS_BUDGET_USER_MAX', 2000.00),
    'budget_alert_threshold' => (float) env('JUSTUS_BUDGET_ALERT_THRESHOLD', 0.80),

    /*
    |--------------------------------------------------------------------------
    | Token Limits
    |--------------------------------------------------------------------------
    */
    'token_monthly_limit' => (int) env('JUSTUS_TOKEN_MONTHLY_LIMIT', 200000),

    /*
    |--------------------------------------------------------------------------
    | Pricing per 1M tokens (BRL) — atualizar conforme tabela OpenAI
    |--------------------------------------------------------------------------
    */
    'pricing' => [
        'gpt-5.2' => [
            'input_per_1m' => (float) env('JUSTUS_PRICING_GPT52_INPUT', 1.75),
            'output_per_1m' => (float) env('JUSTUS_PRICING_GPT52_OUTPUT', 14.00),
        ],
        'gpt-5-mini' => [
            'input_per_1m' => (float) env('JUSTUS_PRICING_MINI_INPUT', 0.25),
            'output_per_1m' => (float) env('JUSTUS_PRICING_MINI_OUTPUT', 2.00),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | RAG Configuration
    |--------------------------------------------------------------------------
    */
    'rag_max_chunks' => (int) env('JUSTUS_RAG_MAX_CHUNKS', 15),
    'rag_max_tokens_context' => (int) env('JUSTUS_RAG_MAX_TOKENS_CONTEXT', 30000),
    'chunk_size_pages' => (int) env('JUSTUS_CHUNK_SIZE_PAGES', 2),

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    */
    
    'usd_brl' => (float) env('JUSTUS_USD_BRL', 5.80),

    'storage_disk' => 'local',
    'storage_base_path' => 'justus',
    'max_upload_size_mb' => (int) env('JUSTUS_MAX_UPLOAD_MB', 50),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    */
    'queue_name' => env('JUSTUS_QUEUE_NAME', 'justus'),

];
