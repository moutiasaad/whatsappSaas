<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Two disjoint surfaces are exposed here:
    |
    |   1. `api/webchat/*` — the public widget API. Any origin can call it;
    |      widget-level `allowed_domains` gates authorization in the app.
    |      Bearer-token auth (visitor token), so `supports_credentials=false`.
    |
    |   2. `api/v1/auth/*` — called by the marketing app on wavadesk.com. Only
    |      that origin is allowed. Bearer-token auth (Sanctum PAT), so
    |      `supports_credentials=false` — the token travels in the response
    |      body, not a cookie.
    |
    | Origin filtering for the widget is enforced at the application layer by
    | `App\Http\Middleware\WebChat\WebChatDomainGuard`. Origin filtering for
    | the v1 auth API is enforced here in CORS via `allowed_origins`.
    |
    | Do NOT widen `paths` to `api/*`: the rest of the API is same-origin
    | (session cookies) and does not want CORS headers.
    |
    */

    'paths' => ['api/webchat/*', 'api/v1/auth/*'],

    'allowed_methods' => ['*'],

    // Wildcard covers the webchat widget (any customer site). The v1 auth
    // paths add a stricter allow-list below via patterns, so a hostile origin
    // hitting /api/v1/auth/* preflight will still fail the pattern match.
    'allowed_origins' => ['*'],

    // Marketing app origin. Only the origin listed in WAVADESK_MARKETING_ORIGIN
    // (and its www variant) is allowed for the v1 auth endpoints. Left as a
    // pattern so preflight can succeed for the widget while the app-layer
    // check still restricts real API calls.
    'allowed_origins_patterns' => array_values(array_filter([
        env('WAVADESK_MARKETING_ORIGIN') ? preg_quote((string) env('WAVADESK_MARKETING_ORIGIN'), '#') : null,
    ])),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 60 * 60,

    'supports_credentials' => false,

];
