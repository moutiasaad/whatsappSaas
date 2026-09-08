<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppInstance;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $actor = $request->user();
        $isSuperAdmin = $actor?->isSuperAdmin() ?? false;

        $tenants = $isSuperAdmin
            ? Tenant::query()->orderBy('name')->get(['id', 'name'])
            : collect();

        $instances = WhatsAppInstance::query()
            ->select(['id', 'name', 'tenant_id'])
            ->when(!$isSuperAdmin, fn ($q) => $q->where('tenant_id', $actor?->tenant_id))
            ->orderBy('name')
            ->get();

        if ($request->expectsJson()) {
            $baseQuery = Customer::query()
                ->with('tenant:id,name')
                ->withCount('conversations')
                ->withMax('conversations', 'last_message_at')
                ->when($request->filled('search'), function ($query) use ($request) {
                    $search = trim((string) $request->string('search'));
                    $query->where(function ($inner) use ($search) {
                        $inner->where('phone_e164', 'like', "%{$search}%")
                            ->orWhere('display_name', 'like', "%{$search}%");
                    });
                })
                ->when($isSuperAdmin && $request->filled('tenant_id'), fn ($q) => $q->where('tenant_id', (int) $request->integer('tenant_id')))
                ->when($request->filled('instance_id'), function ($query) use ($request) {
                    $instanceId = (int) $request->integer('instance_id');
                    $query->whereHas('conversations', fn ($conv) => $conv->where('instance_id', $instanceId));
                })
                ->when($request->filled('has_conversations'), function ($query) use ($request) {
                    if ($request->string('has_conversations')->value() === '1') {
                        $query->has('conversations');
                    } else {
                        $query->doesntHave('conversations');
                    }
                })
                ->when($request->filled('date_from'), fn ($q) => $q->whereDate('updated_at', '>=', $request->string('date_from')->value()))
                ->when($request->filled('date_to'), fn ($q) => $q->whereDate('updated_at', '<=', $request->string('date_to')->value()));

            $stats = [
                'total'              => (clone $baseQuery)->count(),
                'with_conversations' => (clone $baseQuery)->has('conversations')->count(),
                'active_7d'          => (clone $baseQuery)->where('updated_at', '>=', now()->subDays(7))->count(),
                'dormant_30d'        => (clone $baseQuery)->where('updated_at', '<', now()->subDays(30))->count(),
            ];

            $listQuery = clone $baseQuery;
            match ($request->string('sort')->value()) {
                'activity_asc'       => $listQuery->orderBy('updated_at'),
                'name_asc'           => $listQuery->orderBy('display_name')->orderBy('phone_e164'),
                'name_desc'          => $listQuery->orderByDesc('display_name')->orderByDesc('phone_e164'),
                'conversations_desc' => $listQuery->orderByDesc('conversations_count')->orderByDesc('updated_at'),
                'conversations_asc'  => $listQuery->orderBy('conversations_count')->orderByDesc('updated_at'),
                default              => $listQuery->orderByDesc('updated_at'),
            };

            $paginated = $listQuery->paginate((int) ($request->integer('per_page') ?: 30));

            return response()->json(
                array_merge($paginated->toArray(), ['stats' => $stats])
            )->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        return view('admin.customers.index', compact('tenants', 'instances', 'isSuperAdmin'));
    }

    public function show(Request $request, Customer $customer)
    {
        $actor = $request->user();
        $isSuperAdmin = $actor?->isSuperAdmin() ?? false;

        abort_unless(
            $isSuperAdmin || (int) $customer->tenant_id === (int) ($actor?->tenant_id ?? 0),
            403
        );

        $conversationBaseQuery = Conversation::query()
            ->with([
                'ownerAgent:id,name',
                'instance:id,name',
            ])
            ->where('customer_id', $customer->id)
            ->when($request->filled('state'), fn ($q) => $q->where('state', $request->string('state')->value()))
            ->when($request->filled('instance_id'), fn ($q) => $q->where('instance_id', (int) $request->integer('instance_id')))
            ->when($request->filled('agent_id'), fn ($q) => $q->where('owner_agent_id', (int) $request->integer('agent_id')))
            ->when($request->filled('ai_suspended'), fn ($q) => $q->where('ai_suspended', $request->string('ai_suspended')->value() === '1'))
            ->when($request->filled('search'), fn ($q) => $q->where('last_message_preview', 'like', '%' . trim((string) $request->string('search')) . '%'));

        $conversations = (clone $conversationBaseQuery);

        match ($request->string('sort')->value()) {
            'created_desc' => $conversations->orderByDesc('created_at'),
            'created_asc' => $conversations->orderBy('created_at'),
            'state' => $conversations->orderBy('state')->orderByDesc('last_message_at'),
            default => $conversations->orderByDesc('last_message_at')->orderByDesc('created_at'),
        };

        $conversations = $conversations
            ->paginate(15)
            ->withQueryString();

        $conversationStats = [
            'total' => (clone $conversationBaseQuery)->count(),
            'open' => (clone $conversationBaseQuery)->whereIn('state', ['pool', 'claimed'])->count(),
            'closed' => (clone $conversationBaseQuery)->where('state', 'closed')->count(),
            'unread' => (clone $conversationBaseQuery)->where('unread_count', '>', 0)->count(),
            'ai_suspended' => (clone $conversationBaseQuery)->where('ai_suspended', true)->count(),
        ];

        $instanceOptions = WhatsAppInstance::query()
            ->select(['id', 'name'])
            ->where('tenant_id', $customer->tenant_id)
            ->orderBy('name')
            ->get();

        $agentOptions = User::query()
            ->select(['id', 'name', 'role'])
            ->where('tenant_id', $customer->tenant_id)
            ->whereIn('role', ['admin', 'supervisor', 'agent'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.customers.show', compact(
            'customer',
            'conversations',
            'conversationStats',
            'instanceOptions',
            'agentOptions'
        ));
    }
}
