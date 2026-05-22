<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        $conv = $this->message->conversation;
        return [new PrivateChannel("tenant.{$conv->tenant_id}.conversation.{$conv->id}")];
    }

    public function broadcastAs(): string { return 'message.received'; }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id'              => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'direction'       => $this->message->direction,
                'author_type'     => $this->message->author_type,
                'type'            => $this->message->type,
                'body'            => $this->message->body,
                'media_url'       => $this->message->media_url,
                'media_mime'      => $this->message->media_mime,
                'ai_metadata'     => $this->message->ai_metadata,
                'status'          => $this->message->status,
                'sent_at'         => $this->message->sent_at?->toISOString(),
                'created_at'      => $this->message->created_at?->toISOString(),
            ],
        ];
    }
}
