<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Widget public asset
    |--------------------------------------------------------------------------
    | Path (relative to /public) at which the embeddable widget.js is served.
    | Used by the settings page when it renders the copy-paste snippet.
    */
    'widget_asset_path' => '/webchat/widget.js',

    /*
    |--------------------------------------------------------------------------
    | Message body limits
    |--------------------------------------------------------------------------
    | Enforced by the public MessageController AND the dashboard reply
    | endpoint. Kept in one place so both stay in lockstep.
    */
    'message_max_length' => (int) env('WEBCHAT_MESSAGE_MAX_LENGTH', 4000),

    /*
    |--------------------------------------------------------------------------
    | Stale-claim release
    |--------------------------------------------------------------------------
    | If a conversation is `assigned` but nothing has happened on it for this
    | many minutes, `webchat:release-stale` moves it back to `pending` so the
    | next available agent can pick it up. This protects visitors from being
    | left hanging when an agent walks away without closing the chat.
    */
    'release_stale_after_minutes' => (int) env('WEBCHAT_RELEASE_STALE_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Widget poll interval
    |--------------------------------------------------------------------------
    | Fallback poll cadence used by the browser widget when the WebSocket is
    | unavailable. Kept relatively long — WebSockets are the primary path.
    */
    'widget_poll_interval_ms' => (int) env('WEBCHAT_WIDGET_POLL_MS', 4000),

    /*
    |--------------------------------------------------------------------------
    | Rate limits
    |--------------------------------------------------------------------------
    | Requests-per-minute for the two public endpoints most exposed to abuse:
    | session bootstrap (per IP) and message send (per visitor token).
    */
    'rate_limits' => [
        'session_per_minute'      => (int) env('WEBCHAT_RL_SESSION',  60),
        'messages_per_minute'     => (int) env('WEBCHAT_RL_MESSAGES', 40),
    ],
];
