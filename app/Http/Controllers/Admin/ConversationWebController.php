<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\ConversationController as ApiConversationController;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppInstance;
use Illuminate\Http\Request;

class ConversationWebController extends Controller
{
    public function index(Request $request)
    {
        if ($request->expectsJson()) {
            return app(ApiConversationController::class)
                ->index($request)
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        $actor = auth()->user();
        $isSuperAdmin = $actor->isSuperAdmin();

        $tenants = $isSuperAdmin
            ? Tenant::query()->orderBy('name')->get(['id', 'name', 'slug'])
            : collect();

        $instances = WhatsAppInstance::query()
            ->select(['id', 'name', 'tenant_id'])
            ->when(!$isSuperAdmin, fn ($q) => $q->where('tenant_id', $actor->tenant_id))
            ->orderBy('name')
            ->get();

        $teams = Team::query()
            ->select(['id', 'name', 'tenant_id'])
            ->when(!$isSuperAdmin, fn ($q) => $q->where('tenant_id', $actor->tenant_id))
            ->orderBy('name')
            ->get();

        $agents = User::query()
            ->select(['id', 'name', 'role', 'tenant_id'])
            ->whereIn('role', ['admin', 'supervisor', 'agent'])
            ->where('is_active', true)
            ->when(!$isSuperAdmin, fn ($q) => $q->where('tenant_id', $actor->tenant_id))
            ->orderBy('name')
            ->get();

        return view('admin.conversations.index', compact('tenants', 'instances', 'teams', 'agents', 'isSuperAdmin'));
    }

    public function show(Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        $conversation->load(['customer', 'instance', 'ownerAgent', 'team']);

        $events = $conversation->events()->with('actor')->orderBy('created_at')->get();

        $teamAgents = $conversation->team
            ? $conversation->team->users()->where('role', 'agent')->where('is_active', true)->get()
            : User::where('role', 'agent')
                ->where('is_active', true)
                ->where('tenant_id', $conversation->tenant_id)
                ->get();

        $customerConversationCount = Conversation::where('customer_id', $conversation->customer_id)->count();

        $aiSettings = $conversation->tenant?->aiSettings;
        $aiMode = $aiSettings?->mode ?? 'off';

        return view('admin.conversations.show', compact(
            'conversation', 'events', 'teamAgents', 'customerConversationCount', 'aiMode'
        ));
    }
}
