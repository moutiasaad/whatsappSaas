<?php

namespace App\Providers;

use App\Services\Auth\SsoHandoffCode;
use App\Services\WavadeskApi;
use Illuminate\Support\ServiceProvider;

/**
 * AppServiceProvider (Server A).
 *
 * Binds the two decoupling services with constructor arguments the container
 * cannot infer on its own: the API base URL and the shared HMAC secret. Both
 * are env-driven and set by CI/CD to match the values Server B is running
 * with — a mismatch fails closed at boot rather than at first request.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WavadeskApi::class, function () {
            return new WavadeskApi(
                baseUrl: (string) config('services.wavadesk.app_url'),
                timeoutSeconds: (float) env('WAVADESK_API_TIMEOUT', 5),
            );
        });

        $this->app->singleton(SsoHandoffCode::class, function () {
            return new SsoHandoffCode((string) env('WAVADESK_SHARED_SECRET', ''));
        });
    }

    public function boot(): void {}
}
