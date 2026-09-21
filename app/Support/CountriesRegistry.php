<?php

namespace App\Support;

use App\Models\Country;
use App\Models\Plan;
use App\Models\PlanCountryPrice;
use App\Services\WavadeskApi;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Cross-host countries + per-plan pricing lookup.
 *
 * Countries live in the CORE database only (there is no super-admin
 * panel on the marketing box). This registry is the one place code
 * should ask "what currency does country XX use?" or "what does plan
 * P cost for a customer in country XX?" — it hides where the data
 * physically lives:
 *
 *   - On core / non-split: query the local DB directly.
 *   - On marketing: fetch a snapshot from core's GET /api/v1/countries
 *     endpoint, cached in Laravel's cache for 60s.
 *
 * Same pattern as SetLocale::fetchDefaultFromCore — a short TTL means
 * a super-admin's price change reaches the marketing landing within a
 * minute without hammering core on every anonymous visitor request.
 *
 * Failure safety: any lookup that can't be answered (unreachable core,
 * unknown country, disabled row) falls through to base USD via the
 * caller's own fallback path — nothing here throws.
 */
class CountriesRegistry
{
    public const CACHE_KEY = 'wavadesk.countries_snapshot';
    public const CACHE_TTL = 60;

    /**
     * Currency + symbol for a country code, or null if the country isn't
     * enabled anywhere (caller falls back to USD/$).
     *
     * @return array{code: string, currency_code: string, currency_symbol: string}|null
     */
    public function currencyFor(string $code): ?array
    {
        $code = strtoupper($code);
        $snapshot = $this->snapshot();

        foreach ($snapshot['countries'] ?? [] as $c) {
            if (strtoupper((string) ($c['code'] ?? '')) === $code) {
                return [
                    'code'            => $code,
                    'currency_code'   => (string) $c['currency_code'],
                    'currency_symbol' => (string) $c['currency_symbol'],
                ];
            }
        }
        return null;
    }

    /**
     * Localised price + currency for a plan in a country. Returns null when
     * no localised price exists so the caller can decide whether to fall
     * back to the plan's base USD (Plan::priceFor does exactly that).
     *
     * @return array{amount: float, currency: string, symbol: string}|null
     */
    public function priceFor(int $planId, string $countryCode, string $cycle = 'monthly'): ?array
    {
        $countryCode = strtoupper($countryCode);
        $currency    = $this->currencyFor($countryCode);
        if (! $currency) {
            return null;
        }

        $snapshot = $this->snapshot();
        $column   = $cycle === 'annual' ? 'price_annual' : 'price_monthly';

        foreach ($snapshot['plan_prices'] ?? [] as $row) {
            if ((int) ($row['plan_id'] ?? 0) !== $planId) continue;
            if (strtoupper((string) ($row['country_code'] ?? '')) !== $countryCode) continue;

            $amount = isset($row[$column]) ? (float) $row[$column] : 0.0;
            if ($amount <= 0) {
                return null; // Row exists but this cycle isn't priced locally.
            }

            return [
                'amount'   => $amount,
                'currency' => $currency['currency_code'],
                'symbol'   => $currency['currency_symbol'],
            ];
        }

        return null;
    }

    /**
     * Full active-countries snapshot. On core, built from the local DB
     * fresh (cheap — a handful of rows). On marketing, pulled from core
     * via cached API.
     *
     * @return array{countries: array<int, array>, plan_prices: array<int, array>}
     */
    public function snapshot(): array
    {
        if (Wavadesk::isMarketing()) {
            try {
                return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
                    /** @var WavadeskApi $api */
                    $api = app(WavadeskApi::class);
                    $res = $api->countries();
                    if (! ($res['ok'] ?? false)) {
                        return ['countries' => [], 'plan_prices' => []];
                    }
                    return [
                        'countries'   => (array) ($res['body']['countries']   ?? []),
                        'plan_prices' => (array) ($res['body']['plan_prices'] ?? []),
                    ];
                });
            } catch (Throwable) {
                return ['countries' => [], 'plan_prices' => []];
            }
        }

        // Core / non-split — build from local DB, cached briefly so
        // pages that call priceFor() for every plan don't run the same
        // two queries repeatedly during a single render.
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
                return [
                    'countries'   => Country::where('is_active', true)
                        ->orderBy('name')
                        ->get(['code', 'name', 'currency_code', 'currency_symbol'])
                        ->toArray(),
                    'plan_prices' => PlanCountryPrice::query()
                        ->get(['plan_id', 'country_code', 'price_monthly', 'price_annual'])
                        ->toArray(),
                ];
            });
        } catch (Throwable) {
            return ['countries' => [], 'plan_prices' => []];
        }
    }

    /** Bust the cache after a super-admin edit so changes go live immediately on this box. */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
