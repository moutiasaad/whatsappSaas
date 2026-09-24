<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\KnowledgeEntry;
use App\Models\WhatsAppInstance;
use Illuminate\Http\Request;

/**
 * First-run setup.
 *
 * Three things have to be true before a workspace answers anything the way it
 * was sold: a WhatsApp number is linked, the AI has something to answer from,
 * and the AI is switched on. Each is reachable from its own page in the
 * sidebar; this puts them in the order they depend on each other, so a new
 * admin does not have to work out that the AI stays silent until a knowledge
 * base exists.
 *
 * Nothing here is a second source of truth: every step reads its state from
 * the rows the real pages write, so finishing a step elsewhere shows as done
 * here, and the only thing stored for the page itself is whether it was
 * skipped.
 */
class OnboardingController extends Controller
{
    /** Where the skip flags live inside `tenants.settings`. */
    private const KEY = 'onboarding';

    public function show(Request $request)
    {
        $tenant = $this->tenant($request);

        $instance = WhatsAppInstance::where('tenant_id', $tenant->id)->orderBy('id')->first();
        $ai       = $tenant->aiSettings;
        $flags    = (array) data_get($tenant->settings, self::KEY, []);

        return view('admin.onboarding.index', [
            'tenant'      => $tenant,
            'instance'    => $instance,
            'kbCount'     => KnowledgeEntry::where('tenant_id', $tenant->id)->count(),
            'aiMode'      => $ai?->mode ?? 'off',
            'kbSkipped'   => (bool) ($flags['kb_skipped'] ?? false),
            'canUseAi'    => (bool) ($tenant->planAllows('ai_agent') ?? false),
            'teams'       => \App\Models\Team::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'defaultInstanceName' => mb_substr(trim((string) $tenant->name) ?: 'WhatsApp', 0, 90),
        ]);
    }

    /**
     * Leave setup. The tenant keeps whatever they finished; the dashboard just
     * stops sending them back here.
     */
    public function skip(Request $request)
    {
        $tenant = $this->tenant($request);
        $this->flag($tenant, ['skipped_at' => now()->toIso8601String()]);

        return redirect()->route($request->user()->routeNamePrefix() . '.dashboard')
            ->with('success', __('ui.get_started.skipped_toast'));
    }

    /** Skip step 2 only — the AI stays off, agents still get every message. */
    public function skipKnowledge(Request $request)
    {
        $this->flag($this->tenant($request), ['kb_skipped' => true]);

        return response()->json(['ok' => true]);
    }

    /**
     * Turn the answers on this page into real knowledge entries.
     *
     * Two shapes, one endpoint: the five guided questions, or a pasted FAQ in
     * `Q:` / `A:` pairs. Both end up as the same rows the Knowledge Base page
     * shows, so nothing here is a private format only this page understands.
     */
    public function knowledge(Request $request)
    {
        $tenant = $this->tenant($request);

        $data = $request->validate([
            'mode'     => 'required|in:quick,paste',
            'business' => 'nullable|string|max:2000',
            'delivery' => 'nullable|string|max:2000',
            'returns'  => 'nullable|string|max:2000',
            'hours'    => 'nullable|string|max:2000',
            'payments' => 'nullable|array|max:12',
            'payments.*' => 'string|max:60',
            'faq'      => 'nullable|string|max:20000',
        ]);

        $entries = $data['mode'] === 'quick'
            ? $this->fromAnswers($data)
            : $this->fromFaq((string) ($data['faq'] ?? ''));

        if (!$entries) {
            return response()->json(['message' => __('ui.get_started.kb_empty')], 422);
        }

        $sort = (int) KnowledgeEntry::where('tenant_id', $tenant->id)->max('sort_order');

        foreach ($entries as $entry) {
            KnowledgeEntry::create($entry + [
                'tenant_id'  => $tenant->id,
                'is_active'  => true,
                'sort_order' => ++$sort,
                'metadata'   => ['source' => 'onboarding'],
            ]);
        }

        // Answering the questions is itself an un-skip: the step is done.
        $this->flag($tenant, ['kb_skipped' => false]);

        AuditLog::record('knowledge.onboarding_seeded', $tenant, ['entries' => count($entries)]);

        return response()->json([
            'ok'    => true,
            'count' => KnowledgeEntry::where('tenant_id', $tenant->id)->count(),
        ]);
    }

    /** The five guided answers, each as the entry type it really is. */
    private function fromAnswers(array $d): array
    {
        $out = [];

        $add = function (string $type, string $title, ?string $body) use (&$out) {
            $body = trim((string) $body);
            if ($body !== '') {
                $out[] = ['type' => $type, 'title' => $title, 'body' => $body];
            }
        };

        $add('company_profile', __('ui.get_started.q_business_title'), $d['business'] ?? null);
        $add('faq',             __('ui.get_started.q_delivery_title'), $d['delivery'] ?? null);
        $add('policy',          __('ui.get_started.q_returns_title'),  $d['returns'] ?? null);
        $add('faq',             __('ui.get_started.q_hours_title'),    $d['hours'] ?? null);

        $payments = array_filter(array_map('trim', (array) ($d['payments'] ?? [])));
        if ($payments) {
            $add('faq', __('ui.get_started.q_payments_title'), implode(', ', $payments));
        }

        return $out;
    }

    /**
     * Split a pasted FAQ on its `Q:` lines.
     *
     * Deliberately forgiving about everything except the marker: people paste
     * out of a doc, so blank lines, stray spacing and a trailing answer with no
     * question after it all have to survive the trip.
     */
    private function fromFaq(string $faq): array
    {
        $out = [];

        foreach (preg_split('/^\s*Q\s*:/mi', $faq) as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            $parts    = preg_split('/^\s*A\s*:/mi', $block, 2);
            $question = trim($parts[0]);
            $answer   = trim($parts[1] ?? '');

            if ($question === '' || $answer === '') {
                continue;
            }

            $out[] = [
                'type'  => 'faq',
                'title' => mb_substr($question, 0, 180),
                'body'  => $answer,
            ];

            if (count($out) >= 100) {
                break;
            }
        }

        return $out;
    }

    /**
     * Switch the AI on in the chosen mode.
     *
     * Refuses while the knowledge base is empty rather than letting a tenant
     * turn on an agent with nothing to say — the same reason the step is
     * locked in the UI, enforced where it counts.
     */
    public function ai(Request $request)
    {
        $tenant = $this->tenant($request);

        $data = $request->validate([
            'mode' => 'required|in:off,suggestion,hybrid,autonomous',
        ]);

        abort_unless($tenant->planAllows('ai_agent'), 403);

        if ($data['mode'] !== 'off' && KnowledgeEntry::where('tenant_id', $tenant->id)->count() === 0) {
            return response()->json(['message' => __('ui.get_started.ai_needs_kb')], 422);
        }

        $settings = $tenant->aiSettings()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'whatsapp_enabled'      => true,
                'webchat_enabled'       => true,
                'monthly_message_quota' => $tenant->plan?->ai_message_quota,
                'quota_reset_at'        => now()->startOfMonth()->addMonthNoOverflow(),
                'escalation_keywords'   => ['human', 'agent', 'supervisor'],
            ],
        );

        $settings->update(['mode' => $data['mode']]);

        AuditLog::record('ai_settings.updated', $settings, ['mode' => $data['mode'], 'via' => 'onboarding']);

        return response()->json(['ok' => true, 'mode' => $data['mode']]);
    }

    private function tenant(Request $request)
    {
        $tenant = $request->user()?->tenant;
        abort_unless($tenant, 403, __('ui.controller_messages.no_tenant_assigned'));

        return $tenant;
    }

    private function flag($tenant, array $patch): void
    {
        $settings = (array) ($tenant->settings ?? []);
        $settings[self::KEY] = array_merge((array) ($settings[self::KEY] ?? []), $patch);

        $tenant->update(['settings' => $settings]);
    }
}
