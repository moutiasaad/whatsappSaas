<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * IP → ISO country code lookup for boxes that aren't behind Cloudflare.
 *
 * Cloudflare's CF-IPCountry header is the primary source (accurate,
 * free, no rate limit) but wavadesk.com isn't proxied through CF today.
 * This class calls the free ipapi.co endpoint as a fallback so IP-based
 * pricing works right now without a DNS change.
 *
 * Aggressively cached per IP (7 days). A returning visitor from the same
 * IP hits our cache, never the third-party API. The free tier is 1000
 * requests/day; with per-IP caching that supports a very large audience
 * before we'd need a paid plan or the MaxMind DB fallback.
 *
 * Fail-safe: any error (unreachable API, invalid response, rate limit,
 * private/local IP) returns null. Caller falls back to the platform
 * default country, so the site never dies on a geo-lookup hiccup.
 */
class GeoIpLookup
{
    private const CACHE_TTL_DAYS = 7;
    private const TIMEOUT_SEC    = 2; // don't hold a page render on a slow lookup

    /**
     * Returns an uppercase ISO 3166-1 alpha-2 code, or null.
     */
    public function forIp(string $ip): ?string
    {
        // Private / loopback / documentation IPs → no meaningful lookup.
        // FILTER_FLAG_NO_PRIV_RANGE also rejects 10.*, 172.16.*, 192.168.*.
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        $cacheKey = 'geoip.country.' . $ip;

        try {
            return Cache::remember($cacheKey, now()->addDays(self::CACHE_TTL_DAYS), function () use ($ip) {
                return $this->fetchFromApi($ip);
            });
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * ipapi.co exposes a lightweight endpoint that returns just the ISO
     * code as plain text — no JSON parsing overhead, no extra fields.
     * Endpoint: https://ipapi.co/{ip}/country/
     */
    private function fetchFromApi(string $ip): ?string
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SEC)
                ->withHeaders(['User-Agent' => 'wavadesk-geoip/1.0'])
                ->get("https://ipapi.co/{$ip}/country/");
        } catch (Throwable $e) {
            Log::info('GeoIpLookup: request failed', ['ip' => $ip, 'error' => $e->getMessage()]);
            return null;
        }

        if (! $response->successful()) {
            Log::info('GeoIpLookup: non-2xx', ['ip' => $ip, 'status' => $response->status()]);
            return null;
        }

        $code = strtoupper(trim((string) $response->body()));

        // Response can be a 2-letter code, "Undefined" (private IP passed
        // through), or an error blob. Validate strictly before returning.
        if (! preg_match('/^[A-Z]{2}$/', $code)) {
            return null;
        }

        return $code;
    }
}
