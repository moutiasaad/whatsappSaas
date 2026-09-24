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

    /*
    |--------------------------------------------------------------------------
    | Google Tag Manager
    |--------------------------------------------------------------------------
    |
    | One container for both hosts. The id is defaulted here rather than left
    | to .env: marketing and core keep separate .env files and only one of the
    | two is reachable from a deploy, so an env-only key would have tracked on
    | one site and silently not on the other. Set GTM_ID to override per host,
    | or to an empty value to switch the tags off on a box.
    |
    */
    'gtm' => [
        'id' => env('GTM_ID', 'GTM-WS2XL7ZL'),
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
        // Platform-wide fallback for the "static NCP link" checkout mode.
        // Used by the checkout button when a plan has no `paypal_ncp_link`
        // override of its own. When both are unset, the checkout falls
        // through to the dynamic Orders API redirect flow. Format is a full
        // PayPal.com/ncp/payment/XXX URL created in the merchant dashboard.
        'ncp_link'      => env('PAYPAL_NCP_LINK'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta / Facebook Messenger
    |--------------------------------------------------------------------------
    |
    | Credentials for the Messenger channel (config/plan_modules.php key
    | `messenger`). The webhook verify token is a random string YOU set here
    | AND paste into the Meta app's Messenger settings — the two must match
    | exactly or Meta's initial GET verification fails.
    |
    | graph_version is kept in config (not hardcoded) so a Meta minor-version
    | bump is a one-line change. Meta's changelog:
    | https://developers.facebook.com/docs/graph-api/changelog/versions
    |
    | Left empty on any host that does not sell the Messenger module. See
    | docs/MESSENGER_SETUP.md for the full Meta-side setup checklist.
    |
    */

    'meta' => [
        'app_id'        => env('META_APP_ID'),
        'app_secret'    => env('META_APP_SECRET'),
        'verify_token'  => env('META_WEBHOOK_VERIFY_TOKEN'),
        'graph_version' => env('META_GRAPH_VERSION', 'v21.0'),
    ],

];
