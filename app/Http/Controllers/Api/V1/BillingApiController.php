<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\TenantPayment;
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
        ]);

        $user   = $request->user();
        $tenant = $user?->tenant;

        if (! $tenant) {
            return response()->json(['message' => 'No workspace attached to this token.'], 403);
        }

        $plan = Plan::findOrFail($data['plan_id']);

        // Free plans have no checkout to initiate — the caller should have
        // gone through /api/v1/plans/choose, which grants a $0 plan on the
        // spot. Returning 422 rather than silently redirecting to /register
        // (which the web PaymentController does) is deliberate: an API caller
        // benefits from an explicit failure it can render inline.
        if (! $plan->price_monthly || (float) $plan->price_monthly === 0.0) {
            return response()->json([
                'errors' => ['plan_id' => ['This plan is free — use /api/v1/plans/choose instead of billing/checkout.']],
            ], 422);
        }

        return match ($data['provider']) {
            'stripe' => $this->checkoutStripe($tenant, $plan),
            'paypal' => $this->checkoutPaypal($tenant, $plan),
        };
    }

    /**
     * Stripe branch. Same shape as PaymentController::initiate — one
     * Checkout Session, one pending TenantPayment row keyed by the
     * session id so the webhook can settle it later.
     */
    private function checkoutStripe($tenant, Plan $plan): JsonResponse
    {
        $admin       = $tenant->users()->where('role', 'admin')->first();
        $amount      = (float) $plan->price_monthly;
        $amountCents = (int) round($amount * 100);

        try {
            $session = $this->stripe->createCheckoutSession([
                'payment_method_types' => ['card'],
                'line_items'           => [[
                    'price_data' => [
                        'currency'     => 'usd',
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
                'success_url'    => route('payment.success') . '?session_id={CHECKOUT_SESSION_ID}',
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
                'currency'            => 'USD',
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
                'currency'     => 'USD',
                'amount'       => $amount,
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
    private function checkoutPaypal($tenant, Plan $plan): JsonResponse
    {
        if (config('services.paypal.client_id')) {
            return $this->checkoutPaypalRest($tenant, $plan);
        }

        if ($this->paypalStd->isConfigured()) {
            return $this->checkoutPaypalStandard($tenant, $plan);
        }

        return response()->json([
            'message' => 'PayPal is not configured on this server.',
        ], 502);
    }

    private function checkoutPaypalRest($tenant, Plan $plan): JsonResponse
    {
        $amount = (float) $plan->price_monthly;

        try {
            $order = $this->paypal->createOrder(
                amount:      $amount,
                description: $plan->name . ' — ' . $tenant->name,
                returnUrl:   route('payment.paypal.return'),
                cancelUrl:   route('payment.paypal.cancel'),
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
                'currency'        => config('services.paypal.currency', 'USD'),
                'payment_method'  => 'paypal',
                'paypal_order_id' => $order['id'] ?? null,
                'status'          => 'pending',
                'gateway_response'=> ['order' => $order],
            ]);

            return response()->json([
                'redirect_url' => $approveUrl,
                'provider'     => 'paypal',
                'payment_id'   => $payment->id,
                'currency'     => (string) config('services.paypal.currency', 'USD'),
                'amount'       => $amount,
            ]);
        } catch (\Throwable $e) {
            Log::error('API v1 billing/checkout PayPal REST failed', [
                'tenant_id' => $tenant->id,
                'plan_id'   => $plan->id,
                'error'     => $e->getMessage(),
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
    private function checkoutPaypalStandard($tenant, Plan $plan): JsonResponse
    {
        $amount    = (float) $plan->price_monthly;
        $invoiceId = 'tenant_' . $tenant->id . '_' . time();

        $payment = TenantPayment::create([
            'tenant_id'       => $tenant->id,
            'plan_id'         => $plan->id,
            'amount'          => $amount,
            'currency'        => config('services.paypal.currency', 'USD'),
            'payment_method'  => 'paypal',
            'paypal_order_id' => $invoiceId,
            'status'          => 'pending',
            'gateway_response'=> ['mode' => 'standard'],
        ]);

        $params = $this->paypalStd->buildCheckoutParams(
            amount:        $amount,
            itemName:      $plan->name . ' — ' . $tenant->name,
            invoiceId:     $invoiceId,
            returnUrl:     route('payment.success', ['token' => $invoiceId]),
            cancelUrl:     route('payment.paypal.cancel', ['token' => $invoiceId]),
            notifyUrl:     route('payment.paypal.ipn'),
            customPayload: (string) $payment->id,
        );

        return response()->json([
            // Standard mode is a form POST, not a straight 302. The caller
            // must render an auto-submit form with these params — a plain
            // redirect will not carry the fields.
            'redirect_url'  => $this->paypalStd->getCheckoutUrl(),
            'redirect_form' => $params,
            'method'        => 'POST',
            'provider'      => 'paypal',
            'payment_id'    => $payment->id,
            'currency'      => (string) config('services.paypal.currency', 'USD'),
            'amount'        => $amount,
        ]);
    }
}
