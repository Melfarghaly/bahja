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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Bahga Pay online gateways. Verify every flow in the gateway's sandbox
    // (base URLs below are the test environments) before going live.
    'paymob' => [
        'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com'),
        'secret_key' => env('PAYMOB_SECRET_KEY'),
        'public_key' => env('PAYMOB_PUBLIC_KEY'),
        'hmac_secret' => env('PAYMOB_HMAC_SECRET'),
        'integration_ids' => env('PAYMOB_INTEGRATION_IDS'),   // comma-separated: card,wallet
        'expiry_minutes' => env('PAYMOB_EXPIRY_MINUTES', 60),
    ],

    'fawry' => [
        'base_url' => env('FAWRY_BASE_URL', 'https://atfawry.fawrystaging.com'),
        'merchant_code' => env('FAWRY_MERCHANT_CODE'),
        'secure_key' => env('FAWRY_SECURE_KEY'),
        'expiry_hours' => env('FAWRY_EXPIRY_HOURS', 48),
    ],

    // Outgoing SMS (payment reminders). "log" writes to the log only.
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'log_channel' => env('SMS_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
    ],

    // Push notifications to the apps. "log" writes to the log only; "fcm"
    // needs a Firebase service account (path to the JSON file, or the JSON).
    'push' => [
        'driver' => env('PUSH_DRIVER', 'log'),
        'log_channel' => env('PUSH_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
        'fcm' => [
            'credentials' => env('FCM_CREDENTIALS'),
        ],
    ],

];
