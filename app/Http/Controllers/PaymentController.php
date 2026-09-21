<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\User;
use App\Services\PayPalService;
use App\Services\PayPalStandardService;
use App\Services\StripeService;
use App\Support\AddonPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(
        private StripeService $stripe,
        private PayPalService $paypal,
        private PayPalStandardService $paypalStd,
    ) {}

    public function upgrade(Request $request)
    {
        $request->validate([
            // PROC-025: reject retired plans on the upgrade path — same defence
            // as RegisterController::store, so a deactivated plan cannot be
            // reached by posting its id.
            'plan_id' => ['required', Rule::exists('plans', 'id')->where('is_active', true)],
        ]);

        // Resolve the tenant from the authenticated admin, never from a body
        // parameter — an agent posting tenant_id of a foreign tenant used to
        // rewrite that tenant's plan for free (PROC-002 cross-tenant write).
        $tenant = $request->user()->tenant;
        abort_unless($tenant, 403);

        $plan = Plan::findOrFail($request->plan_id);

        // A plan that offers a free trial is switched to immediately and billed
        // only when the trial runs out — that is what "activate free trial"
        // means. Granted once per plan per tenant (trialed_plan_ids), otherwise
        // a tenant could hop between plans to stay permanently un-billed.
        if ($plan->hasTrial() && !$tenant->hasTrialedPlan($plan->id)) {
            $tenant->markPlanTrialed($plan->id);
            $tenant->forceFill([
                'plan_id'              => $plan->id,
                'subscription_status'  => 'trial',
                'trial_ends_at'        => now()->addDays($plan->trialDays()),
                'subscription_ends_at' => null,
                'is_active'            => true,
            ])->save();

            AuditLog::record('tenant.trial_started', $tenant, [
                'plan_id'    => $plan->id,
                'trial_days' => $plan->trialDays(),
            ]);

            return redirect()
                ->route($request->user()->routeNamePrefix() . '.billing.index')
                ->with('success', __('ui.controller_messages.trial_started', [
                    'plan' => $plan->name,
                    'days' => $plan->trialDays(),
                ]));
        }

        // Do NOT persist plan_id here. Carry the picked plan through checkout
        // so it only takes effect when the payment completes via
        // activateTenantSubscription() — that closes the unpaid-upgrade path
        // where a user set the top plan and abandoned checkout to keep it.
        return redirect()->route('payment.checkout', [
            'tenant'  => $tenant->id,
            'plan_id' => (int) $request->plan_id,
        ]);
    }

    /**
     * Checkout for a one-off AI message top-up.
     *
     * Reuses the same stepped payment page as a plan purchase; `$packKind`
     * flips the copy and the line items. Scoped to the caller's own tenant —
     * the tenant is never read from the URL — so nobody can put a pack on
     * someone else's account.
     */
    public function aiPackCheckout(Request $request)
    {
        abort_unless(AddonPricing::packsEnabled(), 404);

        $data = $request->validate([
            'packs' => ['required', 'integer', 'min:1', 'max:' . AddonPricing::maxPacks()],
        ]);

        $tenant = $request->user()->tenant;
        abort_unless($tenant, 403);

        $packs = (int) $data['packs'];

        return view('payment.checkout', [
            'tenant'   => $tenant,
            'plan'     => $tenant->plan,
            'admin'    => $request->user(),
            'kind'     => TenantPayment::KIND_AI_PACK,
            'packs'    => $packs,
            'messages' => $packs * AddonPricing::packMessages(),
            'amount'   => AddonPricing::packTotal($packs),
            'backUrl'  => $this->backUrl($request),
        ]);
    }

    /**
     * Checkout for a whole order: a plan change and/or add-ons, together.
     *
     * Buying a plan, seats and messages used to mean three PayPal hand-offs in
     * a row. Everything the tenant selected is now one approval and one charge.
     * Quantities come from the request but every price comes from the server.
     */
    public function cartCheckout(Request $request)
    {
        $data = $this->validateCart($request);
        $user = $request->user();
        $tenant = $user->tenant;
        abort_unless($tenant, 403);

        $cart = $this->priceCart($data, $tenant);
        abort_if($cart['amount'] <= 0, 404);

        return view('payment.checkout', [
            'tenant' => $tenant,
            'plan'   => $cart['plan'] ?? $tenant->plan,
            'admin'  => $user,
            'kind'   => TenantPayment::KIND_CART,
            'cart'   => $cart,
            'amount' => $cart['amount'],
            'backUrl'=> $this->backUrl($request),
        ]);
    }

    /**
     * Where "Back" on a checkout page should go.
     *
     * Prefers the page the buyer actually came from, but only when it is on
     * this host and is not a checkout page itself — following an arbitrary
     * Referer would be an open redirect, and bouncing back to the checkout the
     * buyer is standing on would be a dead button. Falls back to billing for a
     * signed-in admin and to the pricing section for a guest mid-signup.
     */
    private function backUrl(Request $request): string
    {
        $previous = (string) url()->previous();

        $sameHost = $previous !== ''
            && parse_url($previous, PHP_URL_HOST) === $request->getHost();

        $isCheckout = str_contains((string) parse_url($previous, PHP_URL_PATH), '/payment/');

        if ($sameHost && !$isCheckout) {
            return $previous;
        }

        $user = $request->user();

        return $user && !$user->isSuperAdmin()
            ? route($user->routeNamePrefix() . '.billing.index')
            : url('/#pricing');
    }

    /** Shared validation for both the cart page and its PayPal order. */
    private function validateCart(Request $request): array
    {
        return $request->validate([
            'plan_id' => ['nullable', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            'seats'   => ['nullable', 'integer', 'min:0', 'max:' . AddonPricing::maxSeats()],
            'packs'   => ['nullable', 'integer', 'min:0', 'max:' . AddonPricing::maxPacks()],
            // Which PayPal screen to open on — the card form or account login.
            'card'    => ['nullable', 'boolean'],
        ]);
    }

    /**
     * Price an order from server-side values only.
     *
     * An add-on the operator has taken off sale is dropped rather than charged,
     * so a stale page cannot buy something no longer offered.
     */
    private function priceCart(array $data, $tenant): array
    {
        $plan  = !empty($data['plan_id']) ? Plan::where('id', $data['plan_id'])->where('is_active', true)->first() : null;
        $seats = AddonPricing::seatsEnabled() ? max(0, (int) ($data['seats'] ?? 0)) : 0;
        $packs = AddonPricing::packsEnabled() ? max(0, (int) ($data['packs'] ?? 0)) : 0;

        // An unlimited-AI plan makes message packs meaningless — do not sell one
        // alongside a plan that already grants unlimited messages.
        $targetPlan = $plan ?: $tenant->plan;
        if ($targetPlan && $targetPlan->ai_message_quota === null) {
            $packs = 0;
        }

        $planCost = $plan ? round((float) $plan->price_monthly, 2) : 0.0;

        return [
            'plan'      => $plan,
            'seats'     => $seats,
            'packs'     => $packs,
            'messages'  => $packs * AddonPricing::packMessages(),
            'plan_cost' => $planCost,
            'seat_cost' => AddonPricing::seatTotal($seats),
            'pack_cost' => AddonPricing::packTotal($packs),
            'amount'    => round($planCost + AddonPricing::seatTotal($seats) + AddonPricing::packTotal($packs), 2),
        ];
    }

    /**
     * Checkout for extra agent seats. Same shape as the AI pack: priced from
     * platform settings and billed to the signed-in admin's own tenant.
     */
    public function seatPackCheckout(Request $request)
    {
        abort_unless(AddonPricing::seatsEnabled(), 404);

        $data = $request->validate([
            'seats' => ['required', 'integer', 'min:1', 'max:' . AddonPricing::maxSeats()],
        ]);

        $tenant = $request->user()->tenant;
        abort_unless($tenant, 403);

        $seats = (int) $data['seats'];

        return view('payment.checkout', [
            'tenant' => $tenant,
            'plan'   => $tenant->plan,
            'admin'  => $request->user(),
            'kind'   => TenantPayment::KIND_SEAT_PACK,
            'seats'  => $seats,
            'amount' => AddonPricing::seatTotal($seats),
            'backUrl'=> $this->backUrl($request),
        ]);
    }

    public function checkout(Request $request, Tenant $tenant)
    {
        // Marketing branch is a different route (no {tenant} model binding —
        // marketing's local tenants table is stale). Dispatched from the
        // route file, so this method still owns the single-host path.
        if (\App\Support\Wavadesk::isMarketing()) {
            return $this->checkoutMarketing($request);
        }

        $plan = $this->resolveIntendedPlan($request, $tenant);

        // Free plan — authenticated admin goes to their dashboard, guest goes to register
        if (!$plan || !$plan->price_monthly || (float) $plan->price_monthly === 0.0) {
            if (Auth::check()) {
                return redirect()->route(Auth::user()->homeRouteName());
            }
            return redirect()->route('register');
        }

        // Already active — only block guests (new signups), not admins doing upgrades/renewals
        if (!Auth::check() && $tenant->subscription_status === 'active') {
            return redirect()->route('login')->with('info', __('auth.register.subscription_already_active'));
        }

        $admin  = $tenant->users()->where('role', 'admin')->first();
        $amount = (float) $plan->price_monthly;

        return view('payment.checkout', [
            'tenant'  => $tenant,
            'plan'    => $plan,
            'admin'   => $admin,
            'amount'  => $amount,
            'backUrl' => $this->backUrl($request),
        ]);
    }

    public function initiate(Request $request)
    {
        if (\App\Support\Wavadesk::isMarketing()) {
            return $this->initiateViaCoreApi($request, 'stripe');
        }

        $request->validate(['tenant_id' => 'required|exists:tenants,id']);

        $tenant = Tenant::with(['plan', 'users'])->findOrFail($request->tenant_id);
        $plan   = $this->resolveIntendedPlan($request, $tenant);

        if (!$plan || !$plan->price_monthly || (float) $plan->price_monthly === 0.0) {
            return redirect()->route('register');
        }

        $admin        = $tenant->users()->where('role', 'admin')->first();
        $amount       = (float) $plan->price_monthly;
        $amountCents  = (int) round($amount * 100);

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
                'mode'          => 'payment',
                'customer_email'=> $admin?->email,
                'success_url'   => route('payment.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'    => route('payment.failed'),
                'metadata'      => [
                    'tenant_id' => (string) $tenant->id,
                    'plan_id'   => (string) $plan->id,
                ],
            ]);

            TenantPayment::create([
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

            return redirect($session->url);
        } catch (\Throwable $e) {
            Log::error('Stripe initiate failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            // UI-003: never surface raw gateway exception text to the visitor,
            // even under APP_DEBUG. The failure is already in Log::error above
            // with full context for operators.
            return redirect()->route('payment.checkout', $tenant->id)
                ->withErrors(['payment' => __('auth.register.payment_init_failed')]);
        }
    }

    public function success(Request $request)
    {
        $sessionId = $request->query('session_id');
        $orderId   = $request->query('token'); // PayPal returns ?token={ORDER_ID}

        if (!$sessionId && !$orderId) {
            return view('payment.success', ['tenant' => null, 'plan' => null, 'payment' => null, 'redirectToDash' => false]);
        }

        $payment = $sessionId
            ? TenantPayment::where('stripe_session_id', $sessionId)->first()
            : TenantPayment::where('paypal_order_id', $orderId)->first();

        if ($payment && !$payment->isCompleted()) {
            if ($payment->payment_method === 'paypal') {
                // Standard-payments rows are IPN-driven — no REST capture. If the IPN
                // has already fired by the time the browser returns, we're already
                // completed. Otherwise the success view will show a "processing" state
                // and the IPN will complete the row in the background.
                $mode = is_array($payment->gateway_response) ? ($payment->gateway_response['mode'] ?? null) : null;
                if ($mode !== 'standard') {
                    $this->capturePaypalPayment($payment);
                }
            } else {
                $this->processPayment($payment, $sessionId);
            }
            $payment->refresh();
            $payment->load('tenant', 'plan');
        }

        if ($payment?->isCompleted()) {
            if (!Auth::check()) {
                // Split-hosting path: BillingApiController mints a signed
                // handoff code and embeds it in the success_url so the buyer
                // is auto-logged-in on the core app they never opened a
                // session on. Try that first; fall back to the older
                // single-host session key for the monolith flow.
                if ($sso = (string) $request->query('sso', '')) {
                    try {
                        $userId = app(\App\Services\Auth\SsoHandoffCode::class)->verify($sso);
                        if ($user = User::find($userId)) {
                            Auth::login($user);
                            $request->session()->regenerate();
                        }
                    } catch (\Throwable $e) {
                        Log::warning('payment.success SSO redemption failed', [
                            'reason' => $e->getMessage(),
                        ]);
                        // Fall through — the user can log in manually.
                    }
                }

                if (!Auth::check()) {
                    $userId = session()->pull('_pending_register_user');
                    if ($userId && ($user = User::find($userId))) {
                        Auth::login($user);
                    }
                }
            }

            return view('payment.success', [
                'tenant'         => $payment->tenant,
                'plan'           => $payment->plan,
                'payment'        => $payment,
                'redirectToDash' => Auth::check(),
            ]);
        }

        return view('payment.success', [
            'tenant'         => $payment?->tenant,
            'plan'           => $payment?->plan,
            'payment'        => $payment,
            'redirectToDash' => false,
        ]);
    }

    public function webhook(Request $request)
    {
        $payload   = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            if ($webhookSecret && $signature) {
                $event = $this->stripe->constructWebhookEvent($payload, $signature);
            } else {
                $data  = json_decode($payload, true);
                $event = (object) ['type' => $data['type'] ?? '', 'data' => (object) ['object' => (object) ($data['data']['object'] ?? [])]];
            }

            if ($event->type === 'checkout.session.completed') {
                $session   = $event->data->object;
                $sessionId = is_object($session) ? $session->id : ($session['id'] ?? null);

                if ($sessionId) {
                    $payment = TenantPayment::where('stripe_session_id', $sessionId)->first();
                    if ($payment && !$payment->isCompleted()) {
                        $this->processPayment($payment, $sessionId);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Stripe webhook failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json(['status' => 'ok']);
    }

    public function failed(Request $request)
    {
        $sessionId = $request->query('session_id');
        $payment   = $sessionId
            ? TenantPayment::where('stripe_session_id', $sessionId)->with('tenant', 'plan')->first()
            : null;

        // Read-only: the customer's browser landing here is a hint, not a
        // settlement fact — Stripe's checkout.session.expired webhook is the
        // authoritative source of a failed status. Writing here also let anyone
        // who knew the session id flip a still-pending payment to failed.
        return view('payment.failed', ['payment' => $payment]);
    }

    private function processPayment(TenantPayment $payment, string $sessionId): void
    {
        try {
            $session = $this->stripe->retrieveSession($sessionId);

            if ($this->stripe->isCompleted($session)) {
                $payment->update([
                    'status'           => 'completed',
                    'paid_at'          => now(),
                    'gateway_response' => $session->toArray(),
                ]);

                $this->fulfilPayment($payment);

                Log::info('Tenant activated via Stripe', [
                    'tenant_id'  => $payment->tenant_id,
                    'plan_id'    => $payment->plan_id,
                    'session_id' => $sessionId,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('processPayment failed', ['session_id' => $sessionId, 'error' => $e->getMessage()]);
        }
    }

    // ── PayPal ──────────────────────────────────────────────────────────────

    public function initiatePaypal(Request $request)
    {
        if (\App\Support\Wavadesk::isMarketing()) {
            return $this->initiateViaCoreApi($request, 'paypal');
        }

        $request->validate(['tenant_id' => 'required|exists:tenants,id']);

        $tenant = Tenant::with(['plan', 'users'])->findOrFail($request->tenant_id);
        $plan   = $this->resolveIntendedPlan($request, $tenant);

        if (!$plan || !$plan->price_monthly || (float) $plan->price_monthly === 0.0) {
            return redirect()->route('register');
        }

        // Mode selection: REST (OAuth) if client_id is set; otherwise Standard
        // Payments (email-only). Standard requires just PAYPAL_PAYEE_EMAIL.
        if (config('services.paypal.client_id')) {
            return $this->initiatePaypalRest($tenant, $plan);
        }

        if ($this->paypalStd->isConfigured()) {
            return $this->initiatePaypalStandard($tenant, $plan);
        }

        return redirect()->route('payment.checkout', $tenant->id)
            ->withErrors(['payment' => 'PayPal is not configured on this server.']);
    }

    private function initiatePaypalRest(Tenant $tenant, Plan $plan)
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
                ],
            );

            $approveUrl = $this->paypal->extractApproveUrl($order);
            if (!$approveUrl) {
                throw new \RuntimeException('PayPal approve URL not found in order response.');
            }

            TenantPayment::create([
                'tenant_id'       => $tenant->id,
                'plan_id'         => $plan->id,
                'amount'          => $amount,
                'currency'        => config('services.paypal.currency', 'USD'),
                'payment_method'  => 'paypal',
                'paypal_order_id' => $order['id'] ?? null,
                'status'          => 'pending',
                'gateway_response'=> ['order' => $order],
            ]);

            return redirect($approveUrl);
        } catch (\Throwable $e) {
            Log::error('PayPal REST initiate failed', ['error' => $e->getMessage()]);
            // UI-003: never surface raw gateway exception text to the visitor,
            // even under APP_DEBUG. The failure is already in Log::error above
            // with full context for operators.
            return redirect()->route('payment.checkout', $tenant->id)
                ->withErrors(['payment' => __('auth.register.payment_init_failed')]);
        }
    }

    private function initiatePaypalStandard(Tenant $tenant, Plan $plan)
    {
        $amount    = (float) $plan->price_monthly;
        $invoiceId = 'tenant_' . $tenant->id . '_' . time();

        $payment = TenantPayment::create([
            'tenant_id'       => $tenant->id,
            'plan_id'         => $plan->id,
            'amount'          => $amount,
            'currency'        => config('services.paypal.currency', 'USD'),
            'payment_method'  => 'paypal',
            'paypal_order_id' => $invoiceId, // Standard flow keys on invoice id
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

        // Return a self-submitting form so the browser POSTs to PayPal.
        return response()
            ->view('payment.paypal-standard-redirect', [
                'action' => $this->paypalStd->getCheckoutUrl(),
                'params' => $params,
            ]);
    }

    /**
     * Start an email-only (PayPal Standard) checkout for any order shape.
     *
     * Standard mode needs no OAuth app — just PAYPAL_PAYEE_EMAIL — so it is what
     * runs when no client id is configured. The trade-off is that completion is
     * IPN-driven: the buyer returns before PayPal has told us anything, and the
     * success page shows a processing state until the IPN lands.
     *
     * Prices are computed here from plans and platform settings, exactly as the
     * REST path does; nothing about the amount comes from the browser.
     */
    public function paypalStandardStart(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->isAdmin() || !$user->tenant) {
            abort(403);
        }

        abort_unless($this->paypalStd->isConfigured(), 404);

        $tenant = $user->tenant;
        $cart   = $this->priceCart($this->validateCart($request), $tenant);

        abort_if($cart['amount'] <= 0, 404);

        $parts = array_filter([
            $cart['plan']?->name,
            $cart['seats'] ? $cart['seats'] . ' seats' : null,
            $cart['packs'] ? number_format($cart['messages']) . ' AI messages' : null,
        ]);

        $invoiceId = 'std_' . $tenant->id . '_' . time();

        $payment = TenantPayment::create([
            'tenant_id'       => $tenant->id,
            'plan_id'         => $cart['plan']?->id,
            'kind'            => TenantPayment::KIND_CART,
            'amount'          => $cart['amount'],
            'currency'        => config('services.paypal.currency', 'USD'),
            'payment_method'  => 'paypal',
            // The Standard flow has no order id until the IPN arrives, so the
            // invoice id is what both sides key on.
            'paypal_order_id' => $invoiceId,
            'status'          => 'pending',
            'gateway_response'=> ['mode' => 'standard'],
            'metadata'        => [
                'plan_id'  => $cart['plan']?->id,
                'seats'    => $cart['seats'],
                'packs'    => $cart['packs'],
                'messages' => $cart['messages'],
            ],
        ]);

        $params = $this->paypalStd->buildCheckoutParams(
            amount:        $cart['amount'],
            itemName:      implode(' + ', $parts) . ' — ' . $tenant->name,
            invoiceId:     $invoiceId,
            returnUrl:     route('payment.success', ['token' => $invoiceId]),
            cancelUrl:     route('payment.paypal.cancel', ['token' => $invoiceId]),
            notifyUrl:     route('payment.paypal.ipn'),
            customPayload: (string) $payment->id,
            preferCard:    $request->boolean('card'),
        );

        return response()->view('payment.paypal-standard-redirect', [
            'action' => $this->paypalStd->getCheckoutUrl(),
            'params' => $params,
        ]);
    }

    public function paypalIpn(Request $request)
    {
        $rawBody = $request->getContent();

        if (!$this->paypalStd->verifyIpn($rawBody)) {
            Log::warning('PayPal IPN: verification failed', ['body_preview' => substr($rawBody, 0, 200)]);
            return response('INVALID', 400);
        }

        $data = $request->all();

        // Only complete payments — PayPal IPN also fires for refunds, disputes, etc.
        if (($data['payment_status'] ?? '') !== 'Completed') {
            Log::info('PayPal IPN: non-completed status ignored', [
                'status' => $data['payment_status'] ?? null,
                'txn_id' => $data['txn_id'] ?? null,
            ]);
            return response('OK');
        }

        $payment = null;
        if (!empty($data['custom']) && is_numeric($data['custom'])) {
            $payment = TenantPayment::find((int) $data['custom']);
        }
        if (!$payment && !empty($data['invoice'])) {
            $payment = TenantPayment::where('paypal_order_id', $data['invoice'])->first();
        }

        if (!$payment) {
            Log::warning('PayPal IPN: no matching payment', ['invoice' => $data['invoice'] ?? null, 'custom' => $data['custom'] ?? null]);
            return response('OK');
        }

        // Guard: amount + currency + payee email must match what we sent.
        $expectedEmail = strtolower((string) config('services.paypal.payee_email'));
        $receivedEmail = strtolower((string) ($data['receiver_email'] ?? $data['business'] ?? ''));
        if ($expectedEmail && $receivedEmail && $expectedEmail !== $receivedEmail) {
            Log::warning('PayPal IPN: payee mismatch', ['expected' => $expectedEmail, 'received' => $receivedEmail]);
            return response('OK');
        }

        // CALC-004: the comment above claimed a currency check that did not
        // exist. Without it, a $30/month plan could be settled by paying 30
        // units of a weak currency (~$1.50). Compare currency codes first,
        // then amounts as integer minor units so floating-point drift can
        // never mask underpayment. The 0.001 epsilon was a legacy TND
        // (three-decimal) workaround; no PayPal Standard flow charges in
        // three-decimal currencies today.
        $paidCurrency     = strtoupper((string) ($data['mc_currency'] ?? ''));
        $expectedCurrency = strtoupper((string) $payment->currency);
        if ($paidCurrency !== $expectedCurrency) {
            Log::warning('PayPal IPN: currency mismatch', [
                'expected' => $expectedCurrency,
                'received' => $paidCurrency,
                'txn_id'   => $data['txn_id'] ?? null,
            ]);
            return response('OK');
        }

        $paidCents     = (int) round(((float) ($data['mc_gross'] ?? 0)) * 100);
        $expectedCents = (int) round(((float) $payment->amount) * 100);
        if ($paidCents < $expectedCents) {
            Log::warning('PayPal IPN: underpayment', [
                'expected_cents' => $expectedCents,
                'received_cents' => $paidCents,
                'currency'       => $expectedCurrency,
                'txn_id'         => $data['txn_id'] ?? null,
            ]);
            return response('OK');
        }

        if (!$payment->isCompleted()) {
            $payment->update([
                'status'            => 'completed',
                'paid_at'           => now(),
                'paypal_capture_id' => $data['txn_id'] ?? null,
                'gateway_response'  => array_merge(is_array($payment->gateway_response) ? $payment->gateway_response : [], ['ipn' => $data]),
            ]);
            $this->fulfilPayment($payment);

            Log::info('Tenant activated via PayPal Standard IPN', [
                'tenant_id' => $payment->tenant_id,
                'plan_id'   => $payment->plan_id,
                'txn_id'    => $data['txn_id'] ?? null,
            ]);
        }

        return response('OK');
    }

    // ── PayPal Smart Buttons (JS SDK) ───────────────────────────────────────
    //
    // Renders PayPal's official 3-button stack (PayPal / Pay Later / Card) on
    // the checkout page. Requires PAYPAL_CLIENT_ID. The SDK calls createOrder
    // first (server creates the PayPal order + persists a pending payment),
    // then onApprove (server captures + activates the tenant subscription).

    public function createPaypalOrder(Request $request)
    {
        // Marketing branch — the PayPal SDK's createOrder() call landed
        // here. Proxy to core's /api/v1/billing/paypal/create-order using
        // the session PAT; the response shape ({id}) is what the SDK
        // expects to hand to the buyer's browser.
        if (\App\Support\Wavadesk::isMarketing()) {
            return $this->createPaypalOrderViaCoreApi($request);
        }

        // An AI pack is priced from config and billed to the signed-in admin's
        // own tenant — never from a client-supplied amount or tenant id.
        if ($request->input('kind') === TenantPayment::KIND_AI_PACK) {
            return $this->createAiPackOrder($request);
        }

        if ($request->input('kind') === TenantPayment::KIND_SEAT_PACK) {
            return $this->createSeatPackOrder($request);
        }

        if ($request->input('kind') === TenantPayment::KIND_CART) {
            return $this->createCartOrder($request);
        }

        $request->validate(['tenant_id' => 'required|exists:tenants,id']);

        $tenant = Tenant::with('plan')->findOrFail($request->tenant_id);
        $plan   = $this->resolveIntendedPlan($request, $tenant);

        if (!$plan || !$plan->price_monthly || (float) $plan->price_monthly === 0.0) {
            return response()->json(['error' => 'no_price'], 422);
        }

        $amount = (float) $plan->price_monthly;

        try {
            $order = $this->paypal->createOrder(
                amount:      $amount,
                description: $plan->name . ' — ' . $tenant->name,
                // Not used by the SDK flow, but PayPal still requires these on the order.
                returnUrl:   route('payment.paypal.return'),
                cancelUrl:   route('payment.paypal.cancel'),
                metadata:    [
                    'tenant_id'  => $tenant->id,
                    'invoice_id' => 'tenant_' . $tenant->id . '_' . time(),
                ],
            );

            TenantPayment::create([
                'tenant_id'       => $tenant->id,
                'plan_id'         => $plan->id,
                'kind'            => TenantPayment::KIND_SUBSCRIPTION,
                'amount'          => $amount,
                'currency'        => config('services.paypal.currency', 'USD'),
                'payment_method'  => 'paypal',
                'paypal_order_id' => $order['id'] ?? null,
                'status'          => 'pending',
                'gateway_response'=> ['mode' => 'sdk', 'order' => $order],
            ]);

            return response()->json(['id' => $order['id']]);
        } catch (\Throwable $e) {
            // UI-003: opaque error to the visitor; full detail is in the log.
            Log::error('PayPal SDK create-order failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'create_order_failed'], 500);
        }
    }

    private function createAiPackOrder(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->isAdmin() || !$user->tenant) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        abort_unless(AddonPricing::packsEnabled(), 404);

        $data = $request->validate([
            'packs' => ['required', 'integer', 'min:1', 'max:' . AddonPricing::maxPacks()],
        ]);

        $tenant   = $user->tenant;
        $packs    = (int) $data['packs'];
        $messages = $packs * AddonPricing::packMessages();
        $amount   = AddonPricing::packTotal($packs);

        try {
            $order = $this->paypal->createOrder(
                amount:      $amount,
                description: $messages . ' AI messages — ' . $tenant->name,
                returnUrl:   route('payment.paypal.return'),
                cancelUrl:   route('payment.paypal.cancel'),
                metadata:    [
                    'tenant_id'  => $tenant->id,
                    'invoice_id' => 'aipack_' . $tenant->id . '_' . time(),
                ],
            );

            TenantPayment::create([
                'tenant_id'       => $tenant->id,
                // No plan_id: a pack does not change what the tenant subscribes to.
                'plan_id'         => null,
                'kind'            => TenantPayment::KIND_AI_PACK,
                'amount'          => $amount,
                'currency'        => config('services.paypal.currency', 'USD'),
                'payment_method'  => 'paypal',
                'paypal_order_id' => $order['id'] ?? null,
                'status'          => 'pending',
                'gateway_response'=> ['mode' => 'sdk', 'order' => $order],
                'metadata'        => ['packs' => $packs, 'messages' => $messages],
            ]);

            return response()->json(['id' => $order['id']]);
        } catch (\Throwable $e) {
            Log::error('PayPal AI-pack create-order failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'create_order_failed'], 500);
        }
    }

    private function createSeatPackOrder(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->isAdmin() || !$user->tenant) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        abort_unless(AddonPricing::seatsEnabled(), 404);

        $data = $request->validate([
            'seats' => ['required', 'integer', 'min:1', 'max:' . AddonPricing::maxSeats()],
        ]);

        $tenant = $user->tenant;
        $seats  = (int) $data['seats'];
        $amount = AddonPricing::seatTotal($seats);

        try {
            $order = $this->paypal->createOrder(
                amount:      $amount,
                description: $seats . ' agent seats — ' . $tenant->name,
                returnUrl:   route('payment.paypal.return'),
                cancelUrl:   route('payment.paypal.cancel'),
                metadata:    [
                    'tenant_id'  => $tenant->id,
                    'invoice_id' => 'seats_' . $tenant->id . '_' . time(),
                ],
            );

            TenantPayment::create([
                'tenant_id'       => $tenant->id,
                'plan_id'         => null,
                'kind'            => TenantPayment::KIND_SEAT_PACK,
                'amount'          => $amount,
                'currency'        => config('services.paypal.currency', 'USD'),
                'payment_method'  => 'paypal',
                'paypal_order_id' => $order['id'] ?? null,
                'status'          => 'pending',
                'gateway_response'=> ['mode' => 'sdk', 'order' => $order],
                'metadata'        => ['seats' => $seats],
            ]);

            return response()->json(['id' => $order['id']]);
        } catch (\Throwable $e) {
            Log::error('PayPal seat create-order failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'create_order_failed'], 500);
        }
    }

    private function createCartOrder(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->isAdmin() || !$user->tenant) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        $tenant = $user->tenant;
        $cart   = $this->priceCart($this->validateCart($request), $tenant);

        if ($cart['amount'] <= 0) {
            return response()->json(['error' => 'empty_cart'], 422);
        }

        $parts = array_filter([
            $cart['plan']?->name,
            $cart['seats'] ? $cart['seats'] . ' seats' : null,
            $cart['packs'] ? number_format($cart['messages']) . ' AI messages' : null,
        ]);

        try {
            $order = $this->paypal->createOrder(
                amount:      $cart['amount'],
                description: implode(' + ', $parts) . ' — ' . $tenant->name,
                returnUrl:   route('payment.paypal.return'),
                cancelUrl:   route('payment.paypal.cancel'),
                metadata:    [
                    'tenant_id'  => $tenant->id,
                    'invoice_id' => 'cart_' . $tenant->id . '_' . time(),
                ],
            );

            TenantPayment::create([
                'tenant_id'       => $tenant->id,
                // plan_id is set only when the order actually changes the plan,
                // so activateTenantSubscription has something to switch to.
                'plan_id'         => $cart['plan']?->id,
                'kind'            => TenantPayment::KIND_CART,
                'amount'          => $cart['amount'],
                'currency'        => config('services.paypal.currency', 'USD'),
                'payment_method'  => 'paypal',
                'paypal_order_id' => $order['id'] ?? null,
                'status'          => 'pending',
                'gateway_response'=> ['mode' => 'sdk', 'order' => $order],
                'metadata'        => [
                    'plan_id'  => $cart['plan']?->id,
                    'seats'    => $cart['seats'],
                    'packs'    => $cart['packs'],
                    'messages' => $cart['messages'],
                ],
            ]);

            return response()->json(['id' => $order['id']]);
        } catch (\Throwable $e) {
            Log::error('PayPal cart create-order failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'create_order_failed'], 500);
        }
    }

    public function capturePaypalOrder(Request $request, string $orderId)
    {
        // Marketing branch — SDK's onApprove() called our /capture-order,
        // which just forwards to core. Core does the actual capture +
        // fulfilment and returns {success, redirect}. The redirect points
        // back at core with an SSO code so the buyer auto-logs-in on
        // landing, same as the /billing/checkout return path.
        if (\App\Support\Wavadesk::isMarketing()) {
            return $this->capturePaypalOrderViaCoreApi($request, $orderId);
        }

        $payment = TenantPayment::where('paypal_order_id', $orderId)->first();
        if (!$payment) {
            return response()->json(['error' => 'payment_not_found'], 404);
        }

        // A plan purchase can be captured by the guest who is mid-registration,
        // so it stays open. A pack credits an existing tenant's balance, so only
        // that tenant's own admin may finish it.
        if ($payment->isAiPack() || $payment->isSeatPack() || $payment->isCart()) {
            $user = $request->user();
            if (!$user || !$user->isAdmin() || $user->tenant_id !== $payment->tenant_id) {
                return response()->json(['error' => 'forbidden'], 403);
            }
        }

        try {
            $this->capturePaypalPayment($payment);
            $payment->refresh();

            if ($payment->isCompleted()) {
                // Log the buyer in if this was a fresh registration.
                if (!Auth::check()) {
                    $userId = session()->pull('_pending_register_user');
                    if ($userId && ($user = User::find($userId))) {
                        Auth::login($user);
                    }
                }

                return response()->json([
                    'success'  => true,
                    'redirect' => route('payment.success', ['token' => $orderId]),
                ]);
            }

            return response()->json(['success' => false, 'error' => 'not_completed'], 402);
        } catch (\Throwable $e) {
            // UI-003: opaque error to the visitor; full detail is in the log.
            Log::error('PayPal SDK capture failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'capture_failed'], 500);
        }
    }

    public function paypalReturn(Request $request)
    {
        $orderId = $request->query('token');
        if (!$orderId) {
            return redirect()->route('payment.failed');
        }
        // Forward the split-hosting SSO handoff (if present) so the /success
        // handler can auto-log-in the buyer from marketing-originated PayPal
        // returns, same as the Stripe path.
        $args = ['token' => $orderId];
        if ($sso = (string) $request->query('sso', '')) {
            $args['sso'] = $sso;
        }
        return redirect()->route('payment.success', $args);
    }

    public function paypalCancel(Request $request)
    {
        $orderId = $request->query('token');
        $payment = $orderId
            ? TenantPayment::where('paypal_order_id', $orderId)->with('tenant', 'plan')->first()
            : null;

        // Read-only — see PaymentController::failed(). PayPal's cancellation
        // webhook decides the terminal status; this view only informs the user.
        return view('payment.failed', ['payment' => $payment]);
    }

    public function paypalWebhook(Request $request)
    {
        $rawBody = $request->getContent();
        $headers = [];
        foreach ($request->headers->all() as $key => $values) {
            $headers[strtolower($key)] = is_array($values) ? ($values[0] ?? '') : $values;
        }

        try {
            $verified = $this->paypal->verifyWebhookSignature($headers, $rawBody);
            if (!$verified) {
                Log::warning('PayPal webhook signature invalid');
                return response()->json(['error' => 'invalid signature'], 400);
            }

            $event = json_decode($rawBody, true) ?: [];
            $type  = $event['event_type'] ?? '';

            if (in_array($type, ['CHECKOUT.ORDER.APPROVED', 'PAYMENT.CAPTURE.COMPLETED'], true)) {
                $orderId = $event['resource']['id']
                    ?? $event['resource']['supplementary_data']['related_ids']['order_id']
                    ?? null;

                if ($orderId) {
                    $payment = TenantPayment::where('paypal_order_id', $orderId)->first();
                    if ($payment && !$payment->isCompleted()) {
                        $this->capturePaypalPayment($payment);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('PayPal webhook failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json(['status' => 'ok']);
    }

    private function capturePaypalPayment(TenantPayment $payment): void
    {
        try {
            $orderId = $payment->paypal_order_id;
            if (!$orderId) {
                return;
            }

            $order = $this->paypal->getOrder($orderId);

            // Only capture if approved but not yet captured
            if (($order['status'] ?? null) === 'APPROVED') {
                $capture = $this->paypal->captureOrder($orderId);
            } else {
                $capture = $order;
            }

            if ($this->paypal->isCompleted($capture)) {
                $payment->update([
                    'status'            => 'completed',
                    'paid_at'           => now(),
                    'paypal_capture_id' => $this->paypal->extractCaptureId($capture),
                    'gateway_response'  => ['order' => $order, 'capture' => $capture],
                ]);

                $this->fulfilPayment($payment);

                Log::info('Tenant activated via PayPal', [
                    'tenant_id' => $payment->tenant_id,
                    'plan_id'   => $payment->plan_id,
                    'order_id'  => $orderId,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('capturePaypalPayment failed', [
                'payment_id' => $payment->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    // ── Marketing role (Server A / wavadesk.com) ────────────────────────────

    /**
     * Render the pay-with-Stripe/PayPal button page on the marketing host.
     *
     * Reads tenant + user snapshots from the session (stashed by
     * RegisterController::storeViaCoreApi at register time). The view is the
     * same auth/register-plan-adjacent payment.checkout blade the core role
     * uses; tenant + plan get wrapped into stdClass so the view's property
     * access works with no Blade changes.
     */
    private function checkoutMarketing(Request $request)
    {
        $token      = (string) session(\App\Support\Wavadesk::SESSION_TOKEN, '');
        $userData   = (array)  session(\App\Support\Wavadesk::SESSION_USER, []);
        $tenantData = (array)  session(\App\Support\Wavadesk::SESSION_TENANT, []);

        if ($token === '' || empty($userData['id']) || empty($tenantData['id'])) {
            return redirect()->route('register');
        }

        $planId = (int) $request->query('plan_id');
        if (!$planId) {
            // No plan on the URL — the user got here directly, without a
            // pick. Send them back to the picker rather than 500.
            return redirect()->route('register.plan');
        }

        $plan = Plan::where('id', $planId)->where('is_active', true)->first();
        abort_unless($plan, 404);

        // A free plan shouldn't reach checkout — /api/v1/plans/choose
        // activates it directly. Guard the case anyway (e.g. a paid plan the
        // super admin has since flipped to free between picker and pay).
        if (!$plan->price_monthly || (float) $plan->price_monthly === 0.0) {
            return redirect()->route('register.plan');
        }

        // View expects Model-like access on $tenant + $admin — wrap the
        // session arrays so we don't have to touch the Blade.
        $tenant = (object) [
            'id'                  => (int)    $tenantData['id'],
            'name'                => (string) ($tenantData['name'] ?? ''),
            'subscription_status' => (string) ($tenantData['subscription_status'] ?? 'trial'),
        ];

        $admin = (object) [
            'email' => (string) ($userData['email'] ?? ''),
        ];

        // Render the same rich two-column checkout the core view uses. The
        // PayPal SDK's createOrder / onApprove callbacks land on marketing
        // routes /payment/paypal/create-order + /payment/paypal/capture-order,
        // which proxy to /api/v1/billing/paypal/* on core carrying the
        // session PAT — see createPaypalOrderViaCoreApi() /
        // capturePaypalOrderViaCoreApi(). CardFields eligibility requires
        // wavadesk.com to be whitelisted in developer.paypal.com; without
        // that, the SDK falls back to the hosted card button.
        $sdkReady = (bool) config('services.paypal.client_id');

        // Optional client token for CardFields; only fetched when the REST
        // credentials work. A failure here just skips the token — the SDK
        // still renders in test mode without it.
        $clientToken = null;
        if ($sdkReady) {
            try {
                if ($this->paypal->credentialsValid()) {
                    $clientToken = $this->paypal->clientToken();
                }
            } catch (\Throwable $e) {
                Log::warning('Marketing checkout: PayPal client-token fetch failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Localise the amount + currency for the visitor's country. DetectCountry
        // middleware already resolved it and shared it as $visitorCountry;
        // Plan::priceFor() falls back to base USD when no localised price
        // exists for that country. amount / currency drive what Stripe / PayPal
        // charge; baseAmount is shown in parens next to the local amount
        // so the visitor always sees the USD reference.
        $priced = $plan->priceFor(view()->shared('visitorCountry') ?? null, 'monthly');
        $baseAmount = (float) $plan->price_monthly;

        return view('payment.checkout-marketing', [
            'tenant'         => $tenant,
            'plan'           => $plan,
            'admin'          => $admin,
            'amount'         => (float) $priced['amount'],
            'currency'       => $priced['currency'],
            'currencySymbol' => $priced['symbol'],
            'isLocal'        => (bool) $priced['is_local'],
            'baseAmount'     => $baseAmount, // always base USD
            'backUrl'        => route('register.plan'),
            'sdkReady'       => $sdkReady,
            'clientToken'    => $clientToken,
        ]);
    }

    /**
     * Marketing branch of /payment/paypal/create-order.
     *
     * Forwards to /api/v1/billing/paypal/create-order on core with the
     * session PAT. Returns the response body unchanged — the PayPal SDK
     * needs {id: <order id>} to hand to the buyer's browser.
     */
    private function createPaypalOrderViaCoreApi(Request $request)
    {
        $token = (string) session(\App\Support\Wavadesk::SESSION_TOKEN, '');
        if ($token === '') {
            return response()->json(['error' => 'session_expired'], 401);
        }

        $data = $request->validate([
            'plan_id' => ['required', 'integer'],
        ]);

        $result = app(\App\Services\WavadeskApi::class)
            ->paypalCreateOrder($token, (int) $data['plan_id']);

        if (! $result['ok']) {
            Log::warning('Marketing -> core paypal/create-order failed', [
                'status' => $result['status'],
            ]);

            return response()->json(
                ['error' => 'create_order_failed', 'body' => $result['body']],
                $result['status'] > 0 ? $result['status'] : 502,
            );
        }

        return response()->json($result['body']);
    }

    /**
     * Marketing branch of /payment/paypal/capture-order/{orderId}.
     *
     * Forwards to /api/v1/billing/paypal/capture-order/{id} on core with
     * the session PAT. Returns the response body unchanged — the SDK
     * expects {success, redirect} to navigate to the core success page.
     */
    private function capturePaypalOrderViaCoreApi(Request $request, string $orderId)
    {
        $token = (string) session(\App\Support\Wavadesk::SESSION_TOKEN, '');
        if ($token === '') {
            return response()->json(['success' => false, 'error' => 'session_expired'], 401);
        }

        $result = app(\App\Services\WavadeskApi::class)
            ->paypalCaptureOrder($token, $orderId);

        if (! $result['ok']) {
            Log::warning('Marketing -> core paypal/capture-order failed', [
                'status'   => $result['status'],
                'order_id' => $orderId,
            ]);

            return response()->json(
                ['success' => false, 'error' => 'capture_failed', 'body' => $result['body']],
                $result['status'] > 0 ? $result['status'] : 502,
            );
        }

        return response()->json($result['body']);
    }

    /**
     * Marketing branch for /payment/initiate and /payment/paypal/initiate.
     *
     * Proxies to POST /api/v1/billing/checkout with the session PAT, then
     * 302s the browser to the returned hosted-checkout URL. For PayPal
     * Standard (form POST rather than a straight 302), renders an
     * auto-submit form the way the core initiatePaypalStandard does.
     */
    private function initiateViaCoreApi(Request $request, string $provider)
    {
        $token = (string) session(\App\Support\Wavadesk::SESSION_TOKEN, '');
        if ($token === '') {
            return redirect()->route('register');
        }

        $data = $request->validate([
            'plan_id' => ['required', 'integer'],
        ]);

        // Marketing knows the visitor's country from DetectCountry middleware.
        // Forward it so core prices the checkout in the local currency —
        // without this the API would fall back to its own DetectCountry pass
        // (which sees an internal server-to-server call, not the visitor).
        $country = (string) (view()->shared('visitorCountry') ?? session(\App\Http\Middleware\DetectCountry::SESSION_KEY, ''));

        $result = app(\App\Services\WavadeskApi::class)
            ->billingCheckout($token, (int) $data['plan_id'], $provider, $country ?: null);

        if (!$result['ok']) {
            // 422 → field error (retired plan, provider mismatch). Anything
            // else is our problem, not the buyer's, so show a generic retry.
            if ($result['status'] === 422 && !empty($result['body']['errors'])) {
                return back()->withErrors((array) $result['body']['errors']);
            }

            Log::warning('Marketing -> core billing/checkout failed', [
                'status'   => $result['status'],
                'provider' => $provider,
                // Body is the only signal we have when core throws — a timeout
                // shows here as an empty body + status 0, and a PayPal upstream
                // failure comes through as the message core logged.
                'body'     => $result['body'],
            ]);

            return back()->withErrors([
                'payment' => __('auth.register.payment_init_failed'),
            ]);
        }

        $body = $result['body'];

        // PayPal Standard: form POST rather than 302 — render the
        // auto-submit form on marketing exactly as core does.
        if (($body['method'] ?? null) === 'POST' && !empty($body['redirect_form'])) {
            return response()->view('payment.paypal-standard-redirect', [
                'action' => (string) $body['redirect_url'],
                'params' => (array)  $body['redirect_form'],
            ]);
        }

        return redirect()->away((string) $body['redirect_url']);
    }

    /**
     * Resolve the plan the caller intends to pay for. If plan_id is present on
     * the request (from an upgrade link that carried it through checkout), use
     * that; otherwise fall back to the tenant's currently assigned plan (the
     * renewal / initial-checkout case). Falling back to the tenant plan means
     * the change never persists until the payment completes.
     */
    private function resolveIntendedPlan(Request $request, Tenant $tenant): ?Plan
    {
        $requested = (int) $request->input('plan_id', 0);
        if ($requested > 0) {
            // PROC-025: /payment/checkout/{tenant}?plan_id=X is reachable
            // without auth, so scope the lookup to active plans. A retired id
            // falls through to the tenant's current plan (the normal renewal
            // case) instead of silently subscribing them to a withdrawn plan.
            $plan = Plan::where('id', $requested)->where('is_active', true)->first();
            if ($plan) {
                return $plan;
            }
        }

        return $tenant->plan;
    }

    /**
     * Apply a completed payment.
     *
     * Every gateway callback lands here rather than calling
     * activateTenantSubscription() directly: a one-off AI pack must NOT move
     * the tenant's plan or renewal date, which is exactly what the old
     * unconditional activation would have done to any non-subscription row.
     */
    private function fulfilPayment(TenantPayment $payment): void
    {
        if ($payment->isAiPack()) {
            $this->creditAiPack($payment);

            return;
        }

        if ($payment->isSeatPack()) {
            $this->creditSeatPack($payment);

            return;
        }

        if ($payment->isCart()) {
            $this->fulfilCart($payment);

            return;
        }

        $this->activateTenantSubscription($payment);
    }

    /**
     * Grant the messages a completed pack purchase paid for.
     *
     * Guarded by a `credited_at` stamp in metadata so a webhook arriving after
     * the browser-side capture — both of which reach this method — cannot
     * double-credit the tenant.
     */
    private function creditAiPack(TenantPayment $payment): void
    {
        $metadata = $payment->metadata ?? [];

        if (!empty($metadata['credited_at'])) {
            return;
        }

        $messages = $payment->packMessages();
        $tenant   = $payment->tenant;

        if ($messages <= 0 || !$tenant) {
            Log::warning('AI pack payment completed with nothing to credit', [
                'payment_id' => $payment->id,
                'messages'   => $messages,
            ]);

            return;
        }

        $settings = $tenant->aiSettings;

        if (!$settings) {
            // A tenant who never opened the AI page has no settings row yet;
            // the credit still has to land somewhere it will be found later.
            $settings = \App\Models\AiSettings::create([
                'tenant_id'             => $tenant->id,
                'monthly_message_quota' => $tenant->plan?->ai_message_quota,
            ]);
        }

        $settings->creditMessages($messages);

        $payment->forceFill([
            'metadata' => array_merge($metadata, ['credited_at' => now()->toIso8601String()]),
        ])->save();

        AuditLog::record('tenant.ai_pack_purchased', $tenant, [
            'payment_id' => $payment->id,
            'packs'      => $metadata['packs'] ?? null,
            'messages'   => $messages,
            'amount'     => (float) $payment->amount,
        ]);

        Log::info('AI message pack credited', [
            'tenant_id' => $tenant->id,
            'messages'  => $messages,
            'payment_id'=> $payment->id,
        ]);
    }

    /**
     * Grant the agent seats a completed purchase paid for.
     *
     * Same `credited_at` guard as the AI pack: the browser capture and the
     * webhook both land here, and a tenant must not be given the seats twice.
     */
    private function creditSeatPack(TenantPayment $payment): void
    {
        $metadata = $payment->metadata ?? [];

        if (!empty($metadata['credited_at'])) {
            return;
        }

        $seats  = $payment->packSeats();
        $tenant = $payment->tenant;

        if ($seats <= 0 || !$tenant) {
            Log::warning('Seat payment completed with nothing to credit', [
                'payment_id' => $payment->id,
                'seats'      => $seats,
            ]);

            return;
        }

        $tenant->creditSeats($seats);

        $payment->forceFill([
            'metadata' => array_merge($metadata, ['credited_at' => now()->toIso8601String()]),
        ])->save();

        AuditLog::record('tenant.seats_purchased', $tenant, [
            'payment_id' => $payment->id,
            'seats'      => $seats,
            'amount'     => (float) $payment->amount,
        ]);

        Log::info('Agent seats credited', [
            'tenant_id'  => $tenant->id,
            'seats'      => $seats,
            'payment_id' => $payment->id,
        ]);
    }

    /**
     * Apply everything one combined order paid for.
     *
     * Guarded as a whole rather than per part: activateTenantSubscription
     * extends the renewal date, so replaying it would hand out a free month.
     * The stamp is written before any of the three effects so a mid-way failure
     * cannot be re-run into a double credit either.
     */
    private function fulfilCart(TenantPayment $payment): void
    {
        $metadata = $payment->metadata ?? [];

        if (!empty($metadata['credited_at'])) {
            return;
        }

        $tenant = $payment->tenant;

        if (!$tenant) {
            Log::warning('Cart payment completed with no tenant', ['payment_id' => $payment->id]);

            return;
        }

        $payment->forceFill([
            'metadata' => array_merge($metadata, ['credited_at' => now()->toIso8601String()]),
        ])->save();

        $seats    = (int) ($metadata['seats'] ?? 0);
        $messages = (int) ($metadata['messages'] ?? 0);

        if ($payment->plan_id) {
            $this->activateTenantSubscription($payment);
        }

        if ($seats > 0) {
            $tenant->creditSeats($seats);
        }

        if ($messages > 0) {
            // Re-read after a possible plan change: activateTenantSubscription
            // resets the quota, and the top-up must land on top of the new one.
            $settings = $tenant->fresh()->aiSettings ?: \App\Models\AiSettings::create([
                'tenant_id'             => $tenant->id,
                'monthly_message_quota' => $tenant->fresh()->plan?->ai_message_quota,
            ]);

            $settings->creditMessages($messages);
        }

        AuditLog::record('tenant.order_completed', $tenant, [
            'payment_id' => $payment->id,
            'plan_id'    => $payment->plan_id,
            'seats'      => $seats,
            'messages'   => $messages,
            'amount'     => (float) $payment->amount,
        ]);

        Log::info('Combined order fulfilled', [
            'tenant_id'  => $tenant->id,
            'payment_id' => $payment->id,
            'plan_id'    => $payment->plan_id,
            'seats'      => $seats,
            'messages'   => $messages,
        ]);
    }

    private function activateTenantSubscription(TenantPayment $payment): void
    {
        $tenant = $payment->tenant;
        if (!$tenant) {
            return;
        }

        $currentEnd    = $tenant->subscription_ends_at;
        $hasLivePeriod = $currentEnd && $currentEnd->isFuture();

        // Renewing early must not discard time already paid for: extend from the
        // existing end date when it is still in the future, otherwise from today.
        $base = $hasLivePeriod ? $currentEnd : now();

        $previousPlanId = $tenant->plan_id;

        $tenant->update([
            'plan_id'                => $payment->plan_id,
            'subscription_status'    => 'active',
            // starts_at anchors the period, so it survives a renewal and only
            // resets when there was no live period to extend.
            'subscription_starts_at' => $hasLivePeriod
                ? $tenant->subscription_starts_at
                : now(),
            // NoOverflow: Jan 31 + 1 month is Feb 28, not Mar 3.
            'subscription_ends_at'   => $base->copy()->addMonthNoOverflow(),
            'is_active'              => true,
        ]);

        // Sync AI quota from the newly-assigned plan whenever the plan
        // actually changed. Same "Overwrite every tenant" rule that fires
        // on SuperAdmin plan edits — a fresh subscription starts with the
        // plan's current quota, not whatever the tenant had on the old plan.
        if ($previousPlanId !== $payment->plan_id && $tenant->aiSettings) {
            $newPlan = $tenant->plan()->first();
            $tenant->aiSettings->update([
                'monthly_message_quota' => $newPlan?->ai_message_quota,
            ]);
        }
    }
}
