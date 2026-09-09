<?php

namespace App\Console\Commands;

use App\Models\WhatsAppInstance;
use App\Services\WhatsApp\Gateway\EvolutionApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

// PROC-018 phase 2: run once after the backfill migration to push each
// instance's (now populated) HMAC secret to the gateway. Success stamps
// webhook_last_set, which lets the verifier flip to fail-closed for that
// row. Rows whose gateway call fails stay in fail-open until the next
// successful run.
class WhatsAppReconfigureWebhooks extends Command
{
    protected $signature = 'whatsapp:reconfigure-webhooks
                            {--only= : Reconfigure a single instance id}
                            {--stale : Skip instances whose webhook_last_set is already populated}';

    protected $description = 'Push the HMAC secret to the gateway for every WhatsApp instance so signature verification can be enforced.';

    public function handle(): int
    {
        $query = WhatsAppInstance::query()->whereNotNull('gateway_instance_id');

        if ($id = $this->option('only')) {
            $query->where('id', (int) $id);
        }

        if ($this->option('stale')) {
            $query->whereNull('webhook_last_set');
        }

        $instances = $query->get();

        if ($instances->isEmpty()) {
            $this->info('No instances to reconfigure.');
            return self::SUCCESS;
        }

        $ok = 0;
        $failed = 0;

        foreach ($instances as $instance) {
            if (!$instance->webhook_secret) {
                $this->warn("[{$instance->id}] {$instance->name}: no webhook_secret in DB, skipping (run migrate first).");
                $failed++;
                continue;
            }
            if (!$instance->hasGatewayCredentials()) {
                $this->warn("[{$instance->id}] {$instance->name}: no gateway credentials, skipping.");
                $failed++;
                continue;
            }

            $url = $this->webhookUrl($instance);

            try {
                $client = new EvolutionApiClient(
                    $instance->effectiveGatewayUrl(),
                    $instance->effectiveGatewayApiKey(),
                );
                $client->setWebhook($instance->gateway_instance_id, $url, [], $instance->webhook_secret);
                $instance->update([
                    'webhook_enabled'  => true,
                    'webhook_url'      => $url,
                    'webhook_last_set' => now(),
                ]);
                $this->info("[{$instance->id}] {$instance->name}: reconfigured.");
                $ok++;
            } catch (\Throwable $e) {
                Log::channel('whatsapp')->error('Reconfigure webhook failed', [
                    'instance_id' => $instance->id,
                    'error'       => $e->getMessage(),
                ]);
                $this->error("[{$instance->id}] {$instance->name}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->line("Done. ok={$ok} failed={$failed}");
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function webhookUrl(WhatsAppInstance $instance): string
    {
        $base = rtrim((string) config('services.whatsapp.webhook_base_url', config('app.url')), '/');
        return "{$base}/api/webhooks/whatsapp/{$instance->webhook_token}";
    }
}
