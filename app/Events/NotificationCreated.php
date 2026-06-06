<?php

namespace App\Events;

use App\Models\AppNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AppNotification $notification) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->notification->user_id}")];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id'         => $this->notification->id,
            'type'       => $this->notification->type,
            'title'      => $this->notification->title,
            'body'       => $this->notification->body,
            'read_at'    => null,
            'created_at' => $this->notification->created_at?->toISOString(),
        ];
    }
}
