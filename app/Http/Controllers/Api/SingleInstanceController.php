<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppInstance;
use App\Services\WhatsApp\Gateway\EvolutionApiClient;
use Illuminate\Http\JsonResponse;

class SingleInstanceController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json($this->payload($this->instance()));
    }

    public function status(): JsonResponse
    {
        $instance = $this->instance();

        if (!$instance->gateway_instance_id) {
            return response()->json($this->payload($instance));
        }

        try {
            $gateway        = $this->gateway($instance);
            $details        = $gateway->fetchInstance($instance->gateway_instance_id);
            $gatewayStatus  = $gateway->getStatus($instance->gateway_instance_id);
            $phone          = $this->extractPhone($details);

            // The gateway reports 'close' (→ disconnected) both while waiting for a QR
            // scan AND after a real disconnect. Never downgrade from 'connecting' to
            // 'disconnected' based solely on a gateway poll — only explicit disconnect
            // actions should do that.
            $newStatus = ($instance->status === 'connecting' && $gatewayStatus === 'disconnected')
                ? 'connecting'
                : $gatewayStatus;

            $updates = ['status' => $newStatus, 'last_status_at' => now()];

            if ($phone) {
                $updates['phone_number'] = $phone;
            }

            if ($newStatus === 'connected') {
                $updates['qr_code'] = null;
            }
            // Do NOT call getQrCode() here — it regenerates the QR on the gateway,
            // which invalidates the code the user is currently scanning.

            $instance->update($updates);
            $instance->refresh();
        } catch (\Exception) {}

        return response()->json($this->payload($instance));
    }

    public function connect(): JsonResponse
    {
        $instance = $this->instance();
        $force    = request()->boolean('force', false);

        try {
            $gateway = $this->gateway($instance);

            // Return cached QR immediately unless caller is forcing a fresh scan
            if (!$force && $instance->qr_code && in_array($instance->status, ['connecting', 'qr_pending'], true)) {
                return response()->json(['qr_code' => $instance->qr_code, 'status' => $instance->status]);
            }

            // Force new QR: wipe the gateway instance so a fresh one is created below
            if ($force && $instance->gateway_instance_id) {
                try { $gateway->deleteInstance($instance->gateway_instance_id); } catch (\Throwable) {}
                sleep(2); // let the gateway fully tear down before recreating
                $instance->update([
                    'gateway_instance_id' => null,
                    'status'              => 'disconnected',
                    'qr_code'             => null,
                    'phone_number'        => null,
                    'webhook_enabled'     => false,  // force re-registration on new gateway instance
                    'webhook_url'         => null,
                ]);
                $instance->refresh();
            }

            if (!$instance->gateway_instance_id) {
                $name   = 'wa-' . $instance->tenant_id . '-' . $instance->id
                          . ($force ? '-' . time() : '');
                $result = $gateway->createInstance($name);
                $instance->update([
                    'gateway_instance_id' => $result['name']
                        ?? $result['instance']['instanceId']
                        ?? $result['instanceName']
                        ?? $name,
                ]);
                $instance->refresh();
            }

            $this->registerWebhook($gateway, $instance);

            $qr = $gateway->getQrCode($instance->gateway_instance_id);

            if ($qr === null) {
                if ($gateway->getStatus($instance->gateway_instance_id) === 'connected') {
                    $instance->update(['status' => 'connected', 'qr_code' => null, 'last_status_at' => now()]);
                    return response()->json(['status' => 'connected', 'qr_code' => null]);
                }

                $old = $instance->gateway_instance_id;
                try { $gateway->deleteInstance($old); } catch (\Throwable) {}
                sleep(1);
                $result = $gateway->createInstance($old);
                $new    = $result['name'] ?? $result['instance']['instanceId'] ?? $result['instanceName'] ?? $old;
                $instance->update(['gateway_instance_id' => $new, 'status' => 'disconnected', 'qr_code' => null]);
                $instance->refresh();
                $this->registerWebhook($gateway, $instance);
                $qr = $gateway->getQrCode($new);
            }

            $instance->update(['qr_code' => $qr, 'status' => 'connecting', 'last_status_at' => now()]);

            return response()->json(['qr_code' => $qr, 'status' => 'connecting']);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function disconnect(): JsonResponse
    {
        $instance = $this->instance();

        try {
            $this->gateway($instance)->logout($instance->gateway_instance_id);
        } catch (\Exception) {}

        $instance->update(['status' => 'disconnected', 'qr_code' => null]);

        return response()->json(['status' => 'disconnected', 'message' => 'Disconnected.']);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function instance(): WhatsAppInstance
    {
        $instance = WhatsAppInstance::where('tenant_id', auth()->user()->tenant_id)
            ->orderBy('id')
            ->first();

        abort_unless($instance, 404, 'No WhatsApp instance configured for your account.');

        return $instance;
    }

    private function payload(WhatsAppInstance $instance): array
    {
        return [
            'id'             => $instance->id,
            'name'           => $instance->name,
            'status'         => $instance->status,
            'phone_number'   => $instance->phone_number,
            'qr_code'        => $instance->qr_code,
            'last_status_at' => $instance->last_status_at?->toISOString(),
        ];
    }

    private function gateway(WhatsAppInstance $instance): EvolutionApiClient
    {
        return new EvolutionApiClient(
            $instance->effectiveGatewayUrl(),
            $instance->effectiveGatewayApiKey()
        );
    }

    private function registerWebhook(EvolutionApiClient $gateway, WhatsAppInstance $instance): void
    {
        $base = rtrim((string) config('services.whatsapp.webhook_base_url', config('app.url')), '/');
        $url  = "{$base}/api/webhooks/whatsapp/{$instance->webhook_token}";

        if ($instance->webhook_enabled && $instance->webhook_url === $url) {
            return;
        }

        try {
            $gateway->setWebhook($instance->gateway_instance_id, $url, [
                'qrcodeUpdated'     => true,
                'messagesUpsert'    => true,
                'messagesUpdated'   => true,
                'sendMessage'       => true,
                'contactsUpsert'    => true,
                'connectionUpdated' => true,
                'statusInstance'    => true,
            ]);
            $instance->update(['webhook_enabled' => true, 'webhook_url' => $url, 'webhook_last_set' => now()]);
        } catch (\Throwable) {}
    }

    private function extractPhone(array $details): ?string
    {
        $raw = data_get($details, 'ownerJid')
            ?? data_get($details, 'instance.ownerJid')
            ?? data_get($details, 'number')
            ?? data_get($details, 'instance.number')
            ?? data_get($details, 'me.jid')
            ?? data_get($details, 'instance.me.jid')
            ?? data_get($details, 'me.id');

        if (!is_string($raw) || trim($raw) === '') return null;

        $raw = preg_replace('/[^0-9@]/', '', $raw);
        return str_contains($raw, '@') ? preg_replace('/@.*$/', '', $raw) : ($raw ?: null);
    }
}
