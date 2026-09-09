<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingMessage;
use App\Models\WebhookEvent;
use App\Models\WhatsAppInstance;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function handle(Request $request, string $token): Response
    {
        Log::channel('whatsapp')->info('Webhook hit', [
            'token_prefix' => substr($token, 0, 8) . '...',
            'ip'           => $request->ip(),
            'event'        => $this->eventType($request->all()),
        ]);

        $instance = WhatsAppInstance::withoutGlobalScope('tenant')
            ->where('webhook_token', $token)
            ->first();

        if (!$instance) {
            Log::channel('whatsapp')->warning('Webhook: no instance matched token', [
                'token_prefix' => substr($token, 0, 8) . '...',
            ]);
            return response('', 401);
        }

        if (!$this->verifySignature($request, $instance)) {
            Log::channel('whatsapp')->warning('Webhook: signature mismatch', ['instance_id' => $instance->id]);
            // PROC-018 hot-fix: 401 is retryable per the gateway's redelivery
            // policy, so one bad signature turned into an unbounded flood that
            // pegged PHP-FPM (Sep 9 incident: 788 × 401 in a single day, load
            // 12.8 on a 2-core box). Acknowledge with 202 so the event is
            // dropped without triggering redelivery.
            return response('', 202);
        }

        $payload = $request->all();

        Log::channel('whatsapp')->info('Webhook accepted — dispatching job', [
            'instance_id' => $instance->id,
            'instance'    => $instance->name,
            'event_type'  => $this->eventType($payload),
        ]);

        $event = WebhookEvent::create([
            'tenant_id'  => $instance->tenant_id,
            'instance_id'=> $instance->id,
            'event_type' => $this->eventType($payload),
            'payload'    => $payload,
        ]);

        ProcessIncomingMessage::dispatch($event)->onQueue('whatsapp');

        return response('', 200);
    }

    private function verifySignature(Request $request, WhatsAppInstance $instance): bool
    {
        // PROC-018: fail-closed only for instances whose gateway has been told
        // about the secret (webhook_last_set stamped). A null webhook_last_set
        // means we haven't advertised the secret yet — stay fail-open.
        if (!$instance->webhook_secret || !$instance->webhook_last_set) {
            return true;
        }

        $signature = $request->header('X-Gateway-Signature', '');

        // The iStoreBox/Evolution build in production silently accepts every
        // hmac_secret / webhook_secret / secret field name EvolutionApiClient::
        // setWebhook() sends but never adds a signature header to its posts —
        // verified against GET /webhook/find/{name}, which returns only url,
        // enabled, events, instanceId. An empty header on this gateway means
        // "cannot sign", not "forged", so fall back to the 64-char bearer
        // token in the URL path. If a future gateway version starts signing,
        // this branch stops firing and hash_equals takes over automatically.
        if ($signature === '') {
            return true;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $instance->webhook_secret);

        return hash_equals($expected, $signature);
    }

    private function eventType(array $payload): string
    {
        $event = data_get($payload, 'event')
            ?? data_get($payload, 'type')
            ?? data_get($payload, 'eventType')
            ?? data_get($payload, 'name')
            ?? 'unknown';

        return strtolower(trim((string) $event));
    }
}
