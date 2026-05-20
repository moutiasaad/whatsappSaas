<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $tenant = $this->currentTenant();
        return view('admin.settings.index', compact('tenant'));
    }

    public function update(Request $request)
    {
        $tenant = $this->currentTenant();

        $data = $request->validate([
            'name' => 'required|string|max:150',
        ]);

        $tenant->update(['name' => $data['name']]);
        AuditLog::record('tenant.settings.updated', $tenant);

        return redirect()->route('admin.settings.index')->with('success', 'Settings saved.');
    }
}
