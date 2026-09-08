<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingMessage;
use App\Models\AuditLog;
use App\Models\Team;
use App\Models\WebhookEvent;
use App\Models\WhatsAppInstance;
use App\Services\WhatsApp\Gateway\EvolutionApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InstanceWebController extends Controller
{
    private function ensureInstanceAccess(WhatsAppInstance $instance): void
    {
        $actor = auth()->user();

        if ($actor->isSuperAdmin()) {
            return;
        }

        if ((int) $instance->tenant_id !== (int) $actor->tenant_id) {
            abort(403, __('ui.controller_messages.unauthorized'));
        }
    }

    public function index(Request $request)
    {
        $isSuperAdmin = auth()->user()->role === 'super_admin';

        if ($request->expectsJson()) {
            $query = WhatsAppInstance::withCount([
                    'webhookEvents',
                    'webhookEvents as webhook_pending_count' => fn($q) => $q->whereNull('processed_at'),
                ])
                ->withMax('webhookEvents', 'created_at')
                ->orderBy('name');

            if ($isSuperAdmin) {
                $query->with('tenant:id,name');
            }

            $instances = $query->get()->makeHidden(['gateway_api_key', 'webhook_token']);

            if (!$this->seesGatewayInternals()) {
                $instances->each->makeHidden([
                    'gateway', 'gateway_url', 'gateway_instance_id',
                    'webhook_url', 'webhook_enabled', 'webhook_last_set',
                    'webhook_events_count', 'webhook_pending_count',
                    'webhook_events_max_created_at',
                ]);
            }

            $stats = [
                'connected'  => $instances->where('status', 'connected')->count(),
                'connecting' => $instances->whereIn('status', ['connecting', 'qr_pending'])->count(),
                'offline'    => $instances->whereIn('status', ['disconnected', 'error', 'banned'])->count(),
            ];

            return response()->json(['data' => $instances, 'stats' => $stats])
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        $tenantId    = auth()->user()->tenant_id;
        $canCreateInstance = app(\App\Services\Billing\TenantQuota::class)->canCreateInstance(auth()->user());

        // Fed to the inline "new instance" modal on this page.
        $teams               = Team::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $defaultInstanceName = $this->defaultInstanceName();

        $showGatewayInternals = $this->seesGatewayInternals();

        return view('admin.instances.index', compact(
            'canCreateInstance', 'isSuperAdmin', 'teams', 'defaultInstanceName', 'showGatewayInternals'
        ));
    }

    /**
     * Gateway plumbing — provider URL, API key, webhook endpoint/token and the
     * raw event log — is platform infrastructure, not tenant data. Only the
     * platform owner may see it.
     */
    private function seesGatewayInternals(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    private function ensureGatewayInternalsAccess(): void
    {
        abort_unless($this->seesGatewayInternals(), 403);
    }

    /**
     * A ready-to-accept instance name so the create modal is one click for most
     * tenants: the workspace name, numbered only if that one is already taken.
     */
    private function defaultInstanceName(): string
    {
        $base = trim((string) (auth()->user()->tenant->name ?? '')) ?: 'WhatsApp';
        $base = mb_substr($base, 0, 90);

        $taken = WhatsAppInstance::where('tenant_id', auth()->user()->tenant_id)
            ->pluck('name')
            ->map(fn ($n) => mb_strtolower(trim((string) $n)))
            ->all();

        if (!in_array(mb_strtolower($base), $taken, true)) {
            return $base;
        }

        for ($i = 2; $i < 100; $i++) {
            if (!in_array(mb_strtolower($base . ' ' . $i), $taken, true)) {
                return $base . ' ' . $i;
            }
        }

        return $base;
    }

    public function create()
    {
        $tenantId = auth()->user()->tenant_id;

        $quota = app(\App\Services\Billing\TenantQuota::class);
        if (!$quota->canCreateInstance(auth()->user())) {
            return redirect()->route(auth()->user()->routeNamePrefix() . '.instances.index')
                ->with('error', __('ui.controller_messages.instance_limit_reached'));
        }

        $teams  = Team::where('is_active', true)->orderBy('name')->get();
        $agents = \App\Models\User::where('tenant_id', $tenantId)
            ->whereIn('role', ['agent', 'supervisor'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);
        return view('admin.instances.create', compact('teams', 'agents'));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $quota = app(\App\Services\Billing\TenantQuota::class);
        if (!$quota->canCreateInstance(auth()->user())) {
            return redirect()->route(auth()->user()->routeNamePrefix() . '.instances.index')
                ->with('error', __('ui.controller_messages.instance_limit_reached'));
        }

        $data = $request->validate([
            'name'    => 'required|string|max:100',
            'team_id' => ['nullable', Rule::exists('teams', 'id')->where('tenant_id', $tenantId)],
        ]);

        $data['gateway']         = 'evolution_api';
        $data['gateway_url']     = config('services.whatsapp.default_url');
        $data['gateway_api_key'] = config('services.whatsapp.default_api_key');
        // Generate an HMAC secret per instance so the webhook receiver can
        // verify the gateway's posts (PROC-018). Passed to the gateway in
        // configureGatewayWebhook() below so it signs outgoing events.
        $data['webhook_secret']  = Str::random(64);

        $instance = WhatsAppInstance::create($data + ['tenant_id' => auth()->user()->tenant_id]);

        $this->configureGatewayWebhook($instance);

        AuditLog::record('instance.created', $instance);

        return redirect()->route(auth()->user()->routeNamePrefix() . '.instances.index')
            ->with('success', __('ui.controller_messages.instance_created', ['name' => $instance->name]));
    }

    public function show(WhatsAppInstance $instance)
    {
        $this->ensureInstanceAccess($instance);

        $instance->load(['tenant:id,name', 'team:id,name']);

        $showGatewayInternals = $this->seesGatewayInternals();

        $recentEvents = $showGatewayInternals
            ? $instance->webhookEvents()
                ->latest()
                ->limit(10)
                ->get(['id', 'event_type', 'processed_at', 'error', 'created_at'])
            : collect();

        return view('admin.instances.show', compact('instance', 'recentEvents', 'showGatewayInternals'));
    }

    public function webhookEvents(WhatsAppInstance $instance)
    {
        $this->ensureGatewayInternalsAccess();
        $this->ensureInstanceAccess($instance);

        $events = $instance->webhookEvents()
            ->latest()
            ->limit(30)
            ->get(['id', 'event_type', 'payload', 'processed_at', 'error', 'created_at']);

        return view('admin.instances.webhook-events', compact('instance', 'events'));
    }

    public function reprocessWebhookEvent(WhatsAppInstance $instance, int $eventId)
    {
        $this->ensureGatewayInternalsAccess();
        $this->ensureInstanceAccess($instance);

        $event = WebhookEvent::where('instance_id', $instance->id)->findOrFail($eventId);

        // Reset so the job re-runs fully
        $event->update(['processed_at' => null, 'error' => null]);

        try {
            ProcessIncomingMessage::dispatchSync($event);
            $event->refresh();
            $result = $event->error
                ? ['status' => 'error', 'message' => $event->error]
                : ['status' => 'ok', 'message' => 'Traité avec succès'];
        } catch (\Throwable $e) {
            $event->update(['error' => $e->getMessage()]);
            $result = ['status' => 'error', 'message' => $e->getMessage()];
        }

        return back()->with('reprocess_result', $result);
    }

    public function edit(WhatsAppInstance $instance)
    {
        $this->ensureInstanceAccess($instance);

        $teams = Team::where('is_active', true)->orderBy('name')->get();
        return view('admin.instances.edit', compact('instance', 'teams'));
    }

    public function update(Request $request, WhatsAppInstance $instance)
    {
        $this->ensureInstanceAccess($instance);

        $data = $request->validate([
            'name'    => 'required|string|max:100',
            'team_id' => ['nullable', Rule::exists('teams', 'id')->where('tenant_id', $instance->tenant_id)],
        ]);

        $instance->update($data);
        $this->configureGatewayWebhook($instance);
        AuditLog::record('instance.updated', $instance);

        return redirect()->route('admin.instances.index')
            ->with('success', __('ui.controller_messages.instance_updated'));
    }

    public function destroy(Request $request, WhatsAppInstance $instance)
    {
        $this->ensureInstanceAccess($instance);

        // The gateway name is deterministic (wa-<tenant>-<id>), so a row whose
        // gateway_instance_id is null may still have a stale entry on the
        // gateway from a previous connect attempt. Try both keys so the name
        // is freed and the next create with the same tenant/id can succeed.
        if ($instance->hasGatewayCredentials()) {
            $candidates = array_unique(array_filter([
                $instance->gateway_instance_id,
                'wa-' . $instance->tenant_id . '-' . $instance->id,
            ]));

            $gateway = new EvolutionApiClient($instance->effectiveGatewayUrl(), $instance->effectiveGatewayApiKey());
            foreach ($candidates as $name) {
                try {
                    $gateway->deleteInstance($name);
                } catch (\Throwable $e) {
                    // Best-effort — a missing / already-deleted instance is fine.
                    report($e);
                }
            }
        }

        AuditLog::record('instance.deleted', $instance, ['name' => $instance->name]);
        $instance->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Instance deleted.']);
        }

        return redirect()->route('admin.instances.index')
            ->with('success', __('ui.controller_messages.instance_deleted', ['name' => $instance->name]));
    }

    private function configureGatewayWebhook(WhatsAppInstance $instance): void
    {
        if (!$instance->gateway_instance_id || !$instance->hasGatewayCredentials()) {
            return;
        }

        try {
            $gateway = new EvolutionApiClient($instance->effectiveGatewayUrl(), $instance->effectiveGatewayApiKey());
            $gateway->setWebhook(
                $instance->gateway_instance_id,
                $this->webhookUrl($instance),
                [
                    'qrcodeUpdated' => true,
                    'messagesSet' => false,
                    'messagesUpsert' => true,
                    'messagesUpdated' => true,
                    'sendMessage' => true,
                    'contactsSet' => true,
                    'contactsUpsert' => true,
                    'contactsUpdated' => true,
                    'chatsSet' => false,
                    'chatsUpsert' => true,
                    'chatsUpdated' => true,
                    'chatsDeleted' => true,
                    'presenceUpdated' => true,
                    'groupsUpsert' => true,
                    'groupsUpdated' => true,
                    'groupsParticipantsUpdated' => true,
                    'connectionUpdated' => true,
                    'statusInstance' => true,
                    'refreshToken' => true,
                ],
                // The gateway is asked to include an HMAC signature in each
                // post. If the gateway version honors this field, inbound
                // events will carry X-Gateway-Signature and the receiver
                // rejects forgeries. If the gateway ignores it, the current
                // fail-open branch keeps things working — PROC-018 phase 1.
                $instance->webhook_secret
            );
        } catch (\Throwable) {
            // Webhook setup is best-effort; user can retry from the instance page.
        }
    }

    private function webhookUrl(WhatsAppInstance $instance): string
    {
        $base = rtrim((string) config('services.whatsapp.webhook_base_url', config('app.url')), '/');

        return "{$base}/api/webhooks/whatsapp/{$instance->webhook_token}";
    }
}
