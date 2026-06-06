<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantPayment;
use Illuminate\Http\Request;

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
        $plans  = Plan::where('is_active', true)->orderBy('price_monthly')->get();

        return view('admin.billing.index', compact('tenant', 'plans'));
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

        $payments = $query->paginate(25)->withQueryString();

        $totals = TenantPayment::selectRaw('status, count(*) as count, sum(amount) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $totalRevenue = TenantPayment::where('status', 'completed')->sum('amount');

        return view('admin.billing.payments', compact('payments', 'totals', 'totalRevenue'));
    }
}
