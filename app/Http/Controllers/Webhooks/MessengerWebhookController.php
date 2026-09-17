<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMessengerIncomingMessage;
use App\Models\Messenger\Page;
use App\Services\Messenger\MetaConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * MessengerWebhookController
 *
 * Handles both halves of Meta's webhook contract:
 *
 *   GET  /webhooks/messenger  → Subscription verification. Meta hits this
 *                               when you save the webhook URL in the app
 *                               dashboard. Must echo back hub_challenge
 *                               only when hub_verify_token matches the
 *                               value in .env exactly. On mismatch we
 *                               return 403 so Meta's UI shows a clear
 *                               error instead of silently disabling.
 *
 *   POST /webhooks/messenger  → Event delivery. Meta signs the raw body
 *                               with the App Secret using HMAC-SHA256 and
 *                               puts the result in X-Hub-Signature-256
 *                               ("sha256=<hex>"). We verify against the
 *                               RAW body — any middleware that touches
 *                               the request body before us breaks the
 *                               signature — dispatch a queued job for
 *                               the real work, and return 200 fast.
 *                               Meta retries slow / erroring endpoints
 *                               and eventually disables them.
 *
 * CSRF is not applied here because /api/* is exempted globally
 * (bootstrap/app.php's validateCsrfTokens except list).
 *
 * Per-endpoint request path: the URL you paste into Meta's Messenger
 * settings is `https://app.wavadesk.com/api/webhooks/messenger`. The
 * `/api` prefix is applied globally to every route in routes/api.php
 * by bootstrap/app.php (`Route::middleware('web')->prefix('api')`),
 * so a `Route::get('/webhooks/messenger', ...)` line here serves
 * `/api/webhooks/messenger`. Same trap the WhatsApp webhook lives
 * with (`/api/webhooks/whatsapp/{token}`).
 */
class MessengerWebhookController extends Controller
{
    /**
     * GET /webhooks/messenger
     *
     * Meta uses PHP's default $_GET conversion rules — the dots in
     * `hub.mode`, `hub.verify_token`, `hub.challenge` come through as
     * `hub_mode`, `hub_verify_token`, `hub_challenge`. Reading the
     * dotted names would silently return null.
     */
    public function verify(Request $request): Response
    {
        $mode      = (string) $request->query('hub_mode', '');
        $token     = (string) $request->query('hub_verify_token', '');
        $challenge = (string) $request->query('hub_challenge', '');

        $expected  = MetaConfig::verifyToken();

        // hash_equals guards against timing side-channels on the compare.
        // Ordering hard-coded: guest input FIRST so the constant-time
        // property survives a mistakenly-swapped call.
        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            Log::channel('messenger')->info('Webhook verify OK');
            return response($challenge, 200);
        }

        Log::channel('messenger')->warning('Webhook verify refused', [
            'mode'                 => $mode,
            'verify_token_length'  => strlen($token),
            'expected_length'      => strlen($expected),
            'expected_configured'  => $expected !== '',
        ]);

        return response('Forbidden', 403);
    }

    /**
     * POST /webhooks/messenger
     *
     * Signature verify + queue dispatch + 200 fast. The heavy parse
     * (walk entry[].messaging[], upsert conversations + messages,
     * broadcast, dispatch AI) lives in ProcessMessengerIncomingMessage.
     */
    public function handle(Request $request): Response
    {
        $appSecret = MetaConfig::appSecret();

        if ($appSecret === '') {
            // Fail closed: without the secret we cannot verify. Log at
            // warning so a "why is this 202" thread is one grep away.
            Log::channel('messenger')->warning('Webhook received but Meta app secret is not configured — dropping');
            return response('', 202);
        }

        // Signature is over the RAW request body. getContent() returns
        // it untouched; do NOT read from $request->all() and re-encode.
        $raw       = $request->getContent();
        $signature = (string) $request->header('X-Hub-Signature-256', '');
        $expected  = 'sha256=' . hash_hmac('sha256', $raw, $appSecret);

        if (! hash_equals($expected, $signature)) {
            Log::channel('messenger')->warning('Webhook signature mismatch', [
                'header_present' => $signature !== '',
                'body_bytes'     => strlen($raw),
            ]);
            // Matches the WhatsApp fix (PROC-018): 401 triggered unbounded
            // Meta redeliveries in past incidents on webhook-based APIs.
            // 202 acknowledges without asking for retry, so a spoofed
            // caller can't turn the endpoint into a DoS vector against
            // our own PHP-FPM.
            return response('', 202);
        }

        $payload = $request->json()->all();

        if (! is_array($payload) || ($payload['object'] ?? '') !== 'page') {
            // Diagnostic (2026-09-17): messages arriving with object:null.
            // Log raw body + content type + a decode retry so we can see
            // whether Laravel's json parsing is missing something Meta is
            // legitimately sending, or the body itself is empty.
            $rawBody       = $request->getContent();
            $decodedRetry  = json_decode($rawBody, true);
            Log::channel('messenger')->info('Webhook non-page object — ignoring', [
                'object'        => $payload['object'] ?? null,
                'payload_keys'  => is_array($payload) ? array_keys($payload) : 'not-array',
                'content_type'  => $request->header('Content-Type'),
                'body_length'   => strlen($rawBody),
                'body_head'     => substr($rawBody, 0, 400),
                'retry_object'  => is_array($decodedRetry) ? ($decodedRetry['object'] ?? 'no-key') : 'not-array',
                'json_error'    => json_last_error_msg(),
            ]);
            return response('EVENT_RECEIVED', 200);
        }

        // Match a page id to a Wavadesk tenant. If Meta sent an event for
        // a page nobody in the DB has connected (leftover subscription,
        // dev-mode misconfig) we still 200 — retrying wouldn't help.
        foreach ($payload['entry'] ?? [] as $entry) {
            $pageId = (string) ($entry['id'] ?? '');
            if ($pageId === '') continue;

            $page = Page::withoutGlobalScope('tenant')
                ->where('page_id', $pageId)
                ->first();

            if (! $page) {
                Log::channel('messenger')->info('Webhook page not in DB — ignoring entry', [
                    'page_id' => $pageId,
                ]);
                continue;
            }

            ProcessMessengerIncomingMessage::dispatch($page->id, $entry);
        }

        // Meta's docs say any 2xx is fine but their SDK examples all use
        // this exact string, so ops greps line up if you use it too.
        return response('EVENT_RECEIVED', 200);
    }
}
