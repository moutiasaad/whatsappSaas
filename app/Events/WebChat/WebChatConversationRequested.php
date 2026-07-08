<?php

namespace App\Events\WebChat;

use App\Models\WebChat\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebChatConversationRequested implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Conversation $conversation) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("webchat.tenant.{$this->conversation->tenant_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'webchat.conversation.requested';
    }

    public function broadcastWith(): array
    {
        $c = $this->conversation;

        return [
            'conversation' => [
                'uuid'             => $c->uuid,
                'tenant_id'        => $c->tenant_id,
                'widget_id'        => $c->widget_id,
                'visitor_id'       => $c->visitor_id,
                'status'           => $c->status,
                'visitor_name'     => $c->visitor_name,
                'visitor_email'    => $c->visitor_email,
                'page_url'         => $c->page_url,
                'last_activity_at' => $c->last_activity_at?->toISOString(),
                'created_at'       => $c->created_at?->toISOString(),
            ],
        ];
    }
}
