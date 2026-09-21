<?php

namespace App\Http\Middleware;

use App\Models\PlatformSetting;
use App\Support\GeoIpLookup;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * DetectCountry
 *
 * Resolves the visitor's country from their IP so the plan pickers
 * render local prices. Shares $visitorCountry / $visitorCurrency /
 * $visitorCurrencySymbol to every view.
 *
 * Source order (first hit wins):
 *   1. `CF-IPCountry` header — the strongest source. Cloudflare puts
 *      this on every request when the site is proxied through CF and
 *      strips any client-forged value, so it can't be spoofed. Free.
 *   2. GeoIP lookup against the visitor's real IP — used when CF isn't
 *      in front (our current state). Backed by ipapi.co with a 7-day
 *      per-IP cache so a returning visitor never re-hits the API.
 *   3. session('visitor_country') — sticks the resolved value for the
 *      whole visit so subsequent requests skip the lookup entirely.
 *   4. platform default_country platform_setting → env → 'US'.
 *
 * `?country=XX` in the URL is NO LONGER accepted as an override. It's
 * a spoof attempt (someone trying to fake a cheaper country) — the
 * middleware strips the param and 302-redirects to the canonical URL
 * so the visitor lands on the price their IP resolves to, no matter
 * what they typed. See stripSpoofingParam() for the redirect logic.
 *
 * Failure mode: unrecognised or disabled country → view sees the
 * country code but $visitorCurrency stays USD/$. Plan::priceFor()
 * enforces the same rule when it comes time to charge.
 */
class DetectCountry
{
    public const SESSION_KEY = 'visitor_country';
    private const CACHE_TTL  = 60; // seconds — same posture as SetLocale

    public function handle(Request $request, Closure $next): mixed
    {
        // If the visitor has ?country=XX in the URL, redirect them without
        // it so they can't fake a country by editing the address bar. The
        // stripped URL then re-enters this middleware and resolves via IP.
        if ($redirect = $this->stripSpoofingParam($request)) {
            return $redirect;
        }

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

    /**
     * If a GET request carries ?country=XX, return a 302 to the same URL
     * with the param removed. Only fires on safe (GET/HEAD) requests so
     * a POST form's carried-through query params don't break their
     * submission with an unwanted 302.
     */
    private function stripSpoofingParam(Request $request): ?Response
    {
        if (! $request->isMethodSafe() || ! $request->has('country')) {
            return null;
        }

        $query = $request->query();
        unset($query['country']);

        $url = $request->url() . (empty($query) ? '' : '?' . http_build_query($query));
        return redirect()->to($url, 302);
    }

    private function resolveCountry(Request $request): string
    {
        // 1. Cloudflare header — best source when the site is behind CF.
        // CF strips any client-forged value, so this is un-spoofable.
        // Absent today (wavadesk.com is not proxied through CF), so we
        // fall through to the IP lookup below.
        $cfHeader = strtoupper((string) $request->header('CF-IPCountry', ''));
        if (preg_match('/^[A-Z]{2}$/', $cfHeader) && $cfHeader !== 'XX' && $cfHeader !== 'T1') {
            return $cfHeader;
        }

        // 2. IP-based lookup against the real visitor IP. Cached 7 days
        // per IP in Cache::remember so a returning visitor never re-hits
        // the third-party API. Trust proxy is already configured in
        // bootstrap/app.php so $request->ip() returns the client IP, not
        // the load balancer's.
        try {
            $ipCode = app(GeoIpLookup::class)->forIp((string) $request->ip());
        } catch (Throwable) {
            $ipCode = null;
        }
        if ($ipCode && preg_match('/^[A-Z]{2}$/', $ipCode)) {
            return $ipCode;
        }

        // 3. Sticky session value from an earlier request in this visit.
        // Useful when IP lookup was momentarily unavailable — a subsequent
        // request keeps the same value the visitor first landed with.
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
            $entry = app(\App\Support\CountriesRegistry::class)->currencyFor($code);
        } catch (Throwable) {
            $entry = null;
        }

        if (! $entry) {
            return ['USD', '$'];
        }

        return [$entry['currency_code'], $entry['currency_symbol']];
    }
}
