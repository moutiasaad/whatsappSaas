<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Admin\SuperAdminPlatformController;
use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
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
        ]);
    }
}
