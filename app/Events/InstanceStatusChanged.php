<?php

namespace App\Events;

use App\Models\WhatsAppInstance;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InstanceStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public WhatsAppInstance $instance) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->instance->tenant_id}.instances")];
    }

    public function broadcastAs(): string { return 'instance.status.changed'; }

    public function broadcastWith(): array
    {
        return [
            'id'           => $this->instance->id,
            'status'       => $this->instance->status,
            'phone_number' => $this->instance->phone_number,
            'status_color' => $this->instance->status_color,
        ];
    }
}
