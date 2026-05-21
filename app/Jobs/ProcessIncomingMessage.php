<?php

namespace App\Jobs;

use App\Events\MessageReceived;
use App\Models\Message;
use App\Models\WebhookEvent;
use App\Services\AI\AutoReplyService;
use App\Services\Conversations\ConversationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessIncomingMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(private WebhookEvent $webhookEvent) {}

    public function handle(ConversationService $convService, AutoReplyService $aiService): void
    {
        $payload  = $this->webhookEvent->payload;
        $instance = $this->webhookEvent->instance;

        if (!$instance) {
            Log::warning("No instance for webhook event {$this->webhookEvent->id}");
            return;
        }

        match ($payload['event'] ?? '') {
            'message.received'   => $this->handleMessage($payload, $instance, $convService, $aiService),
            'connection.update'  => $this->handleConnectionUpdate($payload, $instance),
            default              => null,
        };

        $this->webhookEvent->update(['processed_at' => now()]);
    }

    private function handleMessage(array $payload, $instance, ConversationService $convService, AutoReplyService $aiService): void
    {
        $msg     = $payload['message'] ?? [];
        $contact = $payload['contact'] ?? [];
        $from    = $msg['from'] ?? '';

        if (!$from) return;

        $conversation = $convService->findOrCreateForIncoming(
            $instance, $from, $contact['name'] ?? ''
        );

        if ($conversation->isClosed()) {
            $convService->reopenIfClosed($conversation);
            $conversation->refresh();
        }

        // Deduplication
        $extId = $msg['id'] ?? null;
        if ($extId && Message::where('tenant_id', $instance->tenant_id)->where('external_message_id', $extId)->exists()) {
            return;
        }

        $message = Message::create([
            'conversation_id'    => $conversation->id,
            'tenant_id'          => $instance->tenant_id,
            'direction'          => 'in',
            'author_type'        => 'customer',
            'external_message_id'=> $extId,
            'type'               => $msg['type'] ?? 'text',
            'body'               => $msg['text'] ?? null,
            'media_url'          => $msg['media_url'] ?? null,
            'media_mime'         => $msg['media_mime'] ?? null,
            'status'             => 'delivered',
            'sent_at'            => isset($payload['timestamp'])
                                        ? Carbon::createFromTimestamp($payload['timestamp'])
                                        : now(),
        ]);

        $conversation->increment('unread_count');
        $conversation->update([
            'last_message_at'      => $message->sent_at ?? now(),
            'last_message_preview' => $message->body ?: ($message->media_url ? ucfirst((string) $message->type) : null),
        ]);

        broadcast(new MessageReceived($message))->toOthers();

        $aiService->maybeReply($conversation->fresh(), $message);
    }

    private function handleConnectionUpdate(array $payload, $instance): void
    {
        $status = match($payload['state'] ?? '') {
            'open'       => 'connected',
            'connecting' => 'connecting',
            default      => 'disconnected',
        };

        $instance->update(['status' => $status, 'last_status_at' => now()]);
        broadcast(new \App\Events\InstanceStatusChanged($instance->fresh()));
    }

    public function failed(\Throwable $e): void
    {
        $this->webhookEvent->update(['error' => $e->getMessage()]);
        Log::error("ProcessIncomingMessage failed [{$this->webhookEvent->id}]: {$e->getMessage()}");
    }
}
