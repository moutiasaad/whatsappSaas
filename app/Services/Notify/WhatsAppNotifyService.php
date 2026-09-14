<?php

namespace App\Services\Notify;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\WhatsAppInstance;
use App\Services\Conversations\ConversationService;
use App\Services\WhatsApp\Gateway\EvolutionApiClient;
use Illuminate\Support\Facades\Log;

class WhatsAppNotifyService
{
    public const MAX_MESSAGE_LENGTH = 4000;

    public function __construct(private ConversationService $conversations) {}

    public function send(Tenant $tenant, string $message): array
    {
        $settings = $this->settings($tenant);

        if (!$settings['enabled']) {
            return $this->fail('service_disabled', 'Notification service is disabled for this workspace.', 403);
        }

        $body = trim($message);
        if ($body === '') {
            return $this->fail('empty_message', 'Message body cannot be empty.', 422);
        }
        if (mb_strlen($body) > self::MAX_MESSAGE_LENGTH) {
            return $this->fail('message_too_long', 'Message exceeds ' . self::MAX_MESSAGE_LENGTH . ' characters.', 422);
        }

        $identifier = $this->normalize((string) $settings['admin_phone']);
        if ($identifier === '') {
            return $this->fail('no_admin_phone', 'No admin phone number configured. Set one in /tenant-admin/notify-service.', 422);
        }

        $instance = $this->connectedInstance($tenant);
        if (!$instance) {
            return $this->fail('no_instance', 'No connected WhatsApp instance for this workspace.', 422);
        }

        $prefix = trim((string) ($settings['prefix'] ?? ''));
        $delivered = $prefix !== '' ? "{$prefix} {$body}" : $body;

        try {
            $gateway = new EvolutionApiClient(
                $instance->effectiveGatewayUrl(),
                $instance->effectiveGatewayApiKey()
            );
            $result = $gateway->sendText($instance->gateway_instance_id, $identifier, $delivered);
        } catch (\Throwable $e) {
            Log::error('WhatsAppNotifyService: gateway error', [
                'tenant' => $tenant->id,
                'error'  => $e->getMessage(),
            ]);
            return $this->fail('gateway_error', 'Failed to deliver notification: ' . $e->getMessage(), 502);
        }

        $messageId = $this->logMessage($instance, $identifier, $delivered, $result);

        return [
            'ok'         => true,
            'to'         => $identifier,
            'message_id' => $messageId,
        ];
    }

    public function settings(Tenant $tenant): array
    {
        $all = $tenant->settings ?? [];
        $notify = is_array($all['notify'] ?? null) ? $all['notify'] : [];

        return array_merge([
            'enabled'     => false,
            'admin_phone' => '',
            'prefix'      => '',
        ], $notify);
    }

    public function saveSettings(Tenant $tenant, array $settings): void
    {
        $all = $tenant->settings ?? [];
        $all['notify'] = array_merge($this->settings($tenant), $settings);
        $tenant->settings = $all;
        $tenant->save();
    }

    public function recentMessages(Tenant $tenant, int $limit = 20)
    {
        return Message::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('metadata->source', 'notify')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    private function connectedInstance(Tenant $tenant): ?WhatsAppInstance
    {
        return WhatsAppInstance::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'connected')
            ->orderBy('id')
            ->first();
    }

    private function logMessage(WhatsAppInstance $instance, string $phone, string $body, $gatewayResult): ?int
    {
        try {
            $conversation = $this->conversations->findOrCreateForIncoming($instance, $phone, '');

            if ($conversation->isClosed()) {
                $conversation->update([
                    'state'           => 'pool',
                    'owner_agent_id'  => null,
                    'claimed_at'      => null,
                    'last_message_at' => now(),
                ]);
            } else {
                $conversation->update(['last_message_at' => now()]);
            }

            $externalId = data_get($gatewayResult, 'key.id')
                       ?? data_get($gatewayResult, 'id')
                       ?? null;

            $message = Message::withoutGlobalScope('tenant')->create([
                'conversation_id'     => $conversation->id,
                'tenant_id'           => $instance->tenant_id,
                'direction'           => 'out',
                'author_type'         => 'system',
                'author_id'           => null,
                'external_message_id' => $externalId,
                'type'                => 'text',
                'body'                => $body,
                'status'              => 'sent',
                'sent_at'             => now(),
                'metadata'            => ['source' => 'notify'],
            ]);

            return $message->id;
        } catch (\Throwable $e) {
            Log::warning('WhatsAppNotifyService: failed to log message', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function normalize(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function fail(string $error, string $message, int $status, array $extra = []): array
    {
        return array_merge([
            'ok'      => false,
            'error'   => $error,
            'message' => $message,
            'status'  => $status,
        ], $extra);
    }
}
