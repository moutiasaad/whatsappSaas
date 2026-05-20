<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationReopened implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Conversation $conversation) {}

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel("tenant.{$this->conversation->tenant_id}.pool")];
        if ($this->conversation->team_id) {
            $channels[] = new PrivateChannel("tenant.{$this->conversation->tenant_id}.team.{$this->conversation->team_id}.pool");
        }
        return $channels;
    }

    public function broadcastAs(): string { return 'conversation.reopened'; }
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'customer_id'     => $this->conversation->customer_id,
        ];
    }
}
