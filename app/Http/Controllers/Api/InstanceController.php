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
        $tenantId  = auth()->user()->tenant_id;
        $instances = WhatsAppInstance::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get()
            ->makeHidden(['gateway_api_key', 'webhook_token']);

        return response()->json([
            'data'  => $instances,
            'stats' => [
                'connected'    => $instances->where('status', 'connected')->count(),
                'connecting'   => $instances->whereIn('status', ['connecting', 'qr_pending'])->count(),
                'disconnected' => $instances->whereIn('status', ['disconnected', 'error', 'banned'])->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user     = auth()->user();
        $tenantId = $user->tenant_id;

        $request->validate([
            'name'    => 'required|string|max:100',
            'team_id' => 'nullable|integer|exists:teams,id',
        ]);

        $instance = WhatsAppInstance::create([
            'tenant_id'      => $tenantId,
            'team_id'        => $request->team_id,
            'name'           => $request->name,
            'status'         => 'disconnected',
            'webhook_token'  => Str::random(64),
            'gateway_url'    => config('services.whatsapp.default_url'),
            'gateway_api_key'=> config('services.whatsapp.default_api_key'),
        ]);

        return response()->json($instance->makeHidden(['gateway_api_key', 'webhook_token']), 201);
    }

    public function show(WhatsAppInstance $instance): JsonResponse
    {
        $this->authorizeInstance($instance);

        return response()->json($instance->makeHidden(['gateway_api_key', 'webhook_token']));
    }

    public function connect(WhatsAppInstance $instance): JsonResponse
    {
        $this->authorizeInstance($instance);

        try {
            $gateway = $this->gateway($instance);

            // Return cached QR if still valid
            if ($instance->qr_code && in_array($instance->status, ['connecting', 'qr_pending'], true)) {
                return response()->json([
                    'qr_code' => $instance->qr_code,
                    'status'  => $instance->status,
                ]);
            }

            // Create gateway instance if it doesn't exist yet
            if (!$instance->gateway_instance_id) {
                $gatewayName = 'wa-' . $instance->tenant_id . '-' . $instance->id;
                $result      = $gateway->createInstance($gatewayName);
                $instance->update([
                    'gateway_instance_id' => $result['name']
                        ?? $result['instance']['instanceId']
                        ?? $result['instanceName']
                        ?? $gatewayName,
                ]);
                $instance->refresh();
            }

            $this->ensureWebhookRegistered($gateway, $instance);

            $qr = $gateway->getQrCode($instance->gateway_instance_id);

            if ($qr === null) {
                // Maybe it's already connected — check before assuming it's stuck
                $gatewayStatus = $gateway->getStatus($instance->gateway_instance_id);

                if ($gatewayStatus === 'connected') {
                    $instance->update(['status' => 'connected', 'qr_code' => null, 'last_status_at' => now()]);
                    return response()->json(['status' => 'connected', 'qr_code' => null]);
                }

                // Genuinely stuck — delete gateway instance and recreate for a fresh QR
                $oldGatewayId = $instance->gateway_instance_id;
                try {
                    $gateway->deleteInstance($oldGatewayId);
                } catch (\Throwable) {}

                sleep(1);

                $result = $gateway->createInstance($oldGatewayId);
                $newGatewayId = $result['name']
                    ?? $result['instance']['instanceId']
                    ?? $result['instanceName']
                    ?? $oldGatewayId;

                $instance->update([
                    'gateway_instance_id' => $newGatewayId,
                    'status'              => 'disconnected',
                    'qr_code'             => null,
                ]);
                $instance->refresh();

                $this->ensureWebhookRegistered($gateway, $instance);

                $qr = $gateway->getQrCode($newGatewayId);
            }

            $instance->update(['qr_code' => $qr, 'status' => 'connecting', 'last_status_at' => now()]);

            return response()->json(['qr_code' => $qr, 'status' => 'connecting']);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function status(WhatsAppInstance $instance): JsonResponse
    {
        $this->authorizeInstance($instance);

        try {
            $gateway     = $this->gateway($instance);
            $details     = $gateway->fetchInstance($instance->gateway_instance_id);
            $status      = $gateway->getStatus($instance->gateway_instance_id);
            $phoneNumber = $this->extractPhoneNumber($details);

            if ($status === 'connected') {
                $this->ensureWebhookRegistered($gateway, $instance);
            }

            $instance->update(array_filter([
                'status'         => $status,
                'phone_number'   => $phoneNumber ?: $instance->phone_number,
                'last_status_at' => now(),
            ], fn($v) => $v !== null));

            broadcast(new InstanceStatusChanged($instance->fresh()));

            return response()->json([
                'status'   => $status,
                'qr_code'  => $instance->qr_code,
                'instance' => $instance->fresh()->makeHidden(['gateway_api_key', 'webhook_token']),
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function disconnect(WhatsAppInstance $instance): JsonResponse
    {
        $this->authorizeInstance($instance);

        try {
            $this->gateway($instance)->logout($instance->gateway_instance_id);
        } catch (\Exception) {
            // Gateway may already be unreachable — continue with local update
        }

        $instance->update([
            'status'  => 'disconnected',
            'qr_code' => null,
        ]);

        broadcast(new InstanceStatusChanged($instance->fresh()));

        return response()->json(['message' => 'Instance disconnected.']);
    }

    public function destroy(WhatsAppInstance $instance): JsonResponse
    {
        $this->authorizeInstance($instance);

        if ($instance->gateway_instance_id) {
            try {
                $this->gateway($instance)->deleteInstance($instance->gateway_instance_id);
            } catch (\Exception) {
                // Continue even if gateway deletion fails
            }
        }

        $instance->delete();

        return response()->json(['message' => 'Instance deleted.']);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function authorizeInstance(WhatsAppInstance $instance): void
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless(
            (int) $instance->tenant_id === (int) $user->tenant_id,
            403,
            'This instance does not belong to your tenant.'
        );
    }

    private function gateway(WhatsAppInstance $instance): EvolutionApiClient
    {
        return new EvolutionApiClient(
            $instance->effectiveGatewayUrl(),
            $instance->effectiveGatewayApiKey()
        );
    }

    private function ensureWebhookRegistered(EvolutionApiClient $gateway, WhatsAppInstance $instance): void
    {
        $url = $this->webhookUrl($instance);

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
                'error'       => $e->getMessage(),
            ]);
            report($e);
        }
    }

    private function defaultWebhookEvents(): array
    {
        return [
            'qrcodeUpdated'              => true,
            'messagesUpsert'             => true,
            'messagesUpdated'            => true,
            'sendMessage'                => true,
            'contactsUpsert'             => true,
            'contactsUpdated'            => true,
            'chatsUpsert'                => true,
            'chatsUpdated'               => true,
            'presenceUpdated'            => true,
            'connectionUpdated'          => true,
            'statusInstance'             => true,
            'messagesSet'                => false,
            'contactsSet'                => true,
            'chatsSet'                   => false,
            'chatsDeleted'               => true,
            'groupsUpsert'               => true,
            'groupsUpdated'              => true,
            'groupsParticipantsUpdated'  => true,
            'refreshToken'               => true,
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
