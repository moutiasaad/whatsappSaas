<?php

namespace App\Jobs;

use App\Events\InstanceStatusChanged;
use App\Models\WhatsAppInstance;
use App\Services\WhatsApp\ConnectionFlapGuard;
use App\Services\WhatsApp\Gateway\EvolutionApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class PollInstanceHealth implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Consecutive polls that must agree the socket is down before we demote an
     * instance that we currently believe is connected. The job runs every two
     * minutes, so a real drop shows up in the dashboard within ~4 minutes while
     * a single odd reply from the gateway is absorbed.
     */
    private const DISCONNECT_STRIKES = 2;

    public function handle(ConnectionFlapGuard $guard): void
    {
        WhatsAppInstance::withoutGlobalScope('tenant')
            ->whereNotIn('status', ['banned'])
            ->whereNotNull('gateway_instance_id')
            ->get()
            ->each(function (WhatsAppInstance $instance) use ($guard) {
                try {
                    $gateway   = new EvolutionApiClient($instance->effectiveGatewayUrl(), $instance->effectiveGatewayApiKey());
                    $newStatus = $gateway->probeStatus($instance->gateway_instance_id);
                } catch (\Exception) {
                    // Do not force-disconnect on transient gateway/network errors.
                    return;
                }

                if ($newStatus === null) {
                    // Gateway unreachable — that says nothing about the phone.
                    return;
                }

                // PROC-019: a guarded instance stays parked in 'error' until it
                // is genuinely back. This poll is what notices that, and it is
                // the only path that lifts the guard without a human re-pairing.
                if ($guard->isTripped($instance)) {
                    if ($newStatus === 'connected') {
                        $guard->recover($instance);
                        $instance->update(['status' => 'connected', 'last_status_at' => now()]);
                        broadcast(new InstanceStatusChanged($instance->fresh()));
                    }

                    return;
                }

                $strikeKey = "instance-health-strikes:{$instance->id}";

                if ($newStatus === 'connected') {
                    Cache::forget($strikeKey);
                } elseif ($instance->status === 'connected') {
                    // Demoting a live instance is disruptive (it stops OTP and
                    // outbound sends), so require the gateway to say it twice.
                    $strikes = (int) Cache::get($strikeKey, 0) + 1;

                    if ($strikes < self::DISCONNECT_STRIKES) {
                        Cache::put($strikeKey, $strikes, now()->addMinutes(30));
                        return;
                    }

                    Cache::forget($strikeKey);
                }

                $changed = $newStatus !== $instance->status;
                $instance->update(['status' => $newStatus, 'last_status_at' => now()]);

                if ($changed) {
                    broadcast(new InstanceStatusChanged($instance->fresh()));
                }
            });
    }
}
