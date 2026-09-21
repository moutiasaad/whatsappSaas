<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\SuperAdminPlatformController;
use App\Models\PlatformSetting;
use App\Services\WavadeskApi;
use App\Support\Wavadesk;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SetLocale
{
    /**
     * Cache key + TTL for the platform default the marketing box pulls from
     * core. A short TTL means a super admin's language change reaches the
     * marketing landing within a minute — long enough to avoid an API call
     * per anonymous request, short enough to feel live during testing.
     */
    private const MARKETING_DEFAULT_CACHE_KEY = 'wavadesk.platform_default_locale';
    private const MARKETING_DEFAULT_CACHE_TTL = 60;

    public function handle(Request $request, Closure $next): mixed
    {
        $supportedLocales = array_keys(config('locales.supported', []));

        // Super admin picks the platform default from
        // /admin-control-panel/platform/localization on the core box. Where
        // we READ that value from depends on which host is running:
        //
        //   - Core / non-split: read the local platform_settings row.
        //   - Marketing: the row lives on the OTHER box's DB. Fetch it via
        //     the v1 preferences API and cache it — otherwise every anonymous
        //     landing-page hit would round-trip to core.
        //
        // Both paths swallow errors so a missing table (fresh install) or
        // an unreachable core (network blip) never 500s the site.
        $configDefault = config('app.locale', 'en');

        if (Wavadesk::isMarketing()) {
            $defaultLocale = $this->fetchDefaultFromCore($configDefault);
        } else {
            try {
                $defaultLocale = PlatformSetting::get(
                    SuperAdminPlatformController::DEFAULT_LOCALE_KEY,
                    $configDefault,
                );
            } catch (Throwable) {
                $defaultLocale = $configDefault;
            }
        }

        if (empty($supportedLocales)) {
            $supportedLocales = [$defaultLocale];
        }

        // If the stored default was removed from the supported list at
        // runtime, don't leave the site pointing at a non-existent locale.
        if (! in_array($defaultLocale, $supportedLocales, true)) {
            $defaultLocale = $supportedLocales[0];
        }

        $locale = null;

        if ($request->hasSession()) {
            $locale = $request->session()->get('locale');
        }

        if (!$locale || !in_array($locale, $supportedLocales, true)) {
            $locale = $defaultLocale;

            if ($request->hasSession()) {
                $request->session()->put('locale', $locale);
            }
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);
        view()->share('currentLocale', $locale);
        view()->share('supportedLocales', config('locales.supported', []));

        return $next($request);
    }

    /**
     * Pull the platform default locale from the core app's preferences API.
     * Cached for MARKETING_DEFAULT_CACHE_TTL so anonymous landing-page traffic
     * doesn't round-trip to core on every request.
     *
     * Any failure (unreachable core, wrong-shaped payload, unsupported
     * locale) falls back to the config default. Marketing never dies on
     * account of a core-side hiccup.
     */
    private function fetchDefaultFromCore(string $fallback): string
    {
        try {
            $cached = Cache::remember(
                self::MARKETING_DEFAULT_CACHE_KEY,
                self::MARKETING_DEFAULT_CACHE_TTL,
                function () use ($fallback): string {
                    /** @var WavadeskApi $api */
                    $api = app(WavadeskApi::class);
                    $res = $api->platformPreferences();

                    $value = $res['body']['default_locale'] ?? null;

                    // Cache only usable values so a bad payload doesn't
                    // stick for 60s. Falling through returns $fallback which
                    // Cache::remember refuses to store — next request retries.
                    return is_string($value) && $value !== '' ? $value : $fallback;
                },
            );

            return is_string($cached) && $cached !== '' ? $cached : $fallback;
        } catch (Throwable) {
            return $fallback;
        }
    }
}
