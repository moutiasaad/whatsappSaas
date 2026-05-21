<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        if (auth()->user()->isSuperAdmin()) {
            return redirect()->route('super_admin.platform.global-settings');
        }

        $tenant = $this->currentTenant();
        return view('admin.settings.index', compact('tenant'));
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

        return redirect()->route(auth()->user()->routeNamePrefix() . '.settings.index')->with('success', 'Settings saved.');
    }
}
