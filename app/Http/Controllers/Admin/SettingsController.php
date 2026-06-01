<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\TenantPayment;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        if (auth()->user()->isSuperAdmin()) {
            return redirect()->route('super_admin.platform.global-settings');
        }

        $tenant = $this->currentTenant()->load('plan');

        $latestPayment = TenantPayment::where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->latest('paid_at')
            ->first();

        $upgradePlans = Plan::where('is_active', true)
            ->where('id', '!=', $tenant->plan_id)
            ->orderBy('price_monthly')
            ->get();

        return view('admin.settings.index', compact('tenant', 'latestPayment', 'upgradePlans'));
    }

    public function update(Request $request)
    {
        if (auth()->user()->isSuperAdmin()) {
            return redirect()->route('super_admin.platform.global-settings');
        }

        $tenant = $this->currentTenant();

        $data = $request->validate([
            'name' => 'required|string|max:150',
        ]);

        $tenant->update(['name' => $data['name']]);
        AuditLog::record('tenant.settings.updated', $tenant);

        return redirect()->route(auth()->user()->routeNamePrefix() . '.settings.index')->with('success', __('ui.controller_messages.settings_saved'));
    }
}
