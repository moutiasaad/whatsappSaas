<?php

namespace App\Events\Messenger;

use App\Models\Messenger\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * MessengerMessageSent
 *
 * Fires for every persisted Messenger message — inbound (visitor →
 * wavadesk), outbound agent reply, outbound bot reply, and echoes of
 * off-platform messages Meta sends back through the webhook. Mirrors
 * WebChatMessageSent so the shared inbox JS can react with the same
 * absorb() pattern.
 *
 * ShouldBroadcastNow (not ShouldBroadcast) — the ingest and reply
 * paths both already run on the queue, so we don't want to enqueue a
 * broadcast job that lags the underlying persistence by seconds.
 */
class MessengerMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        $conv = $this->message->conversation;

        return [
            new PrivateChannel("messenger.conversation.{$conv->uuid}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'messenger.message.sent';
    }

    public function broadcastWith(): array
    {
        $m    = $this->message;
        $conv = $m->conversation;

        return [
            'message' => [
                'id'                => $m->id,
                'conversation_id'   => $m->conversation_id,
                'conversation_uuid' => $conv->uuid,
                'sender_type'       => $m->sender_type,
                'sender_id'         => $m->sender_id,
                'body'              => $m->body,
                'attachments'       => $m->attachments,
                'created_at'        => $m->created_at?->toISOString(),
            ],
            'conversation' => [
                'uuid'             => $conv->uuid,
                'tenant_id'        => $conv->tenant_id,
                'status'           => $conv->status,
                'last_activity_at' => $conv->last_activity_at?->toISOString(),
            ],
        ];
    }
}
