<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenAI Models
    |--------------------------------------------------------------------------
    */
    'openai_model_rerank'     => env('EVIDENTIA_MODEL_RERANK', 'gpt-4.1-mini'),
    'openai_model_writer'     => env('EVIDENTIA_MODEL_WRITER', 'gpt-4.1-mini'),
    'openai_model_query'      => env('EVIDENTIA_MODEL_QUERY', 'gpt-4.1-mini'),
    'openai_embedding_model'  => env('EVIDENTIA_EMBEDDING_MODEL', 'text-embedding-3-small'),
    'openai_embedding_dims'   => env('EVIDENTIA_EMBEDDING_DIMS', 1536),

    /*
    |--------------------------------------------------------------------------
    | Budget Guard
    |--------------------------------------------------------------------------
    */
    'daily_budget_usd' => env('EVIDENTIA_DAILY_BUDGET', 5.00),

    /*
    |--------------------------------------------------------------------------
    | Search Tuning
    |--------------------------------------------------------------------------
    */
    'max_candidates_fulltext' => 200,
    'max_candidates_semantic' => 100,
    'max_rerank'              => 30,
    'default_topk'            => 10,

    // Score weights (must sum to 1.0)
    'weight_semantic'  => 0.55,
    'weight_text'      => 0.45,

    // Final mix: how much rerank matters vs initial mix
    'weight_mix'    => 0.50,
    'weight_rerank' => 0.50,

    /*
    |--------------------------------------------------------------------------
    | Chunking
    |--------------------------------------------------------------------------
    */
    'chunk_size_chars'    => 1000,
    'chunk_overlap_chars' => 150,

    /*
    |--------------------------------------------------------------------------
    | Fulltext Options
    |--------------------------------------------------------------------------
    */
    'enable_fulltext_inteiro_teor' => false,
    'fulltext_column'              => 'ementa',

    /*
    |--------------------------------------------------------------------------
    | Embedding Generation
    |--------------------------------------------------------------------------
    */
    'embed_inteiro_teor'    => false,
    'embed_batch_size'      => 20,    // chunks per OpenAI API call
    'embed_queue'           => 'evidentia',
    'embed_queue_timeout'   => 120,

    /*
    |--------------------------------------------------------------------------
    | Source Databases (cross-database config)
    |--------------------------------------------------------------------------
    | Maps tribunal sigla to Laravel DB connection name and table.
    */
    'tribunal_databases' => [
        'TJSC'  => ['connection' => 'justus_tjsc',  'table' => 'justus_jurisprudencia'],
        'STJ'   => ['connection' => 'justus_stj',   'table' => 'justus_jurisprudencia'],
        'TRF4'  => ['connection' => 'mysql',         'table' => 'justus_jurisprudencia'],
        'TRT12' => ['connection' => 'justus_falcao', 'table' => 'justus_jurisprudencia'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Citation Block
    |--------------------------------------------------------------------------
    */
    'citation_max_results' => 5,

    /*
    |--------------------------------------------------------------------------
    | OpenAI Pricing (USD per 1M tokens) - for cost tracking
    |--------------------------------------------------------------------------
    */
    'pricing' => [
        'gpt-4.1-mini' => [
            'input'  => 0.40,
            'output' => 1.60,
        ],
        'text-embedding-3-small' => [
            'input' => 0.02,
        ],
    ],

];
