<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppInstance;
use App\Services\Otp\OtpService;
use Illuminate\Http\Request;

class OtpServiceController extends Controller
{
    public function __construct(private OtpService $service) {}

    public function show()
    {
        $tenant   = $this->currentTenant();
        $settings = $this->service->settings($tenant);
        $user     = auth()->user();

        $connectedInstance = WhatsAppInstance::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'connected')
            ->orderBy('id')
            ->first();

        return view('admin.otp-service.index', compact(
            'tenant', 'settings', 'user', 'connectedInstance'
        ));
    }

    public function update(Request $request)
    {
        $tenant = $this->currentTenant();

        $data = $request->validate([
            'enabled'     => ['sometimes', 'boolean'],
            'code_length' => ['required', 'integer', 'in:4,6,8'],
            'ttl_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'template'    => ['required', 'string', 'max:1000'],
        ]);

        // Checkboxes are absent from the payload when unchecked, and the
        // validator hands back the raw string values for the numeric fields.
        $data['enabled']     = $request->boolean('enabled');
        $data['code_length'] = (int) $data['code_length'];
        $data['ttl_minutes'] = (int) $data['ttl_minutes'];

        if (!str_contains($data['template'], '{code}')) {
            return back()
                ->withInput()
                ->withErrors(['template' => __('otp.template_missing_code')]);
        }

        $this->service->saveSettings($tenant, $data);

        return redirect()
            ->route(auth()->user()->routeNamePrefix() . '.otp-service.show')
            ->with('success', __('otp.saved'));
    }
}
