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

class WebChatConversationClosed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Conversation $conversation, public ?User $actor = null) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("webchat.tenant.{$this->conversation->tenant_id}"),
            new PrivateChannel("webchat.conversation.{$this->conversation->uuid}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'webchat.conversation.closed';
    }

    public function broadcastWith(): array
    {
        $c = $this->conversation;

        return [
            'conversation' => [
                'uuid'      => $c->uuid,
                'tenant_id' => $c->tenant_id,
                'status'    => $c->status,
                'closed_by' => $c->closed_by,
                'closed_at' => $c->closed_at?->toISOString(),
            ],
            'actor' => $this->actor ? [
                'id'   => $this->actor->id,
                'name' => $this->actor->name,
            ] : [
                'id'   => null,
                'name' => 'visitor',
            ],
        ];
    }
}
