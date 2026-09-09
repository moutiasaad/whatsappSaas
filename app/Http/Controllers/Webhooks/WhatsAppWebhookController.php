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
            return response('', 401);
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
        // PROC-018 phase 2: fail-closed only for instances whose gateway has
        // been told about the secret (webhook_last_set stamped). A backfilled
        // secret with a null webhook_last_set means the operator has not yet
        // reconfigured this instance's gateway — keeping the fail-open branch
        // for those rows avoids dropping every inbound event mid-deploy.
        // Run `php artisan whatsapp:reconfigure-webhooks` after the backfill
        // migration to close this branch for legacy instances.
        if (!$instance->webhook_secret || !$instance->webhook_last_set) {
            return true;
        }

        $signature = $request->header('X-Gateway-Signature', '');
        $expected  = hash_hmac('sha256', $request->getContent(), $instance->webhook_secret);

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
