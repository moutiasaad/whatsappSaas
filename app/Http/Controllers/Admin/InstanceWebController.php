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

class InstanceWebController extends Controller
{
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

            $stats = [
                'connected'  => $instances->where('status', 'connected')->count(),
                'connecting' => $instances->whereIn('status', ['connecting', 'qr_pending'])->count(),
                'offline'    => $instances->whereIn('status', ['disconnected', 'error', 'banned'])->count(),
            ];

            return response()->json(['data' => $instances, 'stats' => $stats])
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        $tenantId    = auth()->user()->tenant_id;
        $hasInstance = $tenantId && WhatsAppInstance::where('tenant_id', $tenantId)->exists();

        return view('admin.instances.index', compact('hasInstance', 'isSuperAdmin'));
    }

    public function create()
    {
        $tenantId = auth()->user()->tenant_id;

        if ($tenantId && WhatsAppInstance::where('tenant_id', $tenantId)->exists()) {
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

        if ($tenantId && WhatsAppInstance::where('tenant_id', $tenantId)->exists()) {
            return redirect()->route(auth()->user()->routeNamePrefix() . '.instances.index')
                ->with('error', __('ui.controller_messages.instance_limit_reached'));
        }

        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'gateway'        => 'required|in:evolution_api,waha,cloud_api',
            'gateway_url'    => 'nullable|url',
            'gateway_api_key'=> 'nullable|string',
            'team_id'        => 'nullable|exists:teams,id',
        ]);

        $data['gateway_url'] = $data['gateway_url'] ?: config('services.whatsapp.default_url');
        $data['gateway_api_key'] = $data['gateway_api_key'] ?: config('services.whatsapp.default_api_key');

        $instance = WhatsAppInstance::create($data + ['tenant_id' => auth()->user()->tenant_id]);

        $this->configureGatewayWebhook($instance);

        AuditLog::record('instance.created', $instance);

        return redirect()->route('admin.instances.index')
            ->with('success', __('ui.controller_messages.instance_created', ['name' => $instance->name]));
    }

    public function webhookEvents(WhatsAppInstance $instance)
    {
        $events = $instance->webhookEvents()
            ->latest()
            ->limit(30)
            ->get(['id', 'event_type', 'payload', 'processed_at', 'error', 'created_at']);

        return view('admin.instances.webhook-events', compact('instance', 'events'));
    }

    public function reprocessWebhookEvent(WhatsAppInstance $instance, int $eventId)
    {
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
        $teams = Team::where('is_active', true)->orderBy('name')->get();
        return view('admin.instances.edit', compact('instance', 'teams'));
    }

    public function update(Request $request, WhatsAppInstance $instance)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'gateway_url'    => 'nullable|url',
            'gateway_api_key'=> 'nullable|string',
            'team_id'        => 'nullable|exists:teams,id',
        ]);

        if (empty($data['gateway_url'])) {
            $data['gateway_url'] = $instance->gateway_url ?: config('services.whatsapp.default_url');
        }

        if (empty($data['gateway_api_key'])) {
            $data['gateway_api_key'] = $instance->gateway_api_key ?: config('services.whatsapp.default_api_key');
        }

        $instance->update($data);
        $this->configureGatewayWebhook($instance);
        AuditLog::record('instance.updated', $instance);

        return redirect()->route('admin.instances.index')
            ->with('success', __('ui.controller_messages.instance_updated'));
    }

    public function destroy(Request $request, WhatsAppInstance $instance)
    {
        try {
            if ($instance->gateway_instance_id && $instance->hasGatewayCredentials()) {
                $gateway = new EvolutionApiClient($instance->effectiveGatewayUrl(), $instance->effectiveGatewayApiKey());
                $gateway->logout($instance->gateway_instance_id);
            }
        } catch (\Throwable $e) {
            report($e);
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
                ]
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
