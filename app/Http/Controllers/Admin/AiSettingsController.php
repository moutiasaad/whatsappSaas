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
                'monthly_token_quota'  => 100000,
                'quota_reset_at'       => now()->startOfMonth()->addMonth(),
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
            'system_prompt'       => 'nullable|string|max:4000',
            'monthly_token_quota' => 'nullable|integer|min:0',
            'escalation_keywords' => 'nullable|string',
        ]);

        $tenant   = auth()->user()->tenant ?? abort(403, 'No tenant assigned to this account.');
        $settings = $tenant->aiSettings()->firstOrCreate(['tenant_id' => $tenant->id]);

        $settings->update([
            'mode'                => $data['mode'],
            'system_prompt'       => $data['system_prompt'] ?? null,
            'monthly_token_quota' => $data['monthly_token_quota'] ?? 0,
            'escalation_keywords' => json_decode($data['escalation_keywords'] ?? '[]', true),
        ]);

        AuditLog::record('ai_settings.updated', $settings);

        return redirect()->route('admin.ai-settings.index')
            ->with('success', 'AI settings saved.');
    }
}
