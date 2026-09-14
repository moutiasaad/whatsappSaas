<?php

namespace App\Events\WebChat;

use App\Models\WebChat\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        $conv = $this->message->conversation;

        return [
            new PrivateChannel("webchat.conversation.{$conv->uuid}"),
            new PresenceChannel("webchat.tenant.{$conv->tenant_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'webchat.message.sent';
    }

    public function broadcastWith(): array
    {
        $m    = $this->message;
        $conv = $m->conversation;

        return [
            'message' => [
                'id'              => $m->id,
                'conversation_id' => $m->conversation_id,
                'conversation_uuid' => $conv->uuid,
                'sender_type'     => $m->sender_type,
                'sender_id'       => $m->sender_id,
                'body'            => $m->body,
                // Visitors receive this event, so publish only the attachment
                // rather than the whole meta blob, which is ours to use.
                'attachment'      => $m->meta['attachment'] ?? null,
                'created_at'      => $m->created_at?->toISOString(),
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
