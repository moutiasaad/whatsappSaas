<?php

namespace App\Jobs;

use App\Events\InstanceStatusChanged;
use App\Models\WhatsAppInstance;
use App\Services\WhatsApp\Gateway\EvolutionApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PollInstanceHealth implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        WhatsAppInstance::withoutGlobalScope('tenant')
            ->whereNotIn('status', ['banned'])
            ->whereNotNull('gateway_instance_id')
            ->get()
            ->each(function (WhatsAppInstance $instance) {
                try {
                    $gateway   = new EvolutionApiClient($instance->effectiveGatewayUrl(), $instance->effectiveGatewayApiKey());
                    $newStatus = $gateway->getStatus($instance->gateway_instance_id);

                    if ($newStatus === 'disconnected' && $instance->status === 'connected') {
                        // Keep the last known connected state if the gateway check only blipped.
                        return;
                    }

                    $changed = $newStatus !== $instance->status;
                    $instance->update(['status' => $newStatus, 'last_status_at' => now()]);

                    if ($changed) {
                        broadcast(new InstanceStatusChanged($instance->fresh()));
                    }
                } catch (\Exception) {
                    // Do not force-disconnect on transient gateway/network errors.
                    // Let the existing status stand until the gateway explicitly reports a state change.
                }
            });
    }
}
