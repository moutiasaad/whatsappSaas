<?php

namespace App\Http\Middleware;

use App\Models\Country;
use App\Models\PlatformSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * DetectCountry
 *
 * Resolves the visitor's country and shares it (plus their currency) to
 * every view under $visitorCountry / $visitorCurrency / $visitorCurrencySymbol.
 *
 * Source order (first hit wins):
 *   1. `?country=XX` query param — for QA + super-admin previews. Always
 *      wins so an operator can force-render a specific country regardless
 *      of where they physically are. Two-letter ISO code, uppercased.
 *   2. `CF-IPCountry` header — Cloudflare puts this on every request when
 *      the site is behind CF proxy. Free, accurate, no lookup cost.
 *   3. session('visitor_country') — sticks the resolved value for the
 *      whole visit so we don't re-lookup on every request.
 *   4. platform default_country platform_setting (or config fallback) —
 *      last-resort when nothing above resolves. Countries the platform
 *      hasn't enabled will fall through Plan::priceFor() to base USD, so
 *      a bad default only affects display, never billing safety.
 *
 * Failure mode: unrecognised or disabled country → view sees the country
 * code but $visitorCurrency stays USD ($). Plan::priceFor() enforces the
 * same rule when it comes time to charge.
 */
class DetectCountry
{
    public const SESSION_KEY = 'visitor_country';
    private const CACHE_TTL  = 60; // seconds — same posture as SetLocale

    public function handle(Request $request, Closure $next): mixed
    {
        $code = $this->resolveCountry($request);

        // Persist for this visit so subsequent requests skip the lookup.
        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $code);
        }

        // Look up the currency from the countries table. Falls back to
        // USD/$ when the code is unknown or the country isn't enabled —
        // Plan::priceFor() will also refuse to price it, keeping display
        // and charging consistent.
        [$currency, $symbol] = $this->currencyFor($code);

        view()->share('visitorCountry', $code);
        view()->share('visitorCurrency', $currency);
        view()->share('visitorCurrencySymbol', $symbol);

        return $next($request);
    }

    private function resolveCountry(Request $request): string
    {
        // 1. Explicit query-param override (QA / preview).
        $override = strtoupper((string) $request->query('country', ''));
        if (preg_match('/^[A-Z]{2}$/', $override)) {
            return $override;
        }

        // 2. Cloudflare header — the primary production source. Cloudflare
        // strips + rewrites this header itself, so a client-forged value
        // is impossible when CF proxy is on. When CF is NOT in front (our
        // current state per the earlier diagnosis), the header is absent
        // and we fall through to sessions / defaults.
        $cfHeader = strtoupper((string) $request->header('CF-IPCountry', ''));
        if (preg_match('/^[A-Z]{2}$/', $cfHeader) && $cfHeader !== 'XX' && $cfHeader !== 'T1') {
            return $cfHeader;
        }

        // 3. Sticky session value from an earlier request in this visit.
        if ($request->hasSession()) {
            $sess = (string) $request->session()->get(self::SESSION_KEY, '');
            if (preg_match('/^[A-Z]{2}$/', $sess)) {
                return $sess;
            }
        }

        // 4. Platform-configured default. Same rescue() pattern as SetLocale:
        // if platform_settings is unavailable (fresh install, DB blip), fall
        // through to a config-level default rather than 500 the whole page.
        try {
            $default = Cache::remember('platform.default_country', self::CACHE_TTL, function () {
                return PlatformSetting::get('default_country', config('app.default_country', 'US'));
            });
        } catch (Throwable) {
            $default = config('app.default_country', 'US');
        }

        return strtoupper((string) ($default ?: 'US'));
    }

    /**
     * @return array{0: string, 1: string}  [currency_code, symbol]
     */
    private function currencyFor(string $code): array
    {
        try {
            $country = Cache::remember('country.currency.' . $code, self::CACHE_TTL, function () use ($code) {
                return Country::query()
                    ->where('code', $code)
                    ->where('is_active', true)
                    ->first(['currency_code', 'currency_symbol']);
            });
        } catch (Throwable) {
            $country = null;
        }

        if (! $country) {
            return ['USD', '$'];
        }

        return [$country->currency_code, $country->currency_symbol];
    }
}
