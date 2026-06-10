<?php

namespace App\Jobs;

use App\Events\MessageReceived;
use App\Models\Customer;
use App\Models\Message;
use App\Models\WebhookEvent;
use App\Services\WhatsApp\Gateway\EvolutionApiClient;
use App\Services\AI\AutoReplyService;
use App\Services\Conversations\ConversationService;
use App\Models\ReservationSetting;
use App\Services\Reservation\ReservationBotService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessIncomingMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(private WebhookEvent $webhookEvent) {}

    public function handle(ConversationService $convService, AutoReplyService $aiService): void
    {
        $payload   = $this->webhookEvent->payload;
        $instance  = $this->webhookEvent->instance;
        $eventType = $this->normalizedEvent($payload);

        Log::channel('whatsapp')->info('Job: processing', [
            'webhook_event_id' => $this->webhookEvent->id,
            'event_type'       => $eventType,
            'instance_id'      => $instance?->id,
        ]);

        if (!$instance) {
            Log::channel('whatsapp')->error('Job: no instance', ['webhook_event_id' => $this->webhookEvent->id]);
            return;
        }

        match ($eventType) {
            'message.received',
            'messages.upsert',
            'messagesupsert'     => $this->handleMessage($payload, $instance, $convService, $aiService),
            'connection.update',
            'connectionupdated',
            'status.instance',
            'statusinstance',
            'qrcode.updated',
            'qrcodeupdated'      => $this->handleConnectionUpdate($payload, $instance),
            default              => Log::channel('whatsapp')->debug('Job: unhandled event', ['event' => $eventType]),
        };

        $this->webhookEvent->update(['processed_at' => now()]);
    }

    private function handleMessage(array $payload, $instance, ConversationService $convService, AutoReplyService $aiService): void
    {
        $msg     = $this->extractMessage($payload);
        $contact = $this->extractContact($payload, $msg);
        $from    = $this->extractFrom($payload, $msg);

        // Resolve @lid (Meta linked device ID) to a real phone number via gateway contact lookup
        if ($from && str_contains($from, '@lid')) {
            $resolved = $this->resolveLidToPhone($from, $instance);
            if ($resolved) {
                // Patch any existing customer record keyed by the LID JID
                Customer::withoutGlobalScope('tenant')
                    ->where('tenant_id', $instance->tenant_id)
                    ->where('phone_e164', $from)
                    ->update(['phone_e164' => $resolved]);
                $from = $resolved;
            }
        }

        if (!$from) {
            Log::channel('whatsapp')->warning('Job: could not extract sender', [
                'instance_id'  => $instance->id,
                'msg_keys'     => array_keys($msg),
                'payload_keys' => array_keys($payload),
            ]);
            return;
        }

        if ($this->isFromMe($msg)) {
            Log::channel('whatsapp')->debug('Job: skipping outbound (fromMe)', ['from' => $from]);
            return;
        }

        $conversation = $convService->findOrCreateForIncoming(
            $instance, $from, $contact['name'] ?? ''
        );

        if ($conversation->isClosed()) {
            $convService->reopenIfClosed($conversation);
            $conversation->refresh();
        }

        // Deduplication
        $extId = $this->extractExternalId($payload, $msg);
        if ($extId && Message::where('tenant_id', $instance->tenant_id)->where('external_message_id', $extId)->exists()) {
            return;
        }

        $body = $this->extractText($msg);
        $mediaUrl = $this->extractMediaUrl($msg);
        $mediaMime = $this->extractMediaMime($msg);
        $type = $this->extractType($msg, $body, $mediaUrl);

        $message = Message::create([
            'conversation_id'    => $conversation->id,
            'tenant_id'          => $instance->tenant_id,
            'direction'          => 'in',
            'author_type'        => 'customer',
            'external_message_id'=> $extId,
            'type'               => $type,
            'body'               => $body,
            'media_url'          => $mediaUrl,
            'media_mime'         => $mediaMime,
            'status'             => 'delivered',
            'sent_at'            => $this->extractTimestamp($payload, $msg),
        ]);

        $conversation->increment('unread_count');
        $conversation->update([
            'last_message_at'      => $message->sent_at ?? now(),
            'last_message_preview' => Str::limit($message->body ?: ($message->media_url ? ucfirst((string) $message->type) : ''), 200),
        ]);

        broadcast(new MessageReceived($message))->toOthers();

        // Reservation bot intercepts text messages when the module is active on this tenant's plan
        if ($body !== null && $body !== '') {
            $resvSettings = ReservationSetting::where('tenant_id', $instance->tenant_id)
                ->where('is_active', true)
                ->first();

            if ($resvSettings && $this->tenantHasReservations($instance->tenant_id)) {
                // Only intercept if the bot has an active session or the message matches a trigger keyword
                $botHandled = (new ReservationBotService())->handle(
                    $resvSettings, $instance, $from, $body, $conversation->id
                );

                if ($botHandled) {
                    return;
                }
            }
        }

        $aiService->maybeReply($conversation->fresh(), $message);
    }

    private function handleConnectionUpdate(array $payload, $instance): void
    {
        $state = $this->extractConnectionState($payload);

        $status = match($state) {
            'open', 'online', 'connected'            => 'connected',
            'connecting', 'qr', 'qrcode', 'pairing'  => 'connecting',
            default                                   => 'disconnected',
        };

        // Don't downgrade a connecting instance to disconnected from a webhook event —
        // 'close' state fires on connection events before the QR is scanned.
        if ($instance->status === 'connecting' && $status === 'disconnected') {
            $status = 'connecting';
        }

        $updates = ['status' => $status, 'last_status_at' => now()];

        // Extract fresh QR from qrcodeUpdated webhook events
        if ($status === 'connecting') {
            $qr = data_get($payload, 'data.qr.base64')
               ?? data_get($payload, 'data.base64')
               ?? data_get($payload, 'qr.base64')
               ?? data_get($payload, 'qr')
               ?? data_get($payload, 'base64');
            if (is_string($qr) && trim($qr) !== '') {
                $updates['qr_code'] = str_starts_with($qr, 'data:') ? $qr : 'data:image/png;base64,' . preg_replace('/\s+/', '', $qr);
            }
        } elseif ($status === 'connected') {
            $updates['qr_code'] = null;
        }

        $instance->update($updates);
        broadcast(new \App\Events\InstanceStatusChanged($instance->fresh()));
    }

    private function normalizedEvent(array $payload): string
    {
        return strtolower(str_replace([' ', '_'], ['.', '.'], (string) (
            data_get($payload, 'event')
            ?? data_get($payload, 'type')
            ?? data_get($payload, 'eventType')
            ?? data_get($payload, 'name')
            ?? $this->webhookEvent->event_type
            ?? 'unknown'
        )));
    }

    private function extractMessage(array $payload): array
    {
        // iStoreBox wraps every event in a list: "data": [{...}] — unwrap to first element
        $data = data_get($payload, 'data');
        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            $data = $data[0];
        }

        $candidates = [
            $data,                                // iStoreBox: data[0] contains key, pushName, message, ...
            data_get($payload, 'message'),
            data_get($payload, 'data.message'),
            data_get($payload, 'messages.0'),
            data_get($payload, 'data.messages.0'),
            data_get($payload, 'messageData'),
            $payload,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate) && !empty($candidate)) {
                return $candidate;
            }
        }

        return [];
    }

    private function extractContact(array $payload, array $msg): array
    {
        $name = data_get($payload, 'contact.name')
            ?? data_get($payload, 'data.contact.name')
            ?? data_get($msg, 'pushName')
            ?? data_get($msg, 'senderName')
            ?? data_get($msg, 'contact.name')
            ?? '';

        return ['name' => is_string($name) ? trim($name) : ''];
    }

    private function resolveLidToPhone(string $lidJid, $instance): ?string
    {
        try {
            $gateway = new EvolutionApiClient(
                $instance->effectiveGatewayUrl(),
                $instance->effectiveGatewayApiKey()
            );
            $contacts = $gateway->findContacts($instance->gateway_instance_id, $lidJid);
            foreach ($contacts as $contact) {
                $jid = (string) data_get($contact, 'remoteJid', '');
                if (str_contains($jid, '@s.whatsapp.net') || str_contains($jid, '@c.us')) {
                    $digits = preg_replace('/\D+/', '', preg_replace('/@\S+/', '', $jid) ?? $jid);
                    if ($digits !== '') {
                        Log::channel('whatsapp')->info('Job: resolved @lid to phone', [
                            'lid' => $lidJid, 'phone' => $digits,
                        ]);
                        return $digits;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->warning('Job: @lid resolution failed', [
                'lid' => $lidJid, 'error' => $e->getMessage(),
            ]);
        }
        return null;
    }

    private function extractFrom(array $payload, array $msg): ?string
    {
        $raw = data_get($msg, 'from')
            ?? data_get($msg, 'key.remoteJid')
            ?? data_get($msg, 'remoteJid')
            ?? data_get($msg, 'keyRemoteJid')          // iStoreBox flat format
            ?? data_get($payload, 'from')
            ?? data_get($payload, 'data.from')
            ?? data_get($payload, 'key.remoteJid')
            ?? data_get($payload, 'data.keyRemoteJid'); // iStoreBox flat format

        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $raw = trim($raw);

        // Non-phone JIDs — groups, broadcasts (WhatsApp status updates), newsletters
        if (str_contains($raw, '@g.us') || str_contains($raw, '@broadcast') || str_contains($raw, '@newsletter')) {
            return null;
        }

        // LID JIDs (Meta linked IDs) must be preserved as-is — they are not phone numbers
        if (str_contains($raw, '@lid')) {
            return $raw;
        }

        // Strip @s.whatsapp.net suffix, then the :device multi-device suffix (e.g. :5),
        // then keep digits only.  Without the :device strip, "21265182831:5@s.whatsapp.net"
        // yields "212651828315" instead of "21265182831", breaking cache key consistency.
        // Do NOT fall back to $raw after digit stripping — if nothing remains the source
        // was not a phone number (e.g. the literal string "status").
        $raw    = preg_replace('/@.*/', '', $raw) ?? $raw;   // strip @s.whatsapp.net
        $raw    = preg_replace('/:\d+$/', '', $raw) ?? $raw;  // strip :device (multi-device)
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        return $digits !== '' ? $digits : null;
    }

    private function isFromMe(array $msg): bool
    {
        return (bool) data_get($msg, 'fromMe')
            || (bool) data_get($msg, 'key.fromMe')
            || (bool) data_get($msg, 'keyFromMe');
    }

    private function extractExternalId(array $payload, array $msg): ?string
    {
        // Prefer the numeric gateway ID (needed for /chat/readMessages which expects integers)
        $numericId = data_get($msg, 'id')
            ?? data_get($payload, 'id')
            ?? data_get($payload, 'data.id');

        if (is_numeric($numericId)) {
            return (string) (int) $numericId;
        }

        // Fall back to WhatsApp string keyId for deduplication only
        $stringId = data_get($msg, 'key.id')
            ?? data_get($msg, 'keyId')
            ?? data_get($payload, 'data.keyId');

        return is_string($stringId) || is_int($stringId) ? (string) $stringId : null;
    }

    private function extractText(array $msg): ?string
    {
        $candidates = [
            data_get($msg, 'text'),
            data_get($msg, 'body'),
            data_get($msg, 'content.text'),
            data_get($msg, 'message.conversation'),
            data_get($msg, 'message.extendedTextMessage.text'),
            data_get($msg, 'message.imageMessage.caption'),
            data_get($msg, 'message.videoMessage.caption'),
            data_get($msg, 'message.documentMessage.caption'),
            data_get($msg, 'extendedTextMessage.text'),
            data_get($msg, 'imageMessage.caption'),
            data_get($msg, 'videoMessage.caption'),
            data_get($msg, 'documentMessage.caption'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    private function extractMediaUrl(array $msg): ?string
    {
        $candidates = [
            data_get($msg, 'media_url'),
            data_get($msg, 'mediaUrl'),
            data_get($msg, 'content.url'),
            data_get($msg, 'message.imageMessage.url'),
            data_get($msg, 'message.videoMessage.url'),
            data_get($msg, 'message.documentMessage.url'),
            data_get($msg, 'message.audioMessage.url'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    private function extractMediaMime(array $msg): ?string
    {
        $candidates = [
            data_get($msg, 'media_mime'),
            data_get($msg, 'mediaMime'),
            data_get($msg, 'content.mimetype'),
            data_get($msg, 'message.imageMessage.mimetype'),
            data_get($msg, 'message.videoMessage.mimetype'),
            data_get($msg, 'message.documentMessage.mimetype'),
            data_get($msg, 'message.audioMessage.mimetype'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    private function extractType(array $msg, ?string $body, ?string $mediaUrl): string
    {
        $type = strtolower((string) (
            data_get($msg, 'type')
            ?? data_get($msg, 'messageType')
            ?? data_get($msg, 'contentType')
            ?? ''
        ));

        if ($type !== '') {
            return match (true) {
                str_contains($type, 'image') => 'image',
                str_contains($type, 'video') => 'video',
                str_contains($type, 'audio') => 'audio',
                str_contains($type, 'document') => 'document',
                default => $body ? 'text' : ($mediaUrl ? 'document' : 'text'),
            };
        }

        return $body ? 'text' : ($mediaUrl ? 'document' : 'text');
    }

    private function extractTimestamp(array $payload, array $msg): Carbon
    {
        $timestamp = data_get($msg, 'messageTimestamp')
            ?? data_get($msg, 'timestamp')
            ?? data_get($payload, 'timestamp')
            ?? data_get($payload, 'messageTimestamp');

        if (is_numeric($timestamp)) {
            return Carbon::createFromTimestamp((int) $timestamp);
        }

        return now();
    }

    private function extractConnectionState(array $payload): string
    {
        // CodeChat qrcode.updated events have no state field — instance is connecting
        if (data_get($payload, 'data.qrcode') !== null) {
            return 'connecting';
        }

        return strtolower((string) (
            data_get($payload, 'state')
            ?? data_get($payload, 'connectionStatus')
            ?? data_get($payload, 'status')
            ?? data_get($payload, 'data.state')
            ?? data_get($payload, 'data.connectionStatus')
            ?? data_get($payload, 'data.status')  // CodeChat status.instance event
            ?? ''
        ));
    }

    private function tenantHasReservations(int $tenantId): bool
    {
        static $cache = [];
        if (!isset($cache[$tenantId])) {
            $tenant = \App\Models\Tenant::with('plan')->find($tenantId);
            $cache[$tenantId] = $tenant && $tenant->plan && $tenant->plan->reservations_enabled;
        }
        return $cache[$tenantId];
    }

    public function failed(\Throwable $e): void
    {
        $this->webhookEvent->update(['error' => $e->getMessage()]);
        Log::error("ProcessIncomingMessage failed [{$this->webhookEvent->id}]: {$e->getMessage()}");
    }
}
