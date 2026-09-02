<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebChat\Conversation as WebChatConversation;
use App\Models\WhatsAppInstance;
use Illuminate\Http\Request;

class ArchiveController extends Controller
{
    public function index(Request $request)
    {
        $actor        = $request->user();
        $isSuperAdmin = $actor?->isSuperAdmin() ?? false;
        $tenantId     = $isSuperAdmin
            ? ($request->filled('tenant_id') ? (int) $request->integer('tenant_id') : null)
            : (int) $actor->tenant_id;

        $tab = $request->string('tab')->value() ?: 'whatsapp';
        if (!in_array($tab, ['whatsapp', 'webchat'], true)) {
            $tab = 'whatsapp';
        }

        $tenants = $isSuperAdmin
            ? Tenant::query()->orderBy('name')->get(['id', 'name'])
            : collect();

        $instances = WhatsAppInstance::query()
            ->select(['id', 'name', 'tenant_id'])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->get();

        $agents = User::query()
            ->select(['id', 'name', 'tenant_id'])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->get();

        $stats = [
            'whatsapp_total' => Conversation::withoutGlobalScope('tenant')
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->where('state', 'closed')
                ->count(),
            'webchat_total'  => WebChatConversation::withoutGlobalScope('tenant')
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->where('status', WebChatConversation::STATUS_CLOSED)
                ->count(),
        ];

        $items = $tab === 'whatsapp'
            ? $this->queryWhatsApp($request, $tenantId)
            : $this->queryWebChat($request, $tenantId);

        return view('admin.archive.index', [
            'tab'       => $tab,
            'items'     => $items,
            'tenants'   => $tenants,
            'instances' => $instances,
            'agents'    => $agents,
            'stats'     => $stats,
            'filters'   => [
                'search'    => (string) $request->string('search'),
                'tenant_id' => $isSuperAdmin ? (string) $request->string('tenant_id') : (string) $tenantId,
                'closed_by' => (string) $request->string('closed_by'),
                'date_from' => (string) $request->string('date_from'),
                'date_to'   => (string) $request->string('date_to'),
            ],
        ]);
    }

    private function queryWhatsApp(Request $request, ?int $tenantId)
    {
        $q = Conversation::withoutGlobalScope('tenant')
            ->with([
                'customer:id,phone_e164,display_name',
                'instance:id,name',
                'tenant:id,name',
                'ownerAgent:id,name',
            ])
            ->where('state', 'closed')
            ->when($tenantId, fn ($qq) => $qq->where('tenant_id', $tenantId))
            ->when($request->filled('closed_by'), fn ($qq) => $qq->where('owner_agent_id', (int) $request->integer('closed_by')))
            ->when($request->filled('date_from'), fn ($qq) => $qq->whereDate('closed_at', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'),   fn ($qq) => $qq->whereDate('closed_at', '<=', $request->string('date_to')))
            ->when($request->filled('search'), function ($qq) use ($request) {
                $search = trim((string) $request->string('search'));
                $qq->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('last_message_preview', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($c) use ($search) {
                            $c->where('display_name', 'like', "%{$search}%")
                              ->orWhere('phone_e164', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('closed_at');

        return $q->paginate(20)->withQueryString();
    }

    private function queryWebChat(Request $request, ?int $tenantId)
    {
        $q = WebChatConversation::withoutGlobalScope('tenant')
            ->with(['visitor:id,name,email', 'widget:id,name', 'closer:id,name', 'tenant:id,name'])
            ->where('status', WebChatConversation::STATUS_CLOSED)
            ->when($tenantId, fn ($qq) => $qq->where('tenant_id', $tenantId))
            ->when($request->filled('closed_by'), fn ($qq) => $qq->where('closed_by', (int) $request->integer('closed_by')))
            ->when($request->filled('date_from'), fn ($qq) => $qq->whereDate('closed_at', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'),   fn ($qq) => $qq->whereDate('closed_at', '<=', $request->string('date_to')))
            ->when($request->filled('search'), function ($qq) use ($request) {
                $search = trim((string) $request->string('search'));
                $qq->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('visitor_name', 'like', "%{$search}%")
                        ->orWhere('visitor_email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('closed_at');

        return $q->paginate(20)->withQueryString();
    }
}
