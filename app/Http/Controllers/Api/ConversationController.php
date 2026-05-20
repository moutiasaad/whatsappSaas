<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Conversations\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            $query->whereIn('team_id', $user->teams->pluck('id'));
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
        $this->service->close($conversation, $request->user());
        return response()->json(['message' => 'Closed.']);
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
}
