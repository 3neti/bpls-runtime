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
        'base_url' => env('XCHANGE_BASE_URL') ?: env('XCHANGE_API_BASE_URL'),
        'token_endpoint' => env('XCHANGE_TOKEN_ENDPOINT') ?: '/oauth/token',
        'client_id' => env('XCHANGE_CLIENT_ID'),
        'client_secret' => env('XCHANGE_CLIENT_SECRET'),
        'scope' => env('XCHANGE_SCOPE', 'pay-codes:estimate pay-codes:issue pay-codes:read pay-codes:pay'),
        'settlement_rail' => env('XCHANGE_SETTLEMENT_RAIL', 'INSTAPAY'),
        'token_refresh_leeway_seconds' => (int) env('XCHANGE_TOKEN_REFRESH_LEEWAY_SECONDS', 60),
        'connect_timeout_seconds' => (int) env('XCHANGE_CONNECT_TIMEOUT_SECONDS', 5),
        'timeout_seconds' => (int) env('XCHANGE_TIMEOUT_SECONDS', 15),
    ],

];
