<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\User;
use App\Services\FlouciService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(private FlouciService $flouci) {}

    public function checkout(Tenant $tenant)
    {
        $plan = $tenant->plan;

        if (!$plan || !$plan->price_monthly || (float) $plan->price_monthly === 0.0) {
            return redirect()->route('register');
        }

        if ($tenant->subscription_status === 'active') {
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

        $admin         = $tenant->users()->where('role', 'admin')->first();
        $amount        = (float) $plan->price_monthly;
        $amountMillimes = (int) round($amount * 1000);

        try {
            $result = $this->flouci->initPayment([
                'amount_millimes' => $amountMillimes,
                'currency'        => 'TND',
                'description'     => "Abonnement {$plan->name} — {$tenant->name}",
                'first_name'      => $admin ? explode(' ', $admin->name)[0] : '',
                'last_name'       => $admin ? (explode(' ', $admin->name)[1] ?? '') : '',
                'email'           => $admin?->email ?? '',
                'order_id'        => 'TENANT-' . $tenant->id . '-' . time(),
                'client_id'       => $tenant->name,
                'accept_card'     => true,
            ]);

            TenantPayment::create([
                'tenant_id'         => $tenant->id,
                'plan_id'           => $plan->id,
                'amount'            => $amount,
                'currency'          => 'TND',
                'flouci_payment_id' => $result['paymentId'],
                'flouci_pay_url'    => $result['payUrl'],
                'status'            => 'pending',
                'flouci_response'   => $result['raw'],
            ]);

            return redirect($result['payUrl']);
        } catch (\Throwable $e) {
            Log::error('Flouci initiate failed', ['error' => $e->getMessage()]);
            return redirect()->route('payment.checkout', $tenant->id)
                ->withErrors(['payment' => __('auth.register.payment_init_failed')]);
        }
    }

    public function success(Request $request)
    {
        $paymentId = $this->extractPaymentId($request);

        if (!$paymentId) {
            return view('payment.success', ['tenant' => null, 'plan' => null, 'payment' => null, 'redirectToDash' => false]);
        }

        $payment = TenantPayment::where('flouci_payment_id', $paymentId)->first();

        if ($payment && !$payment->isCompleted()) {
            $this->processPayment($payment, $paymentId);
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
        $paymentId = $this->extractPaymentId($request);

        if (!$paymentId) {
            return response()->json(['error' => 'missing payment_id'], 400);
        }

        $payment = TenantPayment::where('flouci_payment_id', $paymentId)->first();

        if (!$payment) {
            return response()->json(['error' => 'payment not found'], 404);
        }

        if (!$payment->isCompleted()) {
            $this->processPayment($payment, $paymentId);
        }

        return response()->json(['status' => 'ok']);
    }

    public function failed(Request $request)
    {
        $paymentId = $this->extractPaymentId($request);
        $payment   = $paymentId
            ? TenantPayment::where('flouci_payment_id', $paymentId)->with('tenant', 'plan')->first()
            : null;

        if ($payment && $payment->isPending()) {
            $payment->update(['status' => 'failed']);
        }

        return view('payment.failed', ['payment' => $payment]);
    }

    private function processPayment(TenantPayment $payment, string $paymentId): void
    {
        try {
            $data = $this->flouci->getPayment($paymentId);

            if ($this->flouci->isCompleted($data)) {
                $payment->update([
                    'status'          => 'completed',
                    'paid_at'         => now(),
                    'flouci_response' => $data,
                ]);

                $payment->tenant->update([
                    'subscription_status' => 'trial',
                    'trial_ends_at'       => now()->addDays(30),
                    'is_active'           => true,
                ]);

                Log::info('Tenant activated via Flouci', [
                    'tenant_id'  => $payment->tenant_id,
                    'plan_id'    => $payment->plan_id,
                    'payment_id' => $paymentId,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('processPayment failed', ['payment_id' => $paymentId, 'error' => $e->getMessage()]);
        }
    }

    private function extractPaymentId(Request $request): ?string
    {
        return $request->input('payment_id')
            ?? $request->query('payment_id')
            ?? $request->input('payment_ref')
            ?? data_get($request->all(), 'result.payment_id')
            ?? data_get($request->all(), 'payment_id');
    }
}
