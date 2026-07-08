<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\WebChat\Widget;
use Illuminate\Console\Command;

class WebChatProvisionWidget extends Command
{
    protected $signature   = 'webchat:provision-widget {tenant : Tenant id or slug}';
    protected $description = 'Provision a default Web Live-Chat widget for a tenant';

    public function handle(): int
    {
        $key = (string) $this->argument('tenant');

        $tenant = is_numeric($key)
            ? Tenant::find((int) $key)
            : Tenant::where('slug', $key)->first();

        if (!$tenant) {
            $this->error("Tenant not found: {$key}");
            return self::FAILURE;
        }

        app()->instance('current_tenant_id', $tenant->id);

        $existing = Widget::where('tenant_id', $tenant->id)->first();
        if ($existing) {
            $this->info("Widget already exists for tenant [{$tenant->id}] {$tenant->name}");
            $this->line("  public_key: {$existing->public_key}");
            return self::SUCCESS;
        }

        $widget = Widget::create([
            'tenant_id'          => $tenant->id,
            'name'               => 'Live Chat',
            'enabled'            => true,
            'welcome_message'    => 'Hi there! How can we help you today?',
            'suggestions'        => ['Pricing', 'Support', 'Talk to a human'],
            'pre_chat_ask_email' => false,
            'offline_message'    => "We're offline right now. Leave a message and we'll get back to you soon.",
            'theme_color'        => '#2563eb',
            'position'           => 'right',
            'launcher_text'      => null,
            'allowed_domains'    => [],
        ]);

        $this->info("Widget provisioned for tenant [{$tenant->id}] {$tenant->name}");
        $this->line("  public_key: {$widget->public_key}");

        return self::SUCCESS;
    }
}
