<?php

namespace App\Jobs;

use App\Models\WebChat\Conversation;
use App\Models\WebChat\Message;
use App\Services\AI\WebChatAutoReplyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWebChatIncomingMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 45;

    public function __construct(public int $conversationId, public int $messageId) {}

    public function handle(WebChatAutoReplyService $ai): void
    {
        $conversation = Conversation::withoutGlobalScope('tenant')->find($this->conversationId);
        $message      = Message::find($this->messageId);

        if (!$conversation || !$message) {
            Log::channel('webchat')->warning('AI job: conversation or message not found', [
                'conversation_id' => $this->conversationId,
                'message_id'      => $this->messageId,
            ]);
            return;
        }

        // Reload the tenant + AI settings under no tenant scope so this runs
        // safely from the queue worker context (no HTTP request bound).
        $conversation->loadMissing('tenant.aiSettings');

        $ai->maybeReply($conversation, $message);
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('webchat')->error('AI job failed permanently', [
            'conversation_id' => $this->conversationId,
            'message_id'      => $this->messageId,
            'error'           => $e->getMessage(),
        ]);
    }
}
