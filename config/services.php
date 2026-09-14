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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        'default_url' => env('WHATSAPP_API_URL', env('EVOLUTION_API_URL')),
        'default_api_key' => env('WHATSAPP_API_KEY', env('EVOLUTION_API_KEY')),
        'webhook_base_url' => env('WHATSAPP_WEBHOOK_BASE_URL', env('APP_URL', 'http://localhost')),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
    ],

    'stripe' => [
        'secret'         => env('STRIPE_SECRET_KEY'),
        'public'         => env('STRIPE_PUBLIC_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'paypal' => [
        'mode'          => env('PAYPAL_MODE', 'sandbox'),
        'client_id'     => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id'    => env('PAYPAL_WEBHOOK_ID'),
        'currency'      => env('PAYPAL_CURRENCY', 'USD'),
        'payee_email'   => env('PAYPAL_PAYEE_EMAIL'),
        // Only for the REST/Orders flow, and only meaningful if this account
        // has partner permissions to be paid on another merchant's behalf.
        // Normally left unset so funds go to the client id's own account.
        'rest_payee_email' => env('PAYPAL_REST_PAYEE_EMAIL'),
    ],

];
