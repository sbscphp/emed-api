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

    /*
    |--------------------------------------------------------------------------
    | Paystack
    |--------------------------------------------------------------------------
    |
    | One set of keys for the whole platform: patients pay eMed, and Paystack
    | splits each charge to the hospital that raised the bill, through the
    | subaccount stored against that hospital. Hospitals never hold keys.
    |
    | `commission_percent` is what eMed keeps when a hospital has not been given
    | a rate of its own. Paystack reads it the other way round on a subaccount,
    | where `percentage_charge` is the share the *subaccount* keeps, so the two
    | are converted for each other rather than stored twice.
    |
    | `support_url` is where a patient's share link points. That link opens a web
    | page rather than the app, so it is configured rather than derived from
    | APP_URL, and it differs per environment — a link generated on staging must
    | not send a supporter to production. Each deploy sets PAYMENT_SUPPORT_URL to
    | its own value, so nothing here is inferred from APP_ENV.
    |
    */
    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        'currency' => env('PAYSTACK_CURRENCY', 'NGN'),
        'commission_percent' => (float) env('PAYSTACK_COMMISSION_PERCENT', 0),
        'channels' => ['card', 'bank_transfer'],
        'callback_url' => env('PAYSTACK_CALLBACK_URL'),
        'support_url' => env('PAYMENT_SUPPORT_URL'),
        'support_link_days' => (int) env('PAYMENT_SUPPORT_LINK_DAYS', 7),
        'timeout' => (int) env('PAYSTACK_TIMEOUT', 30),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
