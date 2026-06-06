<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(private StripeService $stripe) {}

    public function upgrade(Request $request)
    {
        $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'plan_id'   => 'required|exists:plans,id',
        ]);

        $tenant = Tenant::findOrFail($request->tenant_id);
        $plan   = Plan::findOrFail($request->plan_id);

        $tenant->update(['plan_id' => $plan->id]);

        return redirect()->route('payment.checkout', $tenant);
    }

    public function checkout(Tenant $tenant)
    {
        $plan = $tenant->plan;

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
        $plan   = $tenant->plan;

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

        if (!$sessionId) {
            return view('payment.success', ['tenant' => null, 'plan' => null, 'payment' => null, 'redirectToDash' => false]);
        }

        $payment = TenantPayment::where('stripe_session_id', $sessionId)->first();

        if ($payment && !$payment->isCompleted()) {
            $this->processPayment($payment, $sessionId);
            $payment->refresh();
            $payment->load('tenant', 'plan');
        }

        if ($payment?->isCompleted()) {
            if (!Auth::check()) {
                $userId = session()->pull('_pending_register_user');
                $user   = $userId
                    ? User::find($userId)
                    : $payment->tenant?->users()->where('role', 'admin')->where('is_active', true)->first();

                if ($user) {
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

                $tenant    = $payment->tenant;
                $isUpgrade = $tenant->is_active; // existing tenant upgrading / renewing

                $tenant->update($isUpgrade ? [
                    'plan_id'              => $payment->plan_id,
                    'subscription_status'  => 'active',
                    'subscription_ends_at' => now()->addMonth(),
                    'is_active'            => true,
                ] : [
                    'subscription_status'  => 'trial',
                    'trial_ends_at'        => now()->addDays(30),
                    'is_active'            => true,
                ]);

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
}
