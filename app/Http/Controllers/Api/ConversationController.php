<?php

namespace App\Http\Controllers\Api;

use App\Events\AgentTyping;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Services\AI\ConversationTitleGenerator;
use App\Services\Conversations\ConversationService;
use App\Services\WhatsApp\Gateway\EvolutionApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ConversationController extends Controller
{
    public function __construct(private ConversationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'tab' => ['nullable', Rule::in(['pool', 'mine', 'claimed', 'closed', 'all'])],
            'search' => 'nullable|string|max:120',
            'tenant_id' => 'nullable|integer|exists:tenants,id',
            'team_id' => 'nullable|integer|exists:teams,id',
            'instance_id' => 'nullable|integer|exists:whatsapp_instances,id',
            'agent_id' => 'nullable|integer|exists:users,id',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'state' => ['nullable', Rule::in(['pool', 'claimed', 'closed'])],
            'ai_suspended' => 'nullable|boolean',
            'has_unread' => 'nullable|boolean',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'sort' => ['nullable', Rule::in(['last_message_desc', 'last_message_asc', 'created_desc', 'created_asc'])],
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $tab  = $data['tab'] ?? 'pool';

        $query = Conversation::query()
            ->with(['customer', 'ownerAgent', 'instance', 'team', 'tenant']);

        match ($tab) {
            'mine'   => $query->claimed()->where('owner_agent_id', $user->id),
            'claimed' => $query->claimed(),
            'closed' => $query->closed(),
            'all' => $query,
            default  => $query->pool(),
        };

        if ($user->isAgent() || $user->isSupervisor()) {
            $teamIds = $user->teams->pluck('id');
            $query->where(function ($q) use ($teamIds) {
                $q->whereIn('team_id', $teamIds)->orWhereNull('team_id');
            })->where('tenant_id', $user->tenant_id);
        } elseif ($user->isAdmin()) {
            $query->where('tenant_id', $user->tenant_id);
        }

        if ($user->isSuperAdmin() && !empty($data['tenant_id'])) {
            $query->where('tenant_id', (int) $data['tenant_id']);
        }

        if (!empty($data['team_id'])) {
            $query->where('team_id', (int) $data['team_id']);
        }

        if (!empty($data['instance_id'])) {
            $query->where('instance_id', (int) $data['instance_id']);
        }

        if (!empty($data['agent_id'])) {
            $query->where('owner_agent_id', (int) $data['agent_id']);
        }

        if (!empty($data['customer_id'])) {
            $query->where('customer_id', (int) $data['customer_id']);
        }

        if (!empty($data['state'])) {
            $query->where('state', $data['state']);
        }

        if (array_key_exists('ai_suspended', $data) && $data['ai_suspended'] !== null && $data['ai_suspended'] !== '') {
            $query->where('ai_suspended', (bool) $data['ai_suspended']);
        }

        if (array_key_exists('has_unread', $data) && $data['has_unread'] !== null && $data['has_unread'] !== '') {
            if ((bool) $data['has_unread']) {
                $query->where('unread_count', '>', 0);
            } else {
                $query->where('unread_count', '=', 0);
            }
        }

        if (!empty($data['date_from'])) {
            $query->whereDate('last_message_at', '>=', $data['date_from']);
        }

        if (!empty($data['date_to'])) {
            $query->whereDate('last_message_at', '<=', $data['date_to']);
        }

        if ($search = trim((string) ($data['search'] ?? ''))) {
            $query->where(function ($inner) use ($search) {
                $inner->where('last_message_preview', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('id', is_numeric($search) ? (int) $search : 0)
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('display_name', 'like', "%{$search}%")
                            ->orWhere('phone_e164', 'like', "%{$search}%");
                    })
                    ->orWhereHas('instance', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('team', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('ownerAgent', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        match ($data['sort'] ?? 'last_message_desc') {
            'last_message_asc' => $query->orderBy('last_message_at'),
            'created_desc' => $query->orderByDesc('created_at'),
            'created_asc' => $query->orderBy('created_at'),
            default => $query->orderByDesc('last_message_at'),
        };

        return response()->json($query->paginate((int) ($data['per_page'] ?? 20)));
    }

    public function show(Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);
        return response()->json($conversation->load(['customer', 'ownerAgent', 'team', 'instance']));
    }

    public function workspace(Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $conversation->load(['customer', 'ownerAgent', 'team', 'instance', 'tenant']);

        $messages = \App\Models\Message::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit(41)
            ->get();

        $hasMore = $messages->count() > 40;
        if ($hasMore) {
            $messages = $messages->take(40);
        }
        $ordered = $messages->reverse()->values();

        $events = $conversation->events()->with('actor')->orderByDesc('created_at')->limit(50)->get();

        $teamAgents = $conversation->team
            ? $conversation->team->users()
                ->whereIn('role', ['agent', 'supervisor'])
                ->where('is_active', true)
                ->orderBy('users.name')
                ->get(['users.id', 'users.name'])
            : User::query()
                ->whereIn('role', ['agent', 'supervisor'])
                ->where('is_active', true)
                ->where('tenant_id', $conversation->tenant_id)
                ->orderBy('name')
                ->get(['id', 'name']);

        $customerConversationCount = Conversation::where('customer_id', $conversation->customer_id)->count();

        $aiMode = $conversation->tenant?->aiSettings?->mode ?? 'off';

        return response()->json([
            'conversation' => [
                'id'              => $conversation->id,
                'tenant_id'       => $conversation->tenant_id,
                'state'           => $conversation->state,
                'title'           => $conversation->title,
                'owner_agent_id'  => $conversation->owner_agent_id,
                'ai_suspended'    => (bool) $conversation->ai_suspended,
                'last_message_at' => optional($conversation->last_message_at)->toIso8601String(),
                'created_at'      => optional($conversation->created_at)->toIso8601String(),
            ],
            'customer' => [
                'id'              => $conversation->customer?->id,
                'display_name'    => $conversation->customer?->displayNameOrPhone,
                'phone_e164'      => $conversation->customer?->displayPhone,
                'profile_pic_url' => $conversation->customer?->profile_pic_url,
            ],
            'instance' => $conversation->instance ? [
                'id'   => $conversation->instance->id,
                'name' => $conversation->instance->name,
            ] : null,
            'team' => $conversation->team ? [
                'id'   => $conversation->team->id,
                'name' => $conversation->team->name,
            ] : null,
            'tenant' => $conversation->tenant ? [
                'id'   => $conversation->tenant->id,
                'name' => $conversation->tenant->name,
            ] : null,
            'owner_agent' => $conversation->ownerAgent ? [
                'id'   => $conversation->ownerAgent->id,
                'name' => $conversation->ownerAgent->name,
            ] : null,
            'messages' => [
                'data'        => $ordered->map(fn ($m) => [
                    'id'                  => $m->id,
                    'conversation_id'     => $m->conversation_id,
                    'direction'           => $m->direction,
                    'author_type'         => $m->author_type,
                    'author_id'           => $m->author_id,
                    'type'                => $m->type,
                    'body'                => $m->body,
                    'media_url'           => $m->media_url,
                    'media_mime'          => $m->media_mime,
                    'status'              => $m->status,
                    'ai_metadata'         => $m->ai_metadata,
                    'external_message_id' => $m->external_message_id,
                    'sent_at'             => optional($m->sent_at)->toIso8601String(),
                    'created_at'          => optional($m->created_at)->toIso8601String(),
                ])->all(),
                'next_cursor' => $hasMore ? $ordered->first()->id : null,
            ],
            'events' => $events->map(fn ($e) => [
                'id'         => $e->id,
                'type'       => $e->type,
                'actor_name' => $e->actor?->name,
                'created_at' => optional($e->created_at)->toIso8601String(),
            ])->all(),
            'team_agents'                 => $teamAgents->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all(),
            'customer_conversation_count' => $customerConversationCount,
            'ai_mode'                     => $aiMode,
            'ai_suspended'                => (bool) $conversation->ai_suspended,
        ]);
    }

    public function claim(Conversation $conversation, Request $request): JsonResponse
    {
        $this->authorize('claim', $conversation);

        if (!$this->service->claim($conversation, $request->user())) {
            return response()->json(['message' => 'Already claimed by someone else.'], 409);
        }

        return response()->json(['message' => 'Claimed.', 'conversation' => $conversation->fresh()]);
    }

    public function release(Conversation $conversation, Request $request): JsonResponse
    {
        $this->authorize('release', $conversation);
        $this->service->release($conversation, $request->user());
        return response()->json(['message' => 'Released to pool.']);
    }

    public function close(Conversation $conversation, Request $request): JsonResponse
    {
        $this->authorize('close', $conversation);

        $data = $request->validate([
            'title' => 'nullable|string|max:180',
        ]);

        $title = isset($data['title']) ? trim($data['title']) : null;
        $this->service->close($conversation, $request->user(), $title !== '' ? $title : null);

        return response()->json([
            'message'      => 'Closed.',
            'conversation' => [
                'id'    => $conversation->id,
                'title' => $conversation->fresh()->title,
            ],
        ]);
    }

    public function suggestTitle(Conversation $conversation, ConversationTitleGenerator $titles): JsonResponse
    {
        $this->authorize('view', $conversation);
        return response()->json(['title' => $titles->forWhatsApp($conversation)]);
    }

    public function reassign(Conversation $conversation, Request $request): JsonResponse
    {
        $this->authorize('reassign', $conversation);
        $request->validate(['agent_id' => 'required|exists:users,id']);
        $newAgent = User::findOrFail($request->agent_id);

        if (
            !$newAgent->isAgent()
            || (string) $newAgent->tenant_id !== (string) $conversation->tenant_id
            || ($conversation->team_id !== null && !$newAgent->teams->contains('id', $conversation->team_id))
        ) {
            return response()->json(['message' => 'Selected assignee is not eligible for this conversation.'], 422);
        }

        $this->service->reassign($conversation, $newAgent, $request->user());
        return response()->json(['message' => 'Reassigned.']);
    }

    public function reopen(Conversation $conversation, Request $request): JsonResponse
    {
        $this->authorize('reopen', $conversation);
        $this->service->reopen($conversation, $request->user());
        return response()->json(['message' => 'Reopened.']);
    }

    public function markRead(Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $conversation->update(['unread_count' => 0]);

        return response()->json(['message' => 'Read.']);
    }

    public function toggleAi(Conversation $conversation, Request $request): JsonResponse
    {
        $this->authorize('toggleAi', $conversation);
        $data = $request->validate(['ai_suspended' => 'required|boolean']);
        $this->service->toggleAi($conversation, $request->user(), (bool) $data['ai_suspended']);
        return response()->json([
            'message' => $data['ai_suspended'] ? 'AI suspended.' : 'AI resumed.',
            'ai_suspended' => (bool) $data['ai_suspended'],
        ]);
    }

    public function checkNumber(Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $instance = $conversation->instance;
        $phone    = $conversation->customer->phone_e164;

        if (!$phone || !$instance?->gateway_instance_id) {
            return response()->json(['exists' => null, 'error' => 'Missing phone or instance'], 422);
        }

        try {
            $gateway = new EvolutionApiClient(
                $instance->effectiveGatewayUrl(),
                $instance->effectiveGatewayApiKey()
            );
            $results = $gateway->checkNumbers($instance->gateway_instance_id, [$phone]);
            $first   = $results[0] ?? null;

            return response()->json([
                'exists' => $first['exists'] ?? null,
                'jid'    => $first['jid'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::channel('whatsapp')->warning('checkNumber error', [
                'conversation_id' => $conversation->id,
                'error'           => $e->getMessage(),
            ]);
            return response()->json(['exists' => null, 'error' => 'Gateway check failed'], 502);
        }
    }

    public function updatePresence(Conversation $conversation, Request $request): JsonResponse
    {
        $data = $request->validate([
            'presence' => ['required', \Illuminate\Validation\Rule::in(['unavailable', 'available', 'composing', 'recording', 'paused'])],
        ]);

        // Broadcast typing to other agents viewing the same conversation
        try {
            broadcast(new AgentTyping($conversation, $request->user(), $data['presence']))->toOthers();
        } catch (\Exception $e) {
            Log::channel('whatsapp')->debug('AgentTyping broadcast failed', [
                'conversation_id' => $conversation->id,
                'error'           => $e->getMessage(),
            ]);
        }

        // Forward to WhatsApp gateway so the customer also sees the typing dots
        try {
            $instance = $conversation->instance;
            $phone    = $conversation->customer->phone_e164;

            if ($phone && $instance?->gateway_instance_id) {
                $gateway = new EvolutionApiClient(
                    $instance->effectiveGatewayUrl(),
                    $instance->effectiveGatewayApiKey()
                );
                $gateway->updatePresence($instance->gateway_instance_id, $phone, $data['presence']);
            }
        } catch (\Exception $e) {
            Log::channel('whatsapp')->debug('updatePresence gateway error', [
                'conversation_id' => $conversation->id,
                'presence'        => $data['presence'],
                'error'           => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
