<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\KnowledgeEntry;
use Illuminate\Http\Request;

class AiSettingsController extends Controller
{
    public function index()
    {
        $tenant = $this->currentTenant();
        $settings = $tenant->aiSettings()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'mode'                 => 'off',
                'whatsapp_enabled'     => true,
                'webchat_enabled'      => true,
                'monthly_token_quota'  => 100000,
                // addMonthNoOverflow so a settings row seeded on the 31st
                // doesn't overshoot to the next-next month (CALC-011 note).
                'quota_reset_at'       => now()->startOfMonth()->addMonthNoOverflow(),
                'escalation_keywords'  => ['human', 'agent', 'supervisor'],
            ]
        );

        $knowledgeCount = KnowledgeEntry::where('is_active', true)->count();

        return view('admin.ai-settings.index', compact('settings', 'knowledgeCount'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'mode'                => 'required|in:off,suggestion,autonomous,hybrid',
            'whatsapp_enabled'    => 'nullable|boolean',
            'webchat_enabled'     => 'nullable|boolean',
            'reply_when_claimed'  => 'nullable|boolean',
            'reply_language'      => 'nullable|string|max:10',
            'suggestion_count'    => 'nullable|integer|min:1|max:5',
            'system_prompt'       => 'nullable|string|max:4000',
            'monthly_token_quota' => 'nullable|integer|min:0',
            'escalation_keywords' => 'nullable|string',
        ]);

        $tenant = auth()->user()->tenant ?? abort(403, __('ui.controller_messages.no_tenant_assigned'));
        // CALC-011: if this is the row's first materialisation (index() didn't
        // hit first), still seed quota_reset_at so the rollover has a
        // reference to advance from. Existing rows are unchanged.
        $settings = $tenant->aiSettings()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            ['quota_reset_at' => now()->startOfMonth()->addMonthNoOverflow()],
        );

        $settings->update([
            'mode'                => $data['mode'],
            'whatsapp_enabled'    => (bool) ($data['whatsapp_enabled'] ?? false),
            'webchat_enabled'     => (bool) ($data['webchat_enabled'] ?? false),
            'reply_when_claimed'  => (bool) ($data['reply_when_claimed'] ?? false),
            'reply_language'      => $data['reply_language'] ?? 'auto',
            'suggestion_count'    => $data['suggestion_count'] ?? 3,
            'system_prompt'       => $data['system_prompt'] ?? null,
            // Absent field used to fall through to 0 here, silently turning every
            // save into "unlimited quota". Keep the stored value when not posted.
            'monthly_token_quota' => $data['monthly_token_quota'] ?? $settings->monthly_token_quota ?? 0,
            'escalation_keywords' => json_decode($data['escalation_keywords'] ?? '[]', true),
        ]);

        AuditLog::record('ai_settings.updated', $settings);

        return redirect()->route(auth()->user()->routeNamePrefix() . '.ai-settings.index')
            ->with('success', __('ui.controller_messages.ai_settings_saved'));
    }
}
