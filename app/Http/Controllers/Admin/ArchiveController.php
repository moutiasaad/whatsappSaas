<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebChat\Conversation as WebChatConversation;
use App\Models\WebChat\Message as WebChatMessage;
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

    public function showWhatsApp(Request $request, Conversation $conversation)
    {
        $this->guardTenant($request->user(), $conversation->tenant_id);

        $conversation->load(['customer', 'instance', 'tenant', 'ownerAgent', 'team']);

        $messages = Message::withoutGlobalScopes()
            ->where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->with('author:id,name')
            ->get();

        $events = ConversationEvent::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->with('actor:id,name')
            ->get();

        $related = Conversation::withoutGlobalScope('tenant')
            ->where('customer_id', $conversation->customer_id)
            ->where('id', '!=', $conversation->id)
            ->orderByDesc('closed_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'title', 'state', 'closed_at', 'created_at']);

        $stats = [
            'messages_total'    => $messages->count(),
            'messages_customer' => $messages->where('direction', 'in')->count(),
            'messages_agent'    => $messages->where('direction', 'out')->where('author_type', '!=', 'ai')->count(),
            'messages_ai'       => $messages->where('author_type', 'ai')->count(),
        ];

        return view('admin.archive.show', [
            'channel'      => 'whatsapp',
            'conversation' => $conversation,
            'messages'     => $messages,
            'events'       => $events,
            'related'      => $related,
            'stats'        => $stats,
            'meta'         => $this->normalizeWhatsAppMeta($conversation),
        ]);
    }

    public function showWebChat(Request $request, string $uuid)
    {
        $conversation = WebChatConversation::withoutGlobalScope('tenant')
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->guardTenant($request->user(), $conversation->tenant_id);

        $conversation->load(['visitor', 'widget', 'tenant', 'claimer', 'closer']);

        $messages = WebChatMessage::where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->with('sender:id,name')
            ->get();

        $related = WebChatConversation::withoutGlobalScope('tenant')
            ->where('visitor_id', $conversation->visitor_id)
            ->where('id', '!=', $conversation->id)
            ->orderByDesc('closed_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'uuid', 'title', 'status', 'closed_at', 'created_at']);

        $stats = [
            'messages_total'   => $messages->count(),
            'messages_visitor' => $messages->where('sender_type', WebChatMessage::SENDER_VISITOR)->count(),
            'messages_agent'   => $messages->where('sender_type', WebChatMessage::SENDER_AGENT)->count(),
            'messages_ai'      => $messages->where('sender_type', WebChatMessage::SENDER_BOT)->count(),
        ];

        return view('admin.archive.show', [
            'channel'      => 'webchat',
            'conversation' => $conversation,
            'messages'     => $messages,
            'events'       => collect(),
            'related'      => $related,
            'stats'        => $stats,
            'meta'         => $this->normalizeWebChatMeta($conversation),
        ]);
    }

    private function guardTenant(User $actor, int $tenantId): void
    {
        if ($actor->isSuperAdmin()) return;
        abort_unless((int) $actor->tenant_id === (int) $tenantId, 404);
    }

    private function normalizeWhatsAppMeta(Conversation $c): array
    {
        return [
            'display_id'    => '#' . $c->id,
            'title'         => $c->title,
            'contact_name'  => $c->customer?->display_name ?: $c->customer?->phone_e164 ?: '—',
            'contact_sub'   => $c->customer?->phone_e164,
            'source_label'  => $c->instance?->name,
            'source_url'    => null,
            'tenant_name'   => $c->tenant?->name,
            'created_at'    => $c->created_at,
            'closed_at'     => $c->closed_at,
            'closed_by'     => $c->ownerAgent?->name,
            'claimed_at'    => $c->claimed_at,
            'claimed_by'    => $c->ownerAgent?->name,
            'team_name'     => $c->team?->name,
        ];
    }

    private function normalizeWebChatMeta(WebChatConversation $c): array
    {
        return [
            'display_id'    => '#' . $c->id,
            'title'         => $c->title,
            'contact_name'  => $c->visitor?->name ?: $c->visitor_name ?: __('ui.archive_page.anonymous'),
            'contact_sub'   => $c->visitor?->email ?: $c->visitor_email,
            'source_label'  => $c->widget?->name,
            'source_url'    => $c->page_url,
            'tenant_name'   => $c->tenant?->name,
            'created_at'    => $c->created_at,
            'closed_at'     => $c->closed_at,
            'closed_by'     => $c->closer?->name,
            'claimed_at'    => $c->claimed_at,
            'claimed_by'    => $c->claimer?->name,
            'team_name'     => null,
        ];
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
