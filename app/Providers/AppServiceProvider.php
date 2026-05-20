<?php

namespace App\Providers;

use App\Models\Conversation;
use App\Models\WhatsAppInstance;
use App\Policies\ConversationPolicy;
use App\Services\WhatsApp\Gateway\EvolutionApiClient;
use App\Services\WhatsApp\Gateway\GatewayClientInterface;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GatewayClientInterface::class, function ($app, array $params) {
            $instance = $params['instance'] ?? null;
            if (!$instance instanceof WhatsAppInstance) {
                throw new \InvalidArgumentException('GatewayClientInterface requires an instance parameter');
            }
            return new EvolutionApiClient($instance->gateway_url, $instance->gateway_api_key);
        });
    }

    public function boot(): void
    {
        Gate::policy(Conversation::class, ConversationPolicy::class);

        // Allow super_admin to impersonate any user
        Gate::define('impersonate', function ($user, $target) {
            if ($user->isSuperAdmin()) return true;
            if ($user->isAdmin()) return !$target->isAdmin() && !$target->isSuperAdmin();
            return false;
        });
    }
}
