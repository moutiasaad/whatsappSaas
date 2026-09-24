<?php

namespace App\Support;

use App\Models\Plan;

/**
 * PaypalNcp
 *
 * One rule, asked in three places: the core checkout page, the API the
 * marketing host calls, and the button's own POST endpoint. A paypal.com/ncp
 * page charges one fixed amount in one currency and tells us nothing
 * afterwards, so it can only ever stand in for the simplest possible order —
 * a bare plan at its own price. Everything else goes back down the Orders API
 * path, which prices the order server side and reports its capture.
 */
final class PaypalNcp
{
    /**
     * The NCP page that may stand in for this order, or null when it may not.
     *
     * @param  bool    $hasAddon  the order carries seats or AI packs
     * @param  string  $currency  what the buyer is being shown and charged
     */
    public static function linkFor(?Plan $plan, float $amount, bool $hasAddon = false, string $currency = 'USD'): ?string
    {
        if (! $plan || $hasAddon) {
            return null;
        }

        // A localised price (TND, DZD…) on a page that will charge USD would
        // show the buyer one figure and take another.
        if (strtoupper($currency) !== 'USD') {
            return null;
        }

        // A proration or a discount has the same problem for the same reason.
        if (abs($amount - (float) $plan->price_monthly) >= 0.005) {
            return null;
        }

        return $plan->paypal_ncp_link ?: (config('services.paypal.ncp_link') ?: null);
    }
}
