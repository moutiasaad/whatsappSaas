<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;

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
}
