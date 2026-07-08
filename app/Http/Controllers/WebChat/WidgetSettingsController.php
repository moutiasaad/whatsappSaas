<?php

namespace App\Http\Controllers\WebChat;

use App\Http\Controllers\Controller;
use App\Models\WebChat\Widget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class WidgetSettingsController extends Controller
{
    public function show(Request $request): View
    {
        return view('admin.webchat.settings', [
            'widget'      => $this->firstOrProvisionForCurrentTenant($request),
            'embedBase'   => rtrim(config('app.url'), '/'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $widget = $this->firstOrProvisionForCurrentTenant($request);

        $data = $this->validateInput($request);

        $widget->fill([
            'name'               => $data['name'],
            'enabled'            => (bool) ($data['enabled'] ?? false),
            'welcome_message'    => $data['welcome_message'],
            'suggestions'        => $data['suggestions'] ?? [],
            'pre_chat_ask_email' => (bool) ($data['pre_chat_ask_email'] ?? false),
            'offline_message'    => $data['offline_message'] ?? null,
            'theme_color'        => $data['theme_color'],
            'position'           => $data['position'],
            'launcher_text'      => $data['launcher_text'] ?: null,
            'allowed_domains'    => $data['allowed_domains'] ?? [],
        ])->save();

        $prefix = $request->user()->routeNamePrefix();

        return redirect()
            ->route($prefix . '.webchat.settings.show')
            ->with('success', __('ui.webchat_settings.saved_flash'));
    }

    protected function firstOrProvisionForCurrentTenant(Request $request): Widget
    {
        $tenantId = $request->user()->tenant_id;

        // BelongsToTenant global scope + creating hook (auto-generates the
        // `wck_` public_key) handle both branches consistently.
        return Widget::withoutGlobalScope('tenant')
            ->firstOrCreate(
                ['tenant_id' => $tenantId],
                [
                    'name'               => 'Live Chat',
                    'enabled'            => true,
                    'welcome_message'    => __('ui.webchat_settings.default_welcome'),
                    'suggestions'        => [
                        __('ui.webchat_settings.default_chip_pricing'),
                        __('ui.webchat_settings.default_chip_support'),
                        __('ui.webchat_settings.default_chip_human'),
                    ],
                    'pre_chat_ask_email' => false,
                    'offline_message'    => __('ui.webchat_settings.default_offline'),
                    'theme_color'        => '#2563eb',
                    'position'           => 'right',
                    'launcher_text'      => null,
                    'allowed_domains'    => [],
                ]
            );
    }

    protected function validateInput(Request $request): array
    {
        // Chip list — normalize before validation so empty rows the user
        // left behind don't trip the required rule.
        $suggestions = collect($request->input('suggestions', []))
            ->filter(fn ($s) => is_string($s) && trim($s) !== '')
            ->map(fn ($s) => trim($s))
            ->values()
            ->all();
        $request->merge(['suggestions' => $suggestions]);

        // Domain list — same normalization.
        $domains = collect($request->input('allowed_domains', []))
            ->filter(fn ($d) => is_string($d) && trim($d) !== '')
            ->map(fn ($d) => trim($d))
            ->values()
            ->all();
        $request->merge(['allowed_domains' => $domains]);

        $validator = Validator::make($request->all(), [
            'name'               => ['required', 'string', 'max:120'],
            'enabled'            => ['sometimes', 'boolean'],
            'welcome_message'    => ['required', 'string', 'max:4000'],
            'suggestions'        => ['array', 'max:12'],
            'suggestions.*'      => ['string', 'max:60'],
            'pre_chat_ask_email' => ['sometimes', 'boolean'],
            'offline_message'    => ['nullable', 'string', 'max:4000'],
            'theme_color'        => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'position'           => ['required', 'in:left,right'],
            'launcher_text'      => ['nullable', 'string', 'max:120'],
            'allowed_domains'    => ['array', 'max:32'],
            'allowed_domains.*'  => ['string', 'regex:/^https?:\/\/[a-zA-Z0-9.\-]+(:[0-9]{1,5})?$/', 'max:255'],
        ], [
            'theme_color.regex'       => __('ui.webchat_settings.err_theme_color'),
            'allowed_domains.*.regex' => __('ui.webchat_settings.err_domain_format'),
        ]);

        return $validator->validate();
    }
}
