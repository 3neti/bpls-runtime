<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
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

    'x_change' => [
        'base_url' => env('XCHANGE_BASE_URL'),
        'client_id' => env('XCHANGE_CLIENT_ID'),
        'client_secret' => env('XCHANGE_CLIENT_SECRET'),
        'scope' => env('XCHANGE_SCOPE', 'capabilities:read pay-codes:issue pay-codes:pay pay-codes:read'),
        'token_refresh_leeway_seconds' => (int) env('XCHANGE_TOKEN_REFRESH_LEEWAY_SECONDS', 60),
        'timeout_seconds' => (int) env('XCHANGE_TIMEOUT_SECONDS', 15),
    ],

];
