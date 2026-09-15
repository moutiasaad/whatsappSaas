<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Deployment role
    |--------------------------------------------------------------------------
    |
    | One codebase, two hosts:
    |
    |   'core'      — app.wavadesk.com (Server B). Owns the MySQL database,
    |                 the WhatsApp webhooks, live chat, and every panel. Serves
    |                 /api/v1/auth/* and redeems SSO handoff codes.
    |
    |   'marketing' — wavadesk.com (Server A). Landing page, pricing, register
    |                 and login forms, subscriptions. Owns no user identity:
    |                 the auth forms proxy to the core app over HTTP and hand
    |                 the browser off to it with a signed one-shot code.
    |
    | 'core' is the default on purpose. A box that never sets WAVADESK_ROLE —
    | a dev machine, a replica built by deploy/replicate, app.wavadesk.com
    | itself — behaves exactly as the monolith always has.
    |
    */

    'role' => env('WAVADESK_ROLE', 'core'),

    /*
    |--------------------------------------------------------------------------
    | Peer URLs
    |--------------------------------------------------------------------------
    |
    | core_url is where Server A sends its /api/v1/auth/* calls and where it
    | redirects the browser to finish the handoff. It is the one value the
    | marketing host cannot work without.
    |
    | marketing_origin is NOT read by anything yet. Its only consumer was the
    | CORS allow-list, which was removed when /api/v1/auth/* stopped being a
    | browser-facing surface (it is server-to-server, guarded by the shared
    | secret instead). It is kept because the documented follow-ups — sending a
    | user back to the marketing site on logout, and the billing API — both
    | need it. Setting it today changes no behaviour.
    |
    */

    'core_url' => rtrim((string) env('WAVADESK_CORE_URL', 'https://app.wavadesk.com'), '/'),

    'marketing_origin' => rtrim((string) env('WAVADESK_MARKETING_ORIGIN', 'https://wavadesk.com'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Shared secret
    |--------------------------------------------------------------------------
    |
    | One HMAC key, set identically on both hosts. It does two jobs:
    |
    |   1. Signs the short-lived handoff codes that carry identity across the
    |      domain boundary (App\Services\Auth\SsoHandoffCode).
    |   2. Authenticates Server A to Server B's auth API, as the value of the
    |      X-Wavadesk-Caller header (App\Http\Middleware\EnsureMarketingCaller).
    |
    | A mismatch fails closed: every handoff is rejected and every API call 404s.
    | Generate with: php -r "echo bin2hex(random_bytes(32));"
    |
    | Read through config (never env()) because after `artisan config:cache`
    | Laravel stops loading .env entirely and env() returns null — which is why
    | an env()-bound secret 500s in production but works fine locally.
    |
    */

    'shared_secret' => (string) env('WAVADESK_SHARED_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | API timeout
    |--------------------------------------------------------------------------
    |
    | Seconds Server A waits on the core app before giving up. Deliberately
    | short: a slow core app must not pin every php-fpm worker on the marketing
    | site, which is the page that has to stay up to sell anything.
    |
    */

    'api_timeout' => (float) env('WAVADESK_API_TIMEOUT', 5),

];
