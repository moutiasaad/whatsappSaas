<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Conversations\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function __construct(private ConversationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tab  = $request->get('tab', 'pool');

        $query = Conversation::with(['customer', 'ownerAgent', 'instance'])
            ->latest('last_message_at');

        match ($tab) {
            'mine'   => $query->claimed()->where('owner_agent_id', $user->id),
            'closed' => $query->closed(),
            default  => $query->pool(),
        };

        if ($user->isAgent() || $user->isSupervisor()) {
            $query->whereIn('team_id', $user->teams->pluck('id'));
        }

        if ($search = trim((string) $request->get('search', ''))) {
            $query->whereHas('customer', fn($q) =>
                $q->where('display_name', 'like', "%{$search}%")
                  ->orWhere('phone_e164', 'like', "%{$search}%")
            );
        }

        return response()->json($query->paginate(20));
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
}
