<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'support' => [
        'email' => env('SUPPORT_EMAIL', ''),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Invoice scan (BETA). The scan reads the photo; Bukku receives the bill.
    // Both keys are secrets and live only in the server .env - never committed.
    'anthropic' => [
        'key'   => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5'),
    ],

    'bukku' => [
        'token'     => env('BUKKU_API_TOKEN'),
        'base_url'  => env('BUKKU_BASE_URL', 'https://api.bukku.my'),
        'subdomain' => env('BUKKU_SUBDOMAIN'),
        // Fallback expense account for a line the reviewer did not map to a
        // Bukku product. Set this to the id of your own "General Expense" (or
        // equivalent) account — read it off GET /accounts.
        'default_account_id' => (int) env('BUKKU_DEFAULT_ACCOUNT_ID'),
    ],

];
