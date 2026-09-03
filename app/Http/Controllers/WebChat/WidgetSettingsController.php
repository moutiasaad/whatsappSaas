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

        $languages   = $data['available_languages'] ?? ['ar', 'en'];
        $defaultLang = $data['default_lang'] ?? ($languages[0] ?? 'ar');
        if (!in_array($defaultLang, $languages, true)) {
            $defaultLang = $languages[0] ?? 'ar';
        }

        $widget->fill([
            'name'                => $data['name'],
            'enabled'             => (bool) ($data['enabled'] ?? false),
            'welcome_message'     => $data['welcome_message'],
            'suggestions'         => $data['suggestions'] ?? [],
            'pre_chat_ask_email'  => (bool) ($data['pre_chat_ask_email'] ?? false),
            'offline_message'     => $data['offline_message'] ?? null,
            'theme_color'         => $data['theme_color'],
            'position'            => $data['position'],
            'launcher_text'       => $data['launcher_text'] ?: null,
            'header_subtitle'     => $data['header_subtitle'] ?: null,
            'launcher_icon'       => $data['launcher_icon'],
            'bubble_style'        => $data['bubble_style'],
            'show_branding'       => (bool) ($data['show_branding'] ?? false),
            'default_lang'        => $defaultLang,
            'available_languages' => $languages,
            // Empty array → widget.js falls back to its hardcoded defaults.
            // Non-empty → tenant overrides win.
            'topics'              => empty($data['topics']) ? null : $data['topics'],
            'allowed_domains'     => $data['allowed_domains'] ?? [],
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
                    'header_subtitle'    => __('ui.webchat_settings.default_subtitle'),
                    'launcher_icon'      => 'chat',
                    'bubble_style'       => 'soft',
                    'show_branding'      => true,
                    'default_lang'       => 'ar',
                    'available_languages'=> ['ar', 'en'],
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

        // Languages — filter to the supported set and de-duplicate, keeping the
        // tenant-picked order. Fall back to [ar, en] if the tenant submitted
        // nothing so the widget never boots without at least one language.
        $allowedLangs = ['ar', 'en', 'fr'];
        $languages = collect($request->input('available_languages', []))
            ->filter(fn ($l) => in_array($l, $allowedLangs, true))
            ->unique()
            ->values()
            ->all();
        if (empty($languages)) $languages = ['ar', 'en'];
        $request->merge(['available_languages' => $languages]);

        // Topics — same shape the widget consumes:
        //   [{ tint, action, labels: { ar, en, fr } }, ...]
        // Drop rows where every language label is empty; that's what "user
        // left the row blank" looks like in the per-language editor.
        $allowedLangs = ['ar', 'en', 'fr'];
        $allowedTints = ['blue', 'orange', 'green', 'purple', 'red', 'gray', 'teal'];
        $topics = collect($request->input('topics', []))
            ->filter(fn ($t) => is_array($t))
            ->map(function ($t) use ($allowedLangs) {
                $labels = [];
                foreach ($allowedLangs as $lang) {
                    $v = trim((string) ($t['labels'][$lang] ?? ''));
                    if ($v !== '') $labels[$lang] = mb_substr($v, 0, 60);
                }
                return [
                    'tint'   => is_string($t['tint'] ?? null) ? $t['tint'] : 'blue',
                    'action' => (($t['action'] ?? '') === 'agent') ? 'agent' : 'message',
                    'labels' => $labels,
                ];
            })
            ->filter(fn ($t) => !empty($t['labels']))
            ->values()
            ->all();
        $request->merge(['topics' => $topics]);

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
            'header_subtitle'    => ['nullable', 'string', 'max:160'],
            'launcher_icon'      => ['required', 'in:chat,message,help,sparkle'],
            'bubble_style'       => ['required', 'in:soft,rounded,square'],
            'show_branding'      => ['sometimes', 'boolean'],
            'default_lang'         => ['required', 'in:ar,en,fr'],
            'available_languages'  => ['required', 'array', 'min:1', 'max:3'],
            'available_languages.*'=> ['in:ar,en,fr'],
            'allowed_domains'    => ['array', 'max:32'],
            'allowed_domains.*'  => ['string', 'regex:/^https?:\/\/[a-zA-Z0-9.\-]+(:[0-9]{1,5})?$/', 'max:255'],
            'topics'              => ['array', 'max:6'],
            'topics.*.tint'       => ['required', 'in:' . implode(',', $allowedTints)],
            'topics.*.action'     => ['required', 'in:message,agent'],
            'topics.*.labels'     => ['required', 'array', 'min:1'],
            'topics.*.labels.ar'  => ['nullable', 'string', 'max:60'],
            'topics.*.labels.en'  => ['nullable', 'string', 'max:60'],
            'topics.*.labels.fr'  => ['nullable', 'string', 'max:60'],
        ], [
            'theme_color.regex'       => __('ui.webchat_settings.err_theme_color'),
            'allowed_domains.*.regex' => __('ui.webchat_settings.err_domain_format'),
        ]);

        return $validator->validate();
    }
}
