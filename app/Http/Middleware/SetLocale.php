<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SetLocale
{
    public function handle(Request $request, Closure $next): mixed
    {
        $supportedLocales = array_keys(config('locales.supported', []));
        $defaultLocale = config('app.locale', 'en');

        if (empty($supportedLocales)) {
            $supportedLocales = [$defaultLocale];
        }

        $locale = null;

        if ($request->hasSession()) {
            $locale = $request->session()->get('locale');
        }

        if (!$locale || !in_array($locale, $supportedLocales, true)) {
            $locale = $request->getPreferredLanguage($supportedLocales) ?: $defaultLocale;

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
