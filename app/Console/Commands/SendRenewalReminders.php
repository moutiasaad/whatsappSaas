<?php

namespace App\Console\Commands;

use App\Events\NotificationCreated;
use App\Models\AppNotification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;

class SendRenewalReminders extends Command
{
    protected $signature   = 'notifications:renewal-reminders';
    protected $description = 'Send renewal reminder notifications for expiring subscriptions';

    public function handle(): void
    {
        $thresholds = [7, 3, 1];

        foreach ($thresholds as $days) {
            $targetDate = now()->addDays($days)->toDateString();

            $tenants = Tenant::whereNotNull('subscription_ends_at')
                ->whereDate('subscription_ends_at', $targetDate)
                ->where('is_active', true)
                ->with('plan:id,name')
                ->get();

            foreach ($tenants as $tenant) {
                $admins = User::where('tenant_id', $tenant->id)
                    ->where('role', 'admin')
                    ->get(['id']);

                foreach ($admins as $admin) {
                    $alreadySent = AppNotification::where('user_id', $admin->id)
                        ->where('type', 'renewal')
                        ->whereDate('created_at', today())
                        ->exists();

                    if ($alreadySent) {
                        continue;
                    }

                    $planName = $tenant->plan?->name ?? 'your plan';
                    $expiryDate = $tenant->subscription_ends_at->format('d/m/Y');

                    $notif = AppNotification::create([
                        'tenant_id' => $tenant->id,
                        'user_id'   => $admin->id,
                        'sender_id' => null,
                        'type'      => 'renewal',
                        'title'     => "Subscription expiring in {$days} day(s)",
                        'body'      => "Your {$planName} subscription will expire on {$expiryDate}. Renew now to avoid service interruption.",
                        'data'      => [
                            'days_remaining' => $days,
                            'plan_name'      => $planName,
                            'expiry_date'    => $expiryDate,
                        ],
                    ]);
                    broadcast(new NotificationCreated($notif));
                }
            }

            $this->line("Processed {$tenants->count()} tenant(s) expiring in {$days} day(s).");
        }

        $this->info('Renewal reminders sent.');
    }
}
