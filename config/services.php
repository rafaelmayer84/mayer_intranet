<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'datajuri' => [
        'client_id' => env('DATAJURI_CLIENT_ID', 'a79mtxvdhsq0pgob733z'),
        'secret_id' => env('DATAJURI_SECRET_ID', 'f21e0745-0b4f-4bd3-b0a6-959a4d47baa5'),
        'email' => env('DATAJURI_EMAIL', 'rafaelmayer@mayeradvogados.adv.br'),
        'password' => env('DATAJURI_PASSWORD', 'Mayer01.'),
    ],
];
