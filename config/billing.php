<?php

/*
 * Fallback pricing for the billing add-ons.
 *
 * These are only used before the super admin has saved
 * /admin-control-panel/platform/addons — the live values come from
 * platform_settings via App\Support\AddonPricing, which every caller reads
 * through. Do not read this file directly from a controller or view.
 *
 * Both add-ons are ONE-OFF purchases, not recurring lines: the payment layer
 * charges single amounts (see PaymentController), so nothing here is
 * subscribed. Seats stay on the workspace, and pack messages are only drawn on
 * once the plan's monthly allowance is spent — which is why they never expire
 * at the period boundary. The page copy has to keep saying exactly that.
 */
return [
    'seat' => [
        'price' => 6.00,
        'max'   => 50,
    ],

    'ai_pack' => [
        'messages'  => 2500,
        'price'     => 9.00,
        'max_packs' => 20,
    ],

    'currency' => env('PAYPAL_CURRENCY', 'USD'),
];
