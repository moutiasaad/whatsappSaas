<?php

namespace App\Jobs;

use App\Models\Messenger\Conversation;
use App\Models\Messenger\Message;
use App\Services\AI\MessengerAutoReplyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ProcessMessengerAiReply
 *
 * Dispatched by ProcessMessengerIncomingMessage right after a new
 * visitor message lands on disk. Split from the ingest job so a slow
 * Claude call never delays the next webhook's ingestion, and so an
 * AI failure never rolls back the inbound persistence — the message
 * stays visible in the inbox even when the AI can't answer.
 *
 * `tries=2`: one retry on transient failure (Claude 5xx, network
 * blip). More would keep the visitor waiting past the point of a
 * useful auto-reply.
 */
class ProcessMessengerAiReply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 45;

    public function __construct(public int $conversationId, public int $messageId) {}

    public function handle(MessengerAutoReplyService $ai): void
    {
        $conversation = Conversation::withoutGlobalScope('tenant')->find($this->conversationId);
        $message      = Message::find($this->messageId);

        if (! $conversation || ! $message) {
            Log::channel('messenger')->warning('AI job: conversation or message not found', [
                'conversation_id' => $this->conversationId,
                'message_id'      => $this->messageId,
            ]);
            return;
        }

        // Hydrate the tenant + settings under no tenant scope so this
        // runs from the queue worker context (no HTTP request bound).
        $conversation->loadMissing('tenant.aiSettings');

        $ai->maybeReply($conversation, $message);
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('messenger')->error('AI job failed permanently', [
            'conversation_id' => $this->conversationId,
            'message_id'      => $this->messageId,
            'error'           => $e->getMessage(),
        ]);
    }
}
