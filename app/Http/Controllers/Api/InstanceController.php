<?php

namespace App\Http\Controllers\Api;

use App\Events\InstanceStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppInstance;
use App\Services\WhatsApp\Gateway\EvolutionApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InstanceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(WhatsAppInstance::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'           => 'required|string|max:100',
            'gateway'        => 'required|in:evolution,waha,cloud',
            'gateway_url'    => 'nullable|url',
            'gateway_api_key'=> 'nullable|string',
        ]);

        $instance = WhatsAppInstance::create([
            'name'            => $request->name,
            'gateway'         => $request->gateway,
            'gateway_url'     => $request->gateway_url ?: config('services.whatsapp.default_url'),
            'gateway_api_key' => $request->gateway_api_key ?: config('services.whatsapp.default_api_key'),
        ]);

        return response()->json($instance, 201);
    }

    public function connect(WhatsAppInstance $instance): JsonResponse
    {
        try {
            $gateway = $this->gateway($instance);

            if ($instance->qr_code && in_array($instance->status, ['connecting', 'qr_pending'], true)) {
                return response()->json([
                    'qr_code' => $instance->qr_code,
                    'status'  => $instance->status,
                ]);
            }

            if (!$instance->gateway_instance_id) {
                $gatewayName = 'wa-' . $instance->tenant_id . '-' . $instance->id;
                $result = $gateway->createInstance($gatewayName);
                $instance->update([
                    'gateway_instance_id' => $result['name'] ?? $result['instance']['instanceId'] ?? $result['instanceName'] ?? $gatewayName,
                ]);
                $instance->refresh();
            }

            // Register webhook immediately so the gateway can reach us as soon as the QR is scanned
            $this->ensureWebhookRegistered($gateway, $instance);

            $qr = $gateway->getQrCode($instance->gateway_instance_id);
            $instance->update(['qr_code' => $qr, 'status' => 'connecting', 'last_status_at' => now()]);

            return response()->json(['qr_code' => $qr, 'status' => 'connecting']);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function status(WhatsAppInstance $instance): JsonResponse
    {
        try {
            $gateway = $this->gateway($instance);
            $details = $gateway->fetchInstance($instance->gateway_instance_id);
            $status = $gateway->getStatus($instance->gateway_instance_id);
            $phoneNumber = $this->extractPhoneNumber($details);

            if ($status === 'connected') {
                $this->ensureWebhookRegistered($gateway, $instance);
            }

            $instance->update(array_filter([
                'status' => $status,
                'phone_number' => $phoneNumber ?: $instance->phone_number,
                'last_status_at' => now(),
            ], fn ($value) => $value !== null));
            broadcast(new InstanceStatusChanged($instance->fresh()));
            return response()->json([
                'status'   => $status,
                'qr_code'  => $instance->qr_code,
                'instance' => $instance->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function logout(WhatsAppInstance $instance): JsonResponse
    {
        $this->gateway($instance)->logout($instance->gateway_instance_id);
        $instance->update(['status' => 'disconnected', 'qr_code' => null]);
        return response()->json(['message' => 'Logged out.']);
    }

    private function gateway(WhatsAppInstance $instance): EvolutionApiClient
    {
        return new EvolutionApiClient($instance->effectiveGatewayUrl(), $instance->effectiveGatewayApiKey());
    }

    private function ensureWebhookRegistered(EvolutionApiClient $gateway, WhatsAppInstance $instance): void
    {
        $url = $this->webhookUrl($instance);

        // Skip if already registered with the current URL
        if ($instance->webhook_enabled && $instance->webhook_url === $url) {
            return;
        }

        try {
            $result = $gateway->setWebhook($instance->gateway_instance_id, $url, $this->defaultWebhookEvents());
            \Illuminate\Support\Facades\Log::channel('whatsapp')->info('Webhook registered', [
                'instance_id' => $instance->id,
                'url'         => $url,
                'response'    => $result,
            ]);
            $instance->update([
                'webhook_enabled'  => true,
                'webhook_url'      => $url,
                'webhook_last_set' => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::channel('whatsapp')->error('Webhook registration failed', [
                'instance_id' => $instance->id,
                'url'         => $url,
                'error'       => $e->getMessage(),
            ]);
            report($e);
        }
    }

    private function defaultWebhookEvents(): array
    {
        return [
            'qrcodeUpdated' => true,
            'messagesSet' => false,
            'messagesUpsert' => true,
            'messagesUpdated' => true,
            'sendMessage' => true,
            'contactsSet' => true,
            'contactsUpsert' => true,
            'contactsUpdated' => true,
            'chatsSet' => false,
            'chatsUpsert' => true,
            'chatsUpdated' => true,
            'chatsDeleted' => true,
            'presenceUpdated' => true,
            'groupsUpsert' => true,
            'groupsUpdated' => true,
            'groupsParticipantsUpdated' => true,
            'connectionUpdated' => true,
            'statusInstance' => true,
            'refreshToken' => true,
        ];
    }

    private function webhookUrl(WhatsAppInstance $instance): string
    {
        $base = rtrim((string) config('services.whatsapp.webhook_base_url', config('app.url')), '/');

        return "{$base}/api/webhooks/whatsapp/{$instance->webhook_token}";
    }

    private function extractPhoneNumber(array $details): ?string
    {
        $candidate = data_get($details, 'ownerJid')
            ?? data_get($details, 'instance.ownerJid')
            ?? data_get($details, 'number')
            ?? data_get($details, 'instance.number')
            ?? data_get($details, 'me.jid')
            ?? data_get($details, 'instance.me.jid')
            ?? data_get($details, 'me.id')
            ?? data_get($details, 'instance.me.id');

        if (!is_string($candidate) || trim($candidate) === '') {
            return null;
        }

        $candidate = preg_replace('/[^0-9@]/', '', $candidate);
        if (!$candidate) {
            return null;
        }

        return str_contains($candidate, '@')
            ? preg_replace('/@.*$/', '', $candidate)
            : $candidate;
    }
}
