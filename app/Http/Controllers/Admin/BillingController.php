<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\User;
use App\Support\AddonPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BillingController extends Controller
{
    public function index()
    {
        if (auth()->user()->isSuperAdmin()) {
            $tenants = Tenant::with('plan')->orderBy('name')->get();
            $plans   = Plan::where('is_active', true)->orderBy('price_monthly')->get();

            return view('admin.billing.super-admin', compact('tenants', 'plans'));
        }

        $tenant = $this->currentTenant();
        $plans  = Plan::where('is_active', true)->orderBy('price_monthly')->orderBy('id')->get();

        $tenant->loadMissing('plan', 'aiSettings');
        $ai = $tenant->aiSettings;

        // Usage the page's two meters read. A null AI quota means unlimited, 0
        // means the plan carries no AI at all — the view renders those states
        // rather than dividing by them.
        $usage = [
            'users'       => User::where('tenant_id', $tenant->id)->count(),
            // Purchased seats are part of the ceiling the meter measures against.
            'user_limit'  => (int) ($tenant->plan?->max_users ?: 0) + (int) $tenant->extra_seats,
            'extra_seats' => (int) $tenant->extra_seats,
            'ai_used'     => (int) ($ai?->ai_messages_used_this_period ?? 0),
            // effectiveQuota() enforces the trial cap; falls back to the
            // plan's quota when the AiSettings row hasn't been seeded yet.
            'ai_quota'    => $ai ? $ai->effectiveQuota() : ($tenant->isOnTrial() ? (int) config('app.trial_ai_message_quota', 100) : $tenant->plan?->ai_message_quota),
            'ai_credits'  => (int) ($ai?->extra_message_credits ?? 0),
            'ai_resets_at'=> $ai?->quota_reset_at,
        ];

        // Only this tenant's own receipts, newest first.
        $invoices = TenantPayment::where('tenant_id', $tenant->id)
            ->with('plan')
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();

        return view('admin.billing.index', [
            'tenant'   => $tenant,
            'plans'    => $plans,
            'usage'    => $usage,
            'invoices' => $invoices,
            // Live add-on pricing, owned by the super admin.
            'pricing'  => AddonPricing::forView(),
        ]);
    }

    public function payments(Request $request)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $query = TenantPayment::with('tenant', 'plan')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->search, function ($q, $s) {
                $q->whereHas('tenant', fn($tq) => $tq->where('name', 'like', "%{$s}%"));
            })
            ->orderByDesc('created_at');

        if ($request->expectsJson()) {
            $payments = $query->paginate(25);

            $allStats = TenantPayment::selectRaw('status, count(*) as cnt')
                ->groupBy('status')->pluck('cnt', 'status');

            $result              = $payments->toArray();
            $result['data']      = $payments->map(fn($p) => [
                'id'               => $p->id,
                'tenant_name'      => $p->tenant?->name,
                'tenant_slug'      => $p->tenant?->slug,
                'tenant_initial'   => strtoupper(substr($p->tenant?->name ?? '?', 0, 1)),
                'plan_name'        => $p->plan?->name ?? '—',
                'amount'           => number_format((float) $p->amount, 2),
                'currency'         => $p->currency ?? 'USD',
                'status'           => $p->status,
                'is_completed'     => $p->isCompleted(),
                'paid_at_date'     => $p->paid_at?->format('d M Y'),
                'paid_at_time'     => $p->paid_at?->format('H:i'),
                'created_at_date'  => $p->created_at?->format('d M Y'),
                'payment_method'   => $p->payment_method ?? 'stripe',
                'stripe_session_id'=> $p->stripe_session_id ? Str::limit($p->stripe_session_id, 24) : null,
                'paypal_order_id'  => $p->paypal_order_id ? Str::limit($p->paypal_order_id, 24) : null,
            ])->toArray();
            $result['stats'] = [
                'total_revenue' => (float) TenantPayment::where('status', 'completed')->sum('amount'),
                'total'         => TenantPayment::count(),
                'completed'     => (int) ($allStats['completed'] ?? 0),
                'pending'       => (int) ($allStats['pending']   ?? 0),
                'failed'        => (int) ($allStats['failed']    ?? 0),
            ];

            return response()->json($result)
                ->withHeaders(['Cache-Control' => 'no-store, no-cache, must-revalidate']);
        }

        return view('admin.billing.payments');
    }

    public function showPayment(TenantPayment $payment)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $payment->load(['tenant.plan', 'plan']);

        $tenant    = $payment->tenant;
        $adminUser = $tenant?->users()->where('role', 'admin')->orderBy('id')->first();
        $totalPaid = $tenant
            ? TenantPayment::where('tenant_id', $tenant->id)->where('status', 'completed')->sum('amount')
            : 0;
        $paymentsCount = $tenant
            ? TenantPayment::where('tenant_id', $tenant->id)->count()
            : 0;

        return view('admin.billing.payment-show', compact('payment', 'tenant', 'adminUser', 'totalPaid', 'paymentsCount'));
    }
}
