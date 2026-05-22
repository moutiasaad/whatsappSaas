<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationClaimed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Conversation $conversation, public User $agent) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("tenant.{$this->conversation->tenant_id}.conversation.{$this->conversation->id}"),
            new PrivateChannel("tenant.{$this->conversation->tenant_id}.pool"),
        ];
        if ($this->conversation->team_id) {
            $channels[] = new PrivateChannel("tenant.{$this->conversation->tenant_id}.team.{$this->conversation->team_id}.pool");
        }
        return $channels;
    }

    public function broadcastAs(): string { return 'conversation.claimed'; }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'agent_id'        => $this->agent->id,
            'agent_name'      => $this->agent->name,
        ];
    }
}
