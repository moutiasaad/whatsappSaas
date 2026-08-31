<?php

namespace App\Providers;

use App\Mail\Transport\MailtrapApiTransport;
use App\Models\Conversation;
use App\Models\WhatsAppInstance;
use App\Policies\ConversationPolicy;
use App\Services\WhatsApp\Gateway\EvolutionApiClient;
use App\Services\WhatsApp\Gateway\GatewayClientInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
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
            return new EvolutionApiClient($instance->effectiveGatewayUrl(), $instance->effectiveGatewayApiKey());
        });
    }

    public function boot(): void
    {
        // Mailtrap HTTP API transport — see config/mail.php 'mailtrap' mailer.
        Mail::extend('mailtrap', function (array $config) {
            return new MailtrapApiTransport((string) ($config['token'] ?? ''));
        });

        Gate::policy(Conversation::class, ConversationPolicy::class);

        // Allow super_admin to impersonate any user
        Gate::define('impersonate', function ($user, $target) {
            if ($user->isSuperAdmin()) return true;
            if ($user->isAdmin()) return !$target->isAdmin() && !$target->isSuperAdmin();
            return false;
        });

        // Web Live-Chat agent ability — governs both dashboard routes and
        // presence-webchat.tenant.{tenantId} channel authorization.
        Gate::define('webchat-agent', function ($user) {
            return in_array($user->role, ['admin', 'supervisor', 'agent'], true);
        });

        // Web Live-Chat public API rate limiters (keyed per-IP / per-visitor)
        RateLimiter::for('webchat-session', function (Request $request) {
            return Limit::perMinute(20)->by('wc-sess:' . $request->ip());
        });

        RateLimiter::for('webchat-message', function (Request $request) {
            $key = $request->bearerToken() ?: $request->ip();
            return Limit::perMinute(60)->by('wc-msg:' . $key);
        });
    }
}
