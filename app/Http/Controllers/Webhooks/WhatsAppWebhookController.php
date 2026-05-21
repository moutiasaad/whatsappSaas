<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingMessage;
use App\Models\WebhookEvent;
use App\Models\WhatsAppInstance;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    public function handle(Request $request, string $token): Response
    {
        $instance = WhatsAppInstance::withoutGlobalScope('tenant')
            ->where('webhook_token', $token)
            ->first();

        if (!$instance || !$this->verifySignature($request, $instance)) {
            return response('', 401);
        }

        $payload = $request->all();

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
        if (!$instance->webhook_secret) return true;

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
