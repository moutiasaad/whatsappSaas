<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Conversation $conversation,
        public User $agent,
        public string $presence
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->conversation->tenant_id}.conversation.{$this->conversation->id}")];
    }

    public function broadcastAs(): string
    {
        return 'agent.typing';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'presence'        => $this->presence,
            'agent'           => [
                'id'   => $this->agent->id,
                'name' => $this->agent->name,
            ],
        ];
    }
}
