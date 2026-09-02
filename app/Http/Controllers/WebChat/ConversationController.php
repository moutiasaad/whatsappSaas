<?php

namespace App\Http\Controllers\WebChat;

use App\Events\WebChat\WebChatConversationClaimed;
use App\Events\WebChat\WebChatConversationClosed;
use App\Events\WebChat\WebChatConversationReleased;
use App\Events\WebChat\WebChatMessageSent;
use App\Http\Controllers\Controller;
use App\Models\WebChat\Conversation;
use App\Models\WebChat\Message;
use App\Services\AI\ConversationTitleGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        // Browser navigation → render the inbox Blade view. AJAX/JSON callers
        // get the paginated list. Same route serves both, matching the app's
        // convention on /users, /teams, /conversations, etc.
        if (!$request->expectsJson()) {
            return view('admin.webchat.index');
        }

        $user     = $request->user();
        $tenantId = $user->tenant_id;

        if (!$tenantId) {
            return response()->json(['data' => [], 'meta' => ['count' => 0]]);
        }

        $filter = $request->query('filter', 'pending');
        if (!in_array($filter, ['pending', 'mine', 'all', 'closed'], true)) {
            $filter = 'pending';
        }

        $rows = Conversation::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->with(['latestMessage', 'claimer:id,name'])
            ->when($filter === 'pending', fn ($q) => $q->where('status', Conversation::STATUS_PENDING))
            ->when($filter === 'mine',    fn ($q) => $q->where('status', Conversation::STATUS_ASSIGNED)->where('claimed_by', $user->id))
            ->when($filter === 'all',     fn ($q) => $q->where('status', '!=', Conversation::STATUS_CLOSED))
            ->when($filter === 'closed',  fn ($q) => $q->where('status', Conversation::STATUS_CLOSED))
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $data = $rows->map(fn (Conversation $c) => [
            'uuid'                 => $c->uuid,
            'status'               => $c->status,
            'title'                => $c->title,
            'visitor_name'         => $c->visitor_name,
            'visitor_email'        => $c->visitor_email,
            'page_url'             => $c->page_url,
            'claimer'              => $c->claimer ? ['id' => $c->claimer->id, 'name' => $c->claimer->name] : null,
            'last_activity_at'     => $c->last_activity_at?->toISOString(),
            'last_message_preview' => $c->latestMessage ? Str::limit($c->latestMessage->body, 120) : null,
            'created_at'           => $c->created_at?->toISOString(),
        ])->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'count'  => $data->count(),
                'filter' => $filter,
            ],
        ]);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $conversation = $this->findConversationForCurrentTenantOrFail($request, $uuid);

        $conversation->load([
            'visitor',
            'widget:id,name,tenant_id,theme_color',
            'claimer:id,name',
            'closer:id,name',
        ]);

        // `after` lets the agent inbox poll for message deltas without
        // re-downloading the whole thread. When absent, we return the full
        // history so the initial openRow render still works.
        $after = (int) $request->query('after', 0);

        $messages = Message::where('conversation_id', $conversation->id)
            ->when($after > 0, fn ($q) => $q->where('id', '>', $after))
            ->with('sender:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (Message $m) => [
                'id'          => $m->id,
                'sender_type' => $m->sender_type,
                'sender_id'   => $m->sender_id,
                'sender'      => $m->sender ? ['id' => $m->sender->id, 'name' => $m->sender->name] : null,
                'body'        => $m->body,
                'meta'        => $m->meta,
                'read_at'     => $m->read_at?->toISOString(),
                'created_at'  => $m->created_at?->toISOString(),
            ]);

        return response()->json([
            'conversation' => [
                'uuid'             => $conversation->uuid,
                'status'           => $conversation->status,
                'title'            => $conversation->title,
                'claimed_by'       => $conversation->claimed_by,
                'claimer'          => $conversation->claimer ? ['id' => $conversation->claimer->id, 'name' => $conversation->claimer->name] : null,
                'claimed_at'       => $conversation->claimed_at?->toISOString(),
                'closed_by'        => $conversation->closed_by,
                'closer'           => $conversation->closer ? ['id' => $conversation->closer->id, 'name' => $conversation->closer->name] : null,
                'closed_at'        => $conversation->closed_at?->toISOString(),
                'last_activity_at' => $conversation->last_activity_at?->toISOString(),
                'created_at'       => $conversation->created_at?->toISOString(),
            ],
            'visitor' => $conversation->visitor ? [
                'id'    => $conversation->visitor->id,
                'name'  => $conversation->visitor->name,
                'email' => $conversation->visitor->email,
            ] : null,
            'widget' => $conversation->widget ? [
                'id'          => $conversation->widget->id,
                'name'        => $conversation->widget->name,
                'theme_color' => $conversation->widget->theme_color,
            ] : null,
            'meta' => [
                'page_url'   => $conversation->page_url,
                'referrer'   => $conversation->referrer,
                'user_agent' => $conversation->user_agent,
                'ip'         => $conversation->ip,
            ],
            'messages' => $messages,
        ]);
    }

    public function claim(Request $request, string $uuid): JsonResponse
    {
        $user     = $request->user();
        $tenantId = $user->tenant_id;

        if (!$tenantId) {
            abort(404, 'webchat_conversation_not_found');
        }

        // Atomic compare-and-swap — tenant-hardened via WHERE clause.
        // Accepts both `bot` (AI is currently handling) and `pending` (waiting for
        // a human). A `bot` claim is an explicit takeover from the AI. Assigned or
        // closed rows are rejected. NOT check-then-set: the DB rejects the update
        // if the row is already claimed or the tenant does not match.
        $claimableStatuses = [Conversation::STATUS_BOT, Conversation::STATUS_PENDING];

        $updated = Conversation::withoutGlobalScope('tenant')
            ->where('uuid', $uuid)
            ->where('tenant_id', $tenantId)
            ->whereNull('claimed_by')
            ->whereIn('status', $claimableStatuses)
            ->update([
                'claimed_by'       => $user->id,
                'claimed_at'       => now(),
                'status'           => Conversation::STATUS_ASSIGNED,
                'last_activity_at' => now(),
            ]);

        if ($updated === 0) {
            // Distinguish missing/other-tenant (→ 404), already-assigned (→ 409
            // already_claimed), or closed (→ 409 conversation_closed).
            $existing = Conversation::withoutGlobalScope('tenant')
                ->where('uuid', $uuid)
                ->where('tenant_id', $tenantId)
                ->first(['status', 'claimed_by']);

            if (!$existing) {
                abort(404, 'webchat_conversation_not_found');
            }

            if ($existing->status === Conversation::STATUS_CLOSED) {
                return response()->json(['error' => 'conversation_closed'], 409);
            }

            return response()->json(['error' => 'already_claimed'], 409);
        }

        $conversation = Conversation::withoutGlobalScope('tenant')
            ->where('uuid', $uuid)
            ->firstOrFail();

        rescue(fn () => event(new WebChatConversationClaimed($conversation, $user)));

        return response()->json([
            'conversation' => [
                'uuid'       => $conversation->uuid,
                'status'     => $conversation->status,
                'claimed_by' => $conversation->claimed_by,
                'claimed_at' => $conversation->claimed_at?->toISOString(),
                'claimer'    => ['id' => $user->id, 'name' => $user->name],
            ],
        ]);
    }

    public function release(Request $request, string $uuid): JsonResponse
    {
        $conversation = $this->findConversationForCurrentTenantOrFail($request, $uuid);
        $user = $request->user();

        if (!$this->canManageLock($conversation, $user)) {
            abort(403, 'not_your_conversation');
        }

        if ($conversation->isClosed()) {
            return response()->json(['error' => 'conversation_closed'], 409);
        }

        $conversation->claimed_by       = null;
        $conversation->claimed_at       = null;
        $conversation->status           = Conversation::STATUS_PENDING;
        $conversation->last_activity_at = now();
        $conversation->save();

        rescue(fn () => event(new WebChatConversationReleased($conversation, $user)));

        return response()->json([
            'conversation' => [
                'uuid'   => $conversation->uuid,
                'status' => $conversation->status,
            ],
        ]);
    }

    public function close(Request $request, string $uuid): JsonResponse
    {
        $conversation = $this->findConversationForCurrentTenantOrFail($request, $uuid);
        $user = $request->user();

        if (!$this->canManageLock($conversation, $user)) {
            abort(403, 'not_your_conversation');
        }

        if ($conversation->isClosed()) {
            return response()->json([
                'conversation' => [
                    'uuid'   => $conversation->uuid,
                    'status' => $conversation->status,
                ],
            ]);
        }

        $data = $request->validate([
            'title' => 'nullable|string|max:180',
        ]);
        $title = isset($data['title']) ? trim($data['title']) : null;
        if ($title === '') $title = null;

        $systemMessage = DB::transaction(function () use ($conversation, $user, $title) {
            $conversation->status           = Conversation::STATUS_CLOSED;
            $conversation->closed_by        = $user->id;
            $conversation->closed_at        = now();
            $conversation->claimed_by       = null;
            $conversation->claimed_at       = null;
            $conversation->last_activity_at = now();
            if ($title !== null) {
                $conversation->title = $title;
            }
            $conversation->save();

            return Message::create([
                'conversation_id' => $conversation->id,
                'sender_type'     => Message::SENDER_SYSTEM,
                'sender_id'       => null,
                'body'            => 'Chat ended',
            ]);
        });

        $fresh = $conversation->fresh();
        rescue(fn () => event(new WebChatMessageSent($systemMessage->fresh(['conversation']))));
        rescue(fn () => event(new WebChatConversationClosed($fresh, $user)));

        return response()->json([
            'conversation' => [
                'uuid'      => $fresh->uuid,
                'status'    => $fresh->status,
                'title'     => $fresh->title,
                'closed_by' => $fresh->closed_by,
                'closed_at' => $fresh->closed_at?->toISOString(),
            ],
        ]);
    }

    public function suggestTitle(Request $request, string $uuid, ConversationTitleGenerator $titles): JsonResponse
    {
        $conversation = $this->findConversationForCurrentTenantOrFail($request, $uuid);
        $user = $request->user();

        if (!$this->canManageLock($conversation, $user)) {
            abort(403, 'not_your_conversation');
        }

        return response()->json(['title' => $titles->forWebChat($conversation)]);
    }

    public function markRead(Request $request, string $uuid): JsonResponse
    {
        $conversation = $this->findConversationForCurrentTenantOrFail($request, $uuid);
        $user = $request->user();

        if (!$this->canManageLock($conversation, $user)) {
            abort(403, 'not_your_conversation');
        }

        $count = Message::where('conversation_id', $conversation->id)
            ->where('sender_type', Message::SENDER_VISITOR)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'marked' => $count,
        ]);
    }

    /**
     * Look up a conversation by uuid AND assert it belongs to the current
     * tenant. Bypasses the tenant global scope on purpose — resolveRouteBinding
     * strips it too, so a guessed uuid could otherwise reach across tenants.
     */
    protected function findConversationForCurrentTenantOrFail(Request $request, string $uuid): Conversation
    {
        $tenantId = $request->user()?->tenant_id;

        if (!$tenantId) {
            abort(404, 'webchat_conversation_not_found');
        }

        $conversation = Conversation::withoutGlobalScope('tenant')
            ->where('uuid', $uuid)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$conversation) {
            abort(404, 'webchat_conversation_not_found');
        }

        return $conversation;
    }

    protected function canManageLock(Conversation $conversation, $user): bool
    {
        return $conversation->claimed_by === $user->id
            || $user->isAdmin()
            || $user->isSuperAdmin();
    }
}
