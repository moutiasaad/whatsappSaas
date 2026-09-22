<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\TenantPayment;
use App\Services\Auth\SsoHandoffCode;
use App\Services\PayPalService;
use App\Services\PayPalStandardService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * BillingApiController (Server B — app.wavadesk.com)
 *
 * Second half of the "move plan pick + billing to wavadesk.com" roadmap
 * item. The marketing site's plan picker calls `/api/v1/plans/choose`
 * first: a free/trial plan activates on the spot, a paid plan returns
 * `next_step=checkout`. This endpoint is what the marketing site posts to
 * for the second half — it creates a Stripe Checkout Session or a PayPal
 * order on the core app and returns the hosted-checkout URL for the
 * marketing site to 302 the browser to.
 *
 * Return/cancel/webhook URLs stay pointing at core on purpose: the
 * payment provider needs a stable callback that owns the tenant row, and
 * the browser can be back on the panel (which is on core anyway) once
 * the payment captures.
 *
 * Guarded by `wavadesk.caller` + `auth:sanctum`. The tenant is derived
 * from the user's PAT, never from the request body — a caller cannot
 * name a workspace they do not own.
 *
 * @see \App\Http\Controllers\PaymentController::initiate
 * @see \App\Http\Controllers\PaymentController::initiatePaypal
 */
class BillingApiController extends Controller
{
    public function __construct(
        private StripeService $stripe,
        private PayPalService $paypal,
        private PayPalStandardService $paypalStd,
    ) {}

    /**
     * POST /api/v1/billing/checkout
     *
     * Body: { plan_id: int, provider: "stripe" | "paypal" }
     * Returns:
     *   200 { redirect_url: str, provider: str, payment_id: int, currency: str, amount: float }
     *   422 (validation)
     *   403 (auth ok but no tenant)
     *   422 { errors: { plan_id: [...] } } if plan is free/$0 — use /choose instead
     *   502 { message: str } gateway failure (details logged, not returned)
     */
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id'  => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            'provider' => ['required', 'string', Rule::in(['stripe', 'paypal'])],
            // Optional ISO 3166-1 alpha-2. When present, the localised price
            // for this country is used instead of the plan's base USD price.
            // Marketing sends the value it got from DetectCountry middleware;
            // the API also runs DetectCountry, so an absent param falls back
            // to session/CF-IPCountry/default just like everywhere else.
            'country'  => ['nullable', 'string', 'size:2', 'alpha'],
        ]);

        $user   = $request->user();
        $tenant = $user?->tenant;

        if (! $tenant) {
            return response()->json(['message' => 'No workspace attached to this token.'], 403);
        }

        // Outer safety net: any Throwable that bubbles out of the branch
        // calls below (SsoHandoffCode singleton throwing on a missing
        // WAVADESK_SHARED_SECRET, priceFor() blowing up on a stale country
        // cache, PayPal SDK ctor issues, DB constraint on TenantPayment)
        // becomes a Laravel-rendered JSON 502 with a log line the operator
        // can grep for, instead of leaking as an HTTP 500 the marketing box
        // logs as body:[]. Empty-body 502s on marketing's log line for this
        // endpoint therefore point at the web-server / Cloudflare layer,
        // not Laravel — a useful signal for the next round of diagnosis.
        try {
            $plan = Plan::findOrFail($data['plan_id']);

            // Free plans have no checkout to initiate — the caller should have
            // gone through /api/v1/plans/choose, which grants a $0 plan on the
            // spot. Returning 422 rather than silently redirecting to /register
            // (which the web PaymentController does) is deliberate: an API
            // caller benefits from an explicit failure it can render inline.
            if (! $plan->price_monthly || (float) $plan->price_monthly === 0.0) {
                return response()->json([
                    'errors' => ['plan_id' => ['This plan is free — use /api/v1/plans/choose instead of billing/checkout.']],
                ], 422);
            }

            // Country resolution order: explicit body param → view()->shared
            // value that DetectCountry seeded from the header/session/default.
            // Either way Plan::priceFor() guarantees a safe USD fallback if
            // the resolved code isn't a country we sell in.
            $country = strtoupper((string) ($data['country'] ?? view()->shared('visitorCountry') ?? ''));
            $priced  = $plan->priceFor($country, 'monthly');

            Log::info('API v1 billing/checkout entered', [
                'tenant_id' => $tenant->id,
                'plan_id'   => $plan->id,
                'provider'  => $data['provider'],
                'country'   => $country ?: null,
                'currency'  => $priced['currency'] ?? null,
                'amount'    => $priced['amount'] ?? null,
            ]);

            return match ($data['provider']) {
                'stripe' => $this->checkoutStripe($tenant, $plan, (int) $user->id, $priced),
                'paypal' => $this->checkoutPaypal($tenant, $plan, (int) $user->id, $priced),
            };
        } catch (\Throwable $e) {
            Log::error('API v1 billing/checkout crashed before gateway call', [
                'tenant_id' => $tenant->id,
                'plan_id'   => $data['plan_id'] ?? null,
                'provider'  => $data['provider'] ?? null,
                'type'      => get_class($e),
                'error'     => $e->getMessage(),
                'file'      => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json(['message' => 'Payment initiation failed.'], 502);
        }
    }

    /**
     * Stripe branch. Same shape as PaymentController::initiate — one
     * Checkout Session, one pending TenantPayment row keyed by the
     * session id so the webhook can settle it later.
     */
    private function checkoutStripe($tenant, Plan $plan, int $userId, array $priced): JsonResponse
    {
        $admin        = $tenant->users()->where('role', 'admin')->first();
        $amount       = (float) $priced['amount'];
        $currency     = strtolower((string) $priced['currency']); // Stripe expects lowercase
        $amountCents  = (int) round($amount * 100);
        $baseUsd      = (float) $plan->price_monthly; // snapshot for super-admin visibility

        try {
            $session = $this->stripe->createCheckoutSession([
                'payment_method_types' => ['card'],
                'line_items'           => [[
                    'price_data' => [
                        'currency'     => $currency,
                        'product_data' => [
                            'name'        => $plan->name . ' — ' . $tenant->name,
                            'description' => "Monthly subscription for {$tenant->name}",
                        ],
                        'unit_amount' => $amountCents,
                    ],
                    'quantity' => 1,
                ]],
                'mode'           => 'payment',
                'customer_email' => $admin?->email,
                // Callbacks stay on core: Stripe expects a stable URL from the
                // origin that created the session, and the fulfilment path
                // (which writes tenants + activates the plan) already lives here.
                // The ?sso= code is what auto-logs the user in on the return —
                // the marketing app never opened a session on core, so without
                // it the user would land on /payment/success as a guest.
                // 30 min gives realistic checkout latency (card 3DS, PayPal
                // Standard IPN gap, "did I move my card" browsing pauses).
                'success_url'    => route('payment.success') . '?session_id={CHECKOUT_SESSION_ID}&sso='
                                    . urlencode(app(SsoHandoffCode::class)->mint($userId, 1800)),
                'cancel_url'     => route('payment.failed'),
                'metadata'       => [
                    'tenant_id' => (string) $tenant->id,
                    'plan_id'   => (string) $plan->id,
                    // Marks the row as API-originated so payments coming from
                    // the marketing signup can be told apart from the web flow
                    // when the audit needs it.
                    'source'    => 'api.v1.billing.checkout',
                ],
            ]);

            $payment = TenantPayment::create([
                'tenant_id'           => $tenant->id,
                'plan_id'             => $plan->id,
                'amount'              => $amount,
                'currency'            => strtoupper($currency),
                'base_amount_usd'     => $baseUsd,
                'payment_method'      => 'stripe',
                'stripe_session_id'   => $session->id,
                'stripe_checkout_url' => $session->url,
                'status'              => 'pending',
                'gateway_response'    => ['session_id' => $session->id],
            ]);

            return response()->json([
                'redirect_url' => $session->url,
                'provider'     => 'stripe',
                'payment_id'   => $payment->id,
                'currency'     => strtoupper($currency),
                'amount'       => $amount,
                'base_amount_usd' => $baseUsd,
            ]);
        } catch (\Throwable $e) {
            Log::error('API v1 billing/checkout Stripe failed', [
                'tenant_id' => $tenant->id,
                'plan_id'   => $plan->id,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Payment initiation failed.',
            ], 502);
        }
    }

    /**
     * PayPal branch — REST (OAuth) mode if a client id is configured,
     * else Standard (email-only). Same selection logic as
     * PaymentController::initiatePaypal so a server configured for one
     * mode on the web flow behaves identically on the API.
     */
    private function checkoutPaypal($tenant, Plan $plan, int $userId, array $priced): JsonResponse
    {
        // Guard against local currencies PayPal's Orders API doesn't take
        // (e.g. TND, DZD, MAD): sending them earns a 422
        // UNPROCESSABLE_ENTITY / CURRENCY_NOT_SUPPORTED, which the branch
        // catch renders as a generic "Could not initialize payment" with
        // no way forward for the buyer. Fall back to the plan's base USD
        // price so the checkout completes, and log the swap for audit.
        $priced = PayPalService::compatiblePricing($plan, $priced, $tenant->id);

        // credentialsValid() proves the OAuth creds work by fetching a token
        // (6s + cache). Config presence alone is not enough — a box with a
        // half-configured PAYPAL_CLIENT_ID/SECRET pair returns REST 401s
        // and the buyer sees "Impossible d'initialiser le paiement". Falling
        // straight through to Standard when the token fetch fails keeps
        // checkout working while the operator sorts REST out.
        if ($this->paypal->credentialsValid()) {
            return $this->checkoutPaypalRest($tenant, $plan, $userId, $priced);
        }

        if ($this->paypalStd->isConfigured()) {
            return $this->checkoutPaypalStandard($tenant, $plan, $userId, $priced);
        }

        return response()->json([
            'message' => 'PayPal is not configured on this server.',
        ], 502);
    }

    private function checkoutPaypalRest($tenant, Plan $plan, int $userId, array $priced): JsonResponse
    {
        $amount   = (float) $priced['amount'];
        $currency = strtoupper((string) $priced['currency']);
        $baseUsd  = (float) $plan->price_monthly;

        try {
            // Same rationale as the Stripe branch: PayPal returns to core,
            // and the marketing app never opened a session there, so bake
            // an SSO code into the return URL to auto-log the buyer in on
            // landing. Kept inside try/catch so a missing WAVADESK_SHARED_SECRET
            // (SsoHandoffCode singleton ctor throws RuntimeException) surfaces
            // as a Laravel-rendered JSON 502 instead of a raw 500.
            $sso = app(SsoHandoffCode::class)->mint($userId, 1800);

            $order = $this->paypal->createOrder(
                amount:      $amount,
                description: $plan->name . ' — ' . $tenant->name,
                returnUrl:   route('payment.paypal.return') . '?sso=' . urlencode($sso),
                cancelUrl:   route('payment.paypal.cancel'),
                currency:    $currency,
                metadata:    [
                    'tenant_id'  => $tenant->id,
                    'invoice_id' => 'tenant_' . $tenant->id . '_' . time(),
                    'source'     => 'api.v1.billing.checkout',
                ],
            );

            $approveUrl = $this->paypal->extractApproveUrl($order);
            if (! $approveUrl) {
                throw new \RuntimeException('PayPal approve URL not found in order response.');
            }

            $payment = TenantPayment::create([
                'tenant_id'       => $tenant->id,
                'plan_id'         => $plan->id,
                'amount'          => $amount,
                'currency'        => $currency,
                'base_amount_usd' => $baseUsd,
                'payment_method'  => 'paypal',
                'paypal_order_id' => $order['id'] ?? null,
                'status'          => 'pending',
                'gateway_response'=> ['order' => $order],
            ]);

            return response()->json([
                'redirect_url'    => $approveUrl,
                'provider'        => 'paypal',
                'payment_id'      => $payment->id,
                'currency'        => $currency,
                'amount'          => $amount,
                'base_amount_usd' => $baseUsd,
            ]);
        } catch (\Throwable $e) {
            Log::error('API v1 billing/checkout PayPal REST failed', [
                'tenant_id' => $tenant->id,
                'plan_id'   => $plan->id,
                'type'      => get_class($e),
                'error'     => $e->getMessage(),
                'file'      => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json(['message' => 'Payment initiation failed.'], 502);
        }
    }

    /**
     * PayPal Standard is a form-POST redirect, not a straight 302. The API
     * cannot hand a browser a self-submitting form, so it returns the
     * checkout URL plus the pre-computed parameter map for the marketing
     * app to render its own auto-submit form. Mirrors what
     * PaymentController::initiatePaypalStandard renders inline.
     */
    private function checkoutPaypalStandard($tenant, Plan $plan, int $userId, array $priced): JsonResponse
    {
        $amount    = (float) $priced['amount'];
        $currency  = strtoupper((string) $priced['currency']);
        $baseUsd   = (float) $plan->price_monthly;
        $invoiceId = 'tenant_' . $tenant->id . '_' . time();

        try {
            $payment = TenantPayment::create([
                'tenant_id'       => $tenant->id,
                'plan_id'         => $plan->id,
                'amount'          => $amount,
                'currency'        => $currency,
                'base_amount_usd' => $baseUsd,
                'payment_method'  => 'paypal',
                'paypal_order_id' => $invoiceId,
                'status'          => 'pending',
                'gateway_response'=> ['mode' => 'standard'],
            ]);

            // Inside try/catch so a missing WAVADESK_SHARED_SECRET can't
            // slip past this branch as a raw 500. Same rationale as the
            // REST branch above.
            $sso = app(SsoHandoffCode::class)->mint($userId, 1800);

            $params = $this->paypalStd->buildCheckoutParams(
                amount:        $amount,
                itemName:      $plan->name . ' — ' . $tenant->name,
                invoiceId:     $invoiceId,
                returnUrl:     route('payment.success', ['token' => $invoiceId, 'sso' => $sso]),
                cancelUrl:     route('payment.paypal.cancel', ['token' => $invoiceId]),
                notifyUrl:     route('payment.paypal.ipn'),
                customPayload: (string) $payment->id,
                currency:      $currency,
            );

            return response()->json([
                // Standard mode is a form POST, not a straight 302. The caller
                // must render an auto-submit form with these params — a plain
                // redirect will not carry the fields.
                'redirect_url'    => $this->paypalStd->getCheckoutUrl(),
                'redirect_form'   => $params,
                'method'          => 'POST',
                'provider'        => 'paypal',
                'payment_id'      => $payment->id,
                'currency'        => $currency,
                'amount'          => $amount,
                'base_amount_usd' => $baseUsd,
            ]);
        } catch (\Throwable $e) {
            Log::error('API v1 billing/checkout PayPal Standard failed', [
                'tenant_id' => $tenant->id,
                'plan_id'   => $plan->id,
                'type'      => get_class($e),
                'error'     => $e->getMessage(),
                'file'      => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json(['message' => 'Payment initiation failed.'], 502);
        }
    }

    /**
     * POST /api/v1/billing/paypal/create-order
     *
     * Marketing-side PayPal SDK create-order proxy. The marketing checkout
     * page mounts the same PayPal SDK the core view does; the SDK's
     * createOrder() hits marketing's /payment/paypal/create-order, which
     * proxies to here. Returns the PayPal order id the SDK needs to
     * authorise the payment on the buyer's browser.
     *
     * Body: { plan_id: int }
     * Returns 200 { id: str, payment_id: int }
     */
    public function paypalCreateOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
        ]);

        $user   = $request->user();
        $tenant = $user?->tenant;

        if (! $tenant) {
            return response()->json(['message' => 'No workspace attached to this token.'], 403);
        }

        $plan = Plan::findOrFail($data['plan_id']);

        if (! $plan->price_monthly || (float) $plan->price_monthly === 0.0) {
            return response()->json([
                'errors' => ['plan_id' => ['This plan is free — use /api/v1/plans/choose instead of billing/paypal/create-order.']],
            ], 422);
        }

        $amount = (float) $plan->price_monthly;

        try {
            $order = $this->paypal->createOrder(
                amount:      $amount,
                description: $plan->name . ' — ' . $tenant->name,
                // Not actually used by the SDK flow — the browser stays put
                // and the capture round-trips via /capture-order — but PayPal
                // still requires these on the order body.
                returnUrl:   route('payment.paypal.return'),
                cancelUrl:   route('payment.paypal.cancel'),
                metadata:    [
                    'tenant_id'  => $tenant->id,
                    'invoice_id' => 'tenant_' . $tenant->id . '_' . time(),
                    'source'     => 'api.v1.billing.paypal.create-order',
                ],
            );

            $payment = TenantPayment::create([
                'tenant_id'       => $tenant->id,
                'plan_id'         => $plan->id,
                'amount'          => $amount,
                'currency'        => config('services.paypal.currency', 'USD'),
                'payment_method'  => 'paypal',
                'paypal_order_id' => $order['id'] ?? null,
                'status'          => 'pending',
                'gateway_response'=> ['mode' => 'sdk', 'order' => $order],
            ]);

            return response()->json([
                'id'         => $order['id'] ?? null,
                'payment_id' => $payment->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('API v1 billing/paypal create-order failed', [
                'tenant_id' => $tenant->id,
                'plan_id'   => $plan->id,
                'error'     => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Payment initiation failed.'], 502);
        }
    }

    /**
     * POST /api/v1/billing/paypal/capture-order/{orderId}
     *
     * Marketing-side PayPal SDK capture-order proxy. Called via
     * /payment/paypal/capture-order/{id} on marketing after the buyer
     * approves the payment on the SDK's inline card fields or PayPal button.
     * Captures the order, activates the subscription, and returns a
     * redirect URL back to core's success page with an SSO handoff code so
     * the browser lands on core auto-logged-in.
     *
     * Returns 200 { success: true, redirect: str } on capture.
     */
    public function paypalCaptureOrder(Request $request, string $orderId): JsonResponse
    {
        $user   = $request->user();
        $tenant = $user?->tenant;

        if (! $tenant) {
            return response()->json(['success' => false, 'error' => 'no_tenant'], 403);
        }

        $payment = TenantPayment::where('paypal_order_id', $orderId)->first();
        if (! $payment) {
            return response()->json(['success' => false, 'error' => 'payment_not_found'], 404);
        }

        // The token drives who the payment belongs to; a caller cannot
        // capture an order that belongs to another workspace.
        if ((int) $payment->tenant_id !== (int) $tenant->id) {
            return response()->json(['success' => false, 'error' => 'forbidden'], 403);
        }

        // Idempotent: if the previous capture already completed (browser
        // reloaded, marketing timed out and retried, webhook raced ahead),
        // return the same success shape without another round-trip to
        // PayPal. Without this, a retry lands on capture_failed even
        // though the money is already ours and the plan is already active.
        if ($payment->isCompleted()) {
            $sso = app(SsoHandoffCode::class)->mint((int) $user->id, 1800);

            return response()->json([
                'success'  => true,
                'redirect' => route('payment.success', ['token' => $orderId, 'sso' => $sso]),
            ]);
        }

        try {
            $this->capturePaypalPayment($payment);
            $payment->refresh();

            if (! $payment->isCompleted()) {
                return response()->json(['success' => false, 'error' => 'not_completed'], 402);
            }

            // Redirect back to core's /payment/success with an SSO code so
            // the buyer is auto-logged-in the moment they land. 30 min TTL
            // matches the checkout window used by /billing/checkout.
            $sso = app(SsoHandoffCode::class)->mint((int) $user->id, 1800);
            $redirect = route('payment.success', ['token' => $orderId, 'sso' => $sso]);

            return response()->json([
                'success'  => true,
                'redirect' => $redirect,
            ]);
        } catch (\Throwable $e) {
            Log::error('API v1 billing/paypal capture-order failed', [
                'tenant_id' => $tenant->id,
                'order_id'  => $orderId,
                'error'     => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'error' => 'capture_failed'], 500);
        }
    }

    /**
     * Capture a pending PayPal order and, on completion, activate the
     * tenant's subscription. Mirrors PaymentController::capturePaypalPayment
     * + activateTenantSubscription so the API path applies the same effect
     * as the web SDK path — plan updated, dates rolled, quota re-synced.
     */
    private function capturePaypalPayment(TenantPayment $payment): void
    {
        $orderId = $payment->paypal_order_id;
        if (! $orderId) {
            return;
        }

        $order = $this->paypal->getOrder($orderId);

        // Only capture if approved but not yet captured.
        $capture = ($order['status'] ?? null) === 'APPROVED'
            ? $this->paypal->captureOrder($orderId)
            : $order;

        if (! $this->paypal->isCompleted($capture)) {
            return;
        }

        $payment->update([
            'status'            => 'completed',
            'paid_at'           => now(),
            'paypal_capture_id' => $this->paypal->extractCaptureId($capture),
            'gateway_response'  => ['order' => $order, 'capture' => $capture],
        ]);

        $this->activateTenantSubscription($payment);

        Log::info('Tenant activated via PayPal API', [
            'tenant_id' => $payment->tenant_id,
            'plan_id'   => $payment->plan_id,
            'order_id'  => $orderId,
        ]);
    }

    /**
     * Copy of PaymentController::activateTenantSubscription. Duplicated
     * because that method is private and this API path lands here without
     * hopping through the web controller. Any change to the fulfilment
     * shape has to be made in both.
     */
    private function activateTenantSubscription(TenantPayment $payment): void
    {
        $tenant = $payment->tenant;
        if (! $tenant) {
            return;
        }

        $currentEnd    = $tenant->subscription_ends_at;
        $hasLivePeriod = $currentEnd && $currentEnd->isFuture();
        $base          = $hasLivePeriod ? $currentEnd : now();

        $previousPlanId = $tenant->plan_id;

        $tenant->update([
            'plan_id'                => $payment->plan_id,
            'subscription_status'    => 'active',
            'subscription_starts_at' => $hasLivePeriod ? $tenant->subscription_starts_at : now(),
            'subscription_ends_at'   => $base->copy()->addMonthNoOverflow(),
            'is_active'              => true,
        ]);

        if ($previousPlanId !== $payment->plan_id && $tenant->aiSettings) {
            $newPlan = $tenant->plan()->first();
            $tenant->aiSettings->update([
                'monthly_message_quota' => $newPlan?->ai_message_quota,
            ]);
        }
    }
}
