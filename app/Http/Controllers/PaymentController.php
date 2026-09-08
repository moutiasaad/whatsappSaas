<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\User;
use App\Services\PayPalService;
use App\Services\PayPalStandardService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
            'plan_id' => 'required|exists:plans,id',
        ]);

        // Resolve the tenant from the authenticated admin, never from a body
        // parameter — an agent posting tenant_id of a foreign tenant used to
        // rewrite that tenant's plan for free (PROC-002 cross-tenant write).
        $tenant = $request->user()->tenant;
        abort_unless($tenant, 403);

        // Do NOT persist plan_id here. Carry the picked plan through checkout
        // so it only takes effect when the payment completes via
        // activateTenantSubscription() — that closes the unpaid-upgrade path
        // where a user set the top plan and abandoned checkout to keep it.
        return redirect()->route('payment.checkout', [
            'tenant'  => $tenant->id,
            'plan_id' => (int) $request->plan_id,
        ]);
    }

    public function checkout(Request $request, Tenant $tenant)
    {
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

        return view('payment.checkout', compact('tenant', 'plan', 'admin', 'amount'));
    }

    public function initiate(Request $request)
    {
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
            $msg = config('app.debug') ? $e->getMessage() : __('auth.register.payment_init_failed');
            return redirect()->route('payment.checkout', $tenant->id)
                ->withErrors(['payment' => $msg]);
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
                $userId = session()->pull('_pending_register_user');
                if ($userId && ($user = User::find($userId))) {
                    Auth::login($user);
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

        if ($payment && $payment->isPending()) {
            $payment->update(['status' => 'failed']);
        }

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

                $this->activateTenantSubscription($payment);

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
            $msg = config('app.debug') ? $e->getMessage() : __('auth.register.payment_init_failed');
            return redirect()->route('payment.checkout', $tenant->id)
                ->withErrors(['payment' => $msg]);
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

        $paidAmount = (float) ($data['mc_gross'] ?? 0);
        if ($paidAmount + 0.001 < (float) $payment->amount) {
            Log::warning('PayPal IPN: underpayment', ['expected' => $payment->amount, 'received' => $paidAmount]);
            return response('OK');
        }

        if (!$payment->isCompleted()) {
            $payment->update([
                'status'            => 'completed',
                'paid_at'           => now(),
                'paypal_capture_id' => $data['txn_id'] ?? null,
                'gateway_response'  => array_merge(is_array($payment->gateway_response) ? $payment->gateway_response : [], ['ipn' => $data]),
            ]);
            $this->activateTenantSubscription($payment);

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
                'amount'          => $amount,
                'currency'        => config('services.paypal.currency', 'USD'),
                'payment_method'  => 'paypal',
                'paypal_order_id' => $order['id'] ?? null,
                'status'          => 'pending',
                'gateway_response'=> ['mode' => 'sdk', 'order' => $order],
            ]);

            return response()->json(['id' => $order['id']]);
        } catch (\Throwable $e) {
            Log::error('PayPal SDK create-order failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function capturePaypalOrder(Request $request, string $orderId)
    {
        $payment = TenantPayment::where('paypal_order_id', $orderId)->first();
        if (!$payment) {
            return response()->json(['error' => 'payment_not_found'], 404);
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
            Log::error('PayPal SDK capture failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function paypalReturn(Request $request)
    {
        $orderId = $request->query('token');
        if (!$orderId) {
            return redirect()->route('payment.failed');
        }
        return redirect()->route('payment.success', ['token' => $orderId]);
    }

    public function paypalCancel(Request $request)
    {
        $orderId = $request->query('token');
        $payment = $orderId
            ? TenantPayment::where('paypal_order_id', $orderId)->with('tenant', 'plan')->first()
            : null;

        if ($payment && $payment->isPending()) {
            $payment->update(['status' => 'failed']);
        }

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

                $this->activateTenantSubscription($payment);

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
            $plan = Plan::find($requested);
            if ($plan) {
                return $plan;
            }
        }

        return $tenant->plan;
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
    }
}
