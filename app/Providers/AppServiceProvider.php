<?php

namespace App\Providers;

use App\Mail\Transport\BrevoApiTransport;
use App\Mail\Transport\MailtrapApiTransport;
use App\Models\Conversation;
use App\Models\WhatsAppInstance;
use App\Policies\ConversationPolicy;
use App\Services\Auth\SsoHandoffCode;
use App\Services\WavadeskApi;
use App\Services\WhatsApp\Gateway\EvolutionApiClient;
use App\Services\WhatsApp\Gateway\GatewayClientInterface;
use App\Support\Wavadesk;
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

        // SSO handoff between wavadesk.com (marketing) and app.wavadesk.com
        // (core). Both hosts run this codebase and share one secret, so the
        // same class mints on one side and verifies on the other — there is no
        // second copy to drift out of lockstep.
        //
        // Read from config, never env(): `artisan config:cache` stops Laravel
        // loading .env at all, so an env()-bound secret is null in production
        // and every handoff 500s while working perfectly in dev.
        $this->app->singleton(SsoHandoffCode::class, function () {
            return new SsoHandoffCode(Wavadesk::sharedSecret());
        });

        // Marketing-side client for the core app's v1 auth API. Bound lazily:
        // a core box never resolves it, so it costs nothing there and does not
        // need WAVADESK_CORE_URL set.
        $this->app->singleton(WavadeskApi::class, function () {
            return new WavadeskApi(
                Wavadesk::coreUrl(),
                Wavadesk::sharedSecret(),
                (float) config('wavadesk.api_timeout', 5),
            );
        });
    }

    public function boot(): void
    {
        // HTTP API mail transports — see config/mail.php. Outbound SMTP is
        // blocked on this host, so API-based delivery is the only option.
        Mail::extend('mailtrap', function (array $config) {
            return new MailtrapApiTransport((string) ($config['token'] ?? ''));
        });

        Mail::extend('brevo', function (array $config) {
            return new BrevoApiTransport((string) ($config['key'] ?? ''));
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
