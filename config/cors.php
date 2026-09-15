<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Scoped intentionally to the Web Live-Chat public API only. Do NOT widen
    | `paths` to `api/*` — the rest of the API is same-origin (session cookies)
    | and does not want CORS headers.
    |
    | In particular `api/v1/auth/*` does NOT belong here. Those endpoints are
    | called by the marketing app's php-fpm, not by a browser, so they need no
    | preflight; they are guarded by the shared-secret X-Wavadesk-Caller header
    | instead (App\Http\Middleware\EnsureMarketingCaller). Listing them here
    | with `allowed_origins => ['*']` would hand every website on the internet
    | permission to read a login response — an allow-list pattern does not undo
    | that, because a literal `*` in `allowed_origins` already matched.
    |
    | Origin filtering for the widget is enforced at the application layer by
    | `App\Http\Middleware\WebChat\WebChatDomainGuard`, which checks the
    | widget's `allowed_domains`. That is why `allowed_origins` here is `*` —
    | CORS lets the browser preflight succeed, and the guard rejects a bad
    | origin at 403 with a clear error. Visitor auth is a bearer token, so
    | credentials are NOT supported (browsers never send cookies here).
    |
    */

    'paths' => ['api/webchat/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 60 * 60,

    'supports_credentials' => false,

];
