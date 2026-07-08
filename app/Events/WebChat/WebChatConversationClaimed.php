<?php

namespace App\Events\WebChat;

use App\Models\User;
use App\Models\WebChat\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebChatConversationClaimed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Conversation $conversation, public User $agent) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("webchat.tenant.{$this->conversation->tenant_id}"),
            new PrivateChannel("webchat.conversation.{$this->conversation->uuid}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'webchat.conversation.claimed';
    }

    public function broadcastWith(): array
    {
        $c = $this->conversation;

        return [
            'conversation' => [
                'uuid'             => $c->uuid,
                'tenant_id'        => $c->tenant_id,
                'status'           => $c->status,
                'claimed_by'       => $c->claimed_by,
                'claimed_at'       => $c->claimed_at?->toISOString(),
                'last_activity_at' => $c->last_activity_at?->toISOString(),
            ],
            'agent' => [
                'id'   => $this->agent->id,
                'name' => $this->agent->name,
            ],
        ];
    }
}
