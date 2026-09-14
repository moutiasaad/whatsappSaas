<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Connection flap guard (PROC-019)
    |--------------------------------------------------------------------------
    |
    | The gateway reconnects a dropped Baileys socket immediately, with no
    | backoff and no attempt cap, and emits a connection.update webhook on every
    | lap. When WhatsApp refuses the handshake permanently (a stale session
    | answers with 405, not the 401 the gateway treats as terminal) that becomes
    | an unbounded close -> connecting -> close loop: on 2026-09-09 two dead
    | instances produced 23,771 connection.update events in one day, each
    | costing a webhook_events row plus a Reverb broadcast.
    |
    | The guard breaks that loop in two non-destructive steps. It never deletes
    | an instance and never touches a pairing:
    |
    |   1. Absorb  — drop the instance's connection noise at the webhook edge,
    |                before the insert, the job and the broadcast.
    |   2. Mute    — unsubscribe the gateway from connectionUpdated for that
    |                instance, so it stops posting the loop's only event.
    |                Reversible, and reversed automatically on re-pair or if the
    |                socket recovers on its own. Messages keep flowing.
    |
    */

    'flap_guard' => [

        'enabled' => env('WHATSAPP_FLAP_GUARD', true),

        // Disconnect reasons that no amount of reconnecting will clear. The
        // session is gone; the phone has to scan a fresh QR.
        'permanent_reasons' => [401, 403, 405, 411],

        // Rolling window for the rate-based backstop, used when the gateway
        // flaps without reporting a permanent reason.
        'window_seconds' => 300,

        // 'close' events inside the window before the guard trips. Only close
        // states are counted, so a normal QR pairing (which refreshes the
        // 'connecting' state every ~20s) never approaches this.
        'close_threshold' => 30,

        // Consecutive permanent-reason closes before tripping. Two rather than
        // one so a single odd frame during pairing is absorbed.
        'permanent_threshold' => 2,

        // After a human starts a pairing, stand down for this long. Pairing
        // legitimately emits close frames while the phone has yet to scan, and
        // tripping mid-scan would block the one action that clears the fault.
        'pairing_grace_seconds' => env('WHATSAPP_FLAP_PAIRING_GRACE', 300),

        /*
        |----------------------------------------------------------------------
        | Connection-event mute
        |----------------------------------------------------------------------
        |
        | Unsubscribing the gateway from connectionUpdated stops the only event
        | its retry loop emits. Nothing is deleted, the webhook stays enabled
        | and every other event — messages above all — keeps flowing, so an
        | instance that recovers still delivers.
        |
        | It does hide genuine connection changes from the dashboard, so it is
        | limited to instances whose session WhatsApp has permanently refused —
        | those need a QR re-scan regardless, and re-pairing restores the full
        | event set.
        |
        */
        'mute' => [

            'enabled' => env('WHATSAPP_FLAP_MUTE', true),

            // Only mute on a permanent reason code. A transient flap is left
            // to the absorb layer, which costs nothing and needs no undo.
            'permanent_only' => true,

            // Never mute an instance whose conversations carried a message
            // this recently — it is demonstrably still delivering, whatever its
            // state field claims. Measured against the conversations table,
            // because whatsapp_instances.last_message_at is not written by the
            // message pipeline and would make this gate silently always-pass.
            //
            // Short by design. Suspension only ever applies to a session
            // WhatsApp has permanently refused, which cannot deliver anything;
            // traffic from hours ago says nothing about now, and stretching the
            // window would just leave the gateway looping indefinitely. The
            // window guards against acting during an in-flight burst, and
            // PollInstanceHealth restores the webhook within ~2 minutes if the
            // instance turns out to be healthy after all.
            'idle_minutes' => env('WHATSAPP_FLAP_IDLE_MINUTES', 15),

            // Statuses that are never muted under any circumstances.
            'protected_statuses' => ['connected', 'banned'],
        ],
    ],

];
