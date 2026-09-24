<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Admin\SuperAdminPlatformController;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\PlanCountryPrice;
use App\Models\PlatformSetting;
use App\Services\Security\TurnstileConfig;
use Illuminate\Http\JsonResponse;

/**
 * Cross-host platform preferences the marketing app reads to keep its own
 * defaults in sync with what the super admin picked in the core panel.
 *
 * Registered only on core (see routes/api.php, `Wavadesk::isCore()` block).
 * The marketing box has its own separate DB, so without this API the default
 * language super admin sets in /admin-control-panel/platform/localization
 * would apply only to app.wavadesk.com and wavadesk.com would silently
 * stay on the old fallback.
 */
class PlatformPreferencesApiController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'default_locale' => PlatformSetting::get(
                SuperAdminPlatformController::DEFAULT_LOCALE_KEY,
                config('app.locale', 'en'),
            ),
            // The signup challenge: whether to render the widget, and the
            // public site key it needs. The secret deliberately stays here —
            // marketing forwards the token to core, which verifies it.
            'turnstile' => [
                'enabled'  => TurnstileConfig::active(),
                'site_key' => TurnstileConfig::siteKey(),
            ],
        ]);
    }

    /**
     * Countries snapshot for the marketing box.
     *
     * `countries` is the active countries the super admin has enabled
     * on core (code, name, currency_code, currency_symbol). `plan_prices`
     * is the per-plan per-country pricing snapshot. Marketing caches
     * both for 60s via CountriesRegistry so anonymous landing traffic
     * doesn't round-trip to core on every request.
     */
    public function countries(): JsonResponse
    {
        return response()->json([
            'countries' => Country::where('is_active', true)
                ->orderBy('name')
                ->get(['code', 'name', 'currency_code', 'currency_symbol']),
            'plan_prices' => PlanCountryPrice::query()
                ->get(['plan_id', 'country_code', 'price_monthly', 'price_annual']),
        ]);
    }
}
