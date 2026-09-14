<?php

namespace App\Jobs;

use App\Models\WhatsAppInstance;
use App\Services\WhatsApp\Gateway\EvolutionApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * PROC-019 — undoes MuteGatewayConnectionEvents.
 *
 * Runs when a guarded instance turns out to be healthy again, so a socket that
 * recovers on its own starts delivering events without anyone intervening.
 * Passing an empty event list lets the client fill in its own defaults, which
 * keeps this in step with the registration path in InstanceController.
 */
class RestoreGatewayWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(private int $instanceId) {}

    public function handle(): void
    {
        $instance = WhatsAppInstance::withoutGlobalScope('tenant')->find($this->instanceId);

        if (!$instance || blank($instance->gateway_instance_id) || !$instance->hasGatewayCredentials()) {
            return;
        }

        $url = $instance->webhook_url;

        if (blank($url)) {
            Log::channel('whatsapp')->warning('Webhook restore skipped — no stored url', [
                'instance_id' => $instance->id,
            ]);
            return;
        }

        try {
            $gateway = new EvolutionApiClient(
                $instance->effectiveGatewayUrl(),
                $instance->effectiveGatewayApiKey(),
            );

            $gateway->setWebhook($instance->gateway_instance_id, $url, [], $instance->webhook_secret);
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('Webhook restore failed', [
                'instance_id' => $instance->id,
                'error'       => $e->getMessage(),
            ]);
            throw $e;
        }

        $instance->update([
            'webhook_enabled'  => true,
            'webhook_url'      => $url,
            'webhook_last_set' => now(),
        ]);

        Log::channel('whatsapp')->info('Webhook restored', ['instance_id' => $instance->id]);
    }
}
