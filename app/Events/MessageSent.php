<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        $conv = $this->message->conversation;
        return [new PrivateChannel("tenant.{$conv->tenant_id}.conversation.{$conv->id}")];
    }

    public function broadcastAs(): string { return 'message.sent'; }

    public function broadcastWith(): array
    {
        return [
            'id'                   => $this->message->id,
            'status'               => $this->message->status,
            'external_message_id'  => $this->message->external_message_id,
        ];
    }
}
