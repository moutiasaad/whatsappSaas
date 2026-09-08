<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireSubscriptions extends Command
{
    protected $signature   = 'subscriptions:expire';
    protected $description = 'Suspend tenants whose paid subscription or trial has ended';

    public function handle(): void
    {
        $paidExpired = Tenant::where('subscription_status', 'active')
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '<', now())
            ->get();

        foreach ($paidExpired as $tenant) {
            $tenant->update([
                'subscription_status' => 'suspended',
                'is_active'           => false,
            ]);

            Log::info('Paid subscription expired — tenant suspended', [
                'tenant_id'   => $tenant->id,
                'tenant_name' => $tenant->name,
                'ended_at'    => $tenant->subscription_ends_at->toDateString(),
            ]);

            $this->line("Paid subscription suspended: [{$tenant->id}] {$tenant->name}");
        }

        $trialExpired = Tenant::where('subscription_status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->get();

        foreach ($trialExpired as $tenant) {
            $tenant->update([
                'subscription_status' => 'suspended',
                'is_active'           => false,
            ]);

            Log::info('Trial expired — tenant suspended', [
                'tenant_id'      => $tenant->id,
                'tenant_name'    => $tenant->name,
                'trial_ended_at' => $tenant->trial_ends_at->toDateString(),
            ]);

            $this->line("Trial suspended: [{$tenant->id}] {$tenant->name}");
        }

        $this->info("Done. {$paidExpired->count()} paid subscription(s) and {$trialExpired->count()} trial(s) suspended.");
    }
}
