<?php

/**
 * ADICIONAR ESTE CONTEÚDO DENTRO DO ARRAY 'services' em config/services.php
 */

return [
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'sendpulse' => [
        'webhook_token' => env('SENDPULSE_WEBHOOK_TOKEN'),
    ],
];
