<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireSubscriptions extends Command
{
    protected $signature   = 'subscriptions:expire';
    protected $description = 'Suspend tenants whose subscription_ends_at has passed';

    public function handle(): void
    {
        $expired = Tenant::where('subscription_status', 'active')
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '<', now())
            ->get();

        foreach ($expired as $tenant) {
            $tenant->update([
                'subscription_status' => 'suspended',
                'is_active'           => false,
            ]);

            Log::info('Subscription expired — tenant suspended', [
                'tenant_id'   => $tenant->id,
                'tenant_name' => $tenant->name,
                'ended_at'    => $tenant->subscription_ends_at->toDateString(),
            ]);

            $this->line("Suspended: [{$tenant->id}] {$tenant->name}");
        }

        $this->info("Done. {$expired->count()} tenant(s) suspended.");
    }
}
