<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppInstance;
use App\Services\Notify\WhatsAppNotifyService;
use Illuminate\Http\Request;

class NotifyServiceController extends Controller
{
    public function __construct(private WhatsAppNotifyService $service) {}

    public function show()
    {
        $tenant   = $this->currentTenant();
        $settings = $this->service->settings($tenant);
        $user     = auth()->user();
        $recent   = $this->service->recentMessages($tenant);

        $connectedInstance = WhatsAppInstance::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'connected')
            ->orderBy('id')
            ->first();

        return view('admin.notify-service.index', compact(
            'tenant', 'settings', 'user', 'connectedInstance', 'recent'
        ));
    }

    public function update(Request $request)
    {
        $tenant = $this->currentTenant();

        $data = $request->validate([
            'enabled'     => ['sometimes', 'boolean'],
            'admin_phone' => ['required', 'string', 'max:32'],
            'prefix'      => ['nullable', 'string', 'max:64'],
        ]);

        $data['enabled']     = $request->boolean('enabled');
        $data['admin_phone'] = preg_replace('/[^0-9+]/', '', $data['admin_phone']);
        $data['prefix']      = trim((string) ($data['prefix'] ?? ''));

        if (!preg_match('/^\+?\d{7,15}$/', $data['admin_phone'])) {
            return back()
                ->withInput()
                ->withErrors(['admin_phone' => __('notify.admin_phone_invalid')]);
        }

        $this->service->saveSettings($tenant, $data);

        return redirect()
            ->route(auth()->user()->routeNamePrefix() . '.notify-service.show')
            ->with('success', __('notify.saved'));
    }

    public function test(Request $request)
    {
        $tenant = $this->currentTenant();

        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $result = $this->service->send($tenant, $data['message']);

        if ($result['ok']) {
            return back()->with('success', __('notify.test_sent'));
        }

        return back()->withErrors(['message' => $result['message'] ?? __('notify.test_failed')]);
    }
}
