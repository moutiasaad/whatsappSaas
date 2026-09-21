<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\SuperAdminPlatformController;
use App\Models\PlatformSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class SetLocale
{
    public function handle(Request $request, Closure $next): mixed
    {
        $supportedLocales = array_keys(config('locales.supported', []));

        // Super admin can pick the platform default from
        // /admin-control-panel/platform/localization; the stored value is
        // preferred over config('app.locale'). rescue() guards the first
        // request after a fresh install — before the platform_settings
        // table exists — so we never 500 the app on a missing table.
        $configDefault = config('app.locale', 'en');
        try {
            $defaultLocale = PlatformSetting::get(
                SuperAdminPlatformController::DEFAULT_LOCALE_KEY,
                $configDefault,
            );
        } catch (Throwable) {
            $defaultLocale = $configDefault;
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
}
