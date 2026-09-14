<?php

namespace App\Jobs;

use App\Models\WhatsAppInstance;
use App\Services\WhatsApp\ConnectionFlapGuard;
use App\Services\WhatsApp\Gateway\EvolutionApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * PROC-019 — breaks the webhook half of the gateway's reconnect loop.
 *
 * The gateway retries a refused socket forever and posts a connection.update on
 * every lap. We cannot stop its internal retry from here, but we can stop it
 * shouting at us: unsubscribing from that one event ends the loop's only
 * outbound effect.
 *
 * Deliberately narrow and non-destructive. The gateway instance, its session
 * and its pairing are untouched, the webhook stays enabled, and every other
 * event — messages included — keeps flowing, so an instance that recovers
 * still delivers. InstanceController::connect() restores the full event set on
 * re-pair, and PollInstanceHealth does the same automatically if the socket
 * comes back on its own.
 */
class MuteGatewayConnectionEvents implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 30;

    public function __construct(private int $instanceId) {}

    public function handle(ConnectionFlapGuard $guard): void
    {
        $instance = WhatsAppInstance::withoutGlobalScope('tenant')->find($this->instanceId);

        if (!$instance) {
            return;
        }

        // Re-check the gates against current state rather than state at dispatch:
        // the instance may have reconnected or picked up traffic in between.
        if ($refusal = $guard->muteRefusal($instance)) {
            Log::channel('whatsapp')->info('Connection-event mute skipped', [
                'instance_id' => $instance->id,
                'reason'      => $refusal,
            ]);
            $this->markOutcome($instance, false, $refusal);
            return;
        }

        if (!$guard->isTripped($instance)) {
            Log::channel('whatsapp')->info('Connection-event mute skipped', [
                'instance_id' => $instance->id,
                'reason'      => 'guard was reset before the job ran',
            ]);
            return;
        }

        try {
            $gateway = new EvolutionApiClient(
                $instance->effectiveGatewayUrl(),
                $instance->effectiveGatewayApiKey(),
            );

            $gateway->muteConnectionEvents(
                $instance->gateway_instance_id,
                (string) $instance->webhook_url,
                $instance->webhook_secret,
            );
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->warning('Connection-event mute failed', [
                'instance_id' => $instance->id,
                'error'       => $e->getMessage(),
            ]);
            $this->markOutcome($instance, false, 'gateway refused the webhook update: ' . $e->getMessage());
            return;
        }

        // Mark the subscription as not fully registered, the same idiom
        // SingleInstanceController uses, so ensureWebhookRegistered() re-applies
        // the complete event set — connectionUpdated included — the next time
        // anyone deliberately connects this instance.
        $instance->update([
            'webhook_enabled'  => false,
            'webhook_last_set' => now(),
        ]);
        $this->markOutcome($instance, true, null);

        Log::channel('whatsapp')->warning('Muted connection events for looping instance', [
            'instance_id' => $instance->id,
            'instance'    => $instance->name,
            'gateway_id'  => $instance->gateway_instance_id,
        ]);
    }

    private function markOutcome(WhatsAppInstance $instance, bool $muted, ?string $refusal): void
    {
        $settings = $instance->settings ?? [];

        if (!isset($settings['connection_guard'])) {
            return;
        }

        $settings['connection_guard']['connection_events_muted'] = $muted;
        $settings['connection_guard']['muted_at']                = $muted ? now()->toIso8601String() : null;

        if ($refusal !== null) {
            $settings['connection_guard']['mute_refusal'] = $refusal;
        }

        $instance->update(['settings' => $settings]);
    }
}
