<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Messenger\Conversation;
use App\Models\Messenger\Message;
use App\Services\Messenger\MessengerSendException;
use App\Services\Messenger\MessengerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * MessengerInboxController
 *
 * Tenant admin inbox for Messenger conversations. Mirrors WebChat's
 * ConversationController + MessageController pattern but leaner — enough
 * to see conversations, claim / release / close, reply, and mark read.
 * The WebChat controller (415 lines) has extra branches for polished
 * real-time UX; the Messenger inbox reaches feature parity in phases,
 * starting here with the essential CRUD.
 *
 * Every write goes through withoutGlobalScope('tenant') + explicit
 * tenant_id check — the same pattern WebChat + WhatsApp use, so a
 * guessed uuid can't reach across tenants.
 */
class MessengerInboxController extends Controller
{
    public const CLAIMABLE = [Conversation::STATUS_BOT, Conversation::STATUS_PENDING];

    // ─── Index ───────────────────────────────────────────────────────

    public function index(Request $request)
    {
        // Browser navigation → Blade view. AJAX/JSON callers get the
        // paginated list. Same URL serves both, matching the WebChat +
        // Users + Teams admin pages.
        if (! $request->expectsJson()) {
            // Empty inbox for an admin who hasn't connected any Page is
            // confusing — bounce them to settings with a "Connect a Page"
            // prompt. Supervisors and agents get the empty inbox because
            // they can't fix it (settings is admin-only).
            $user = $request->user();
            if ($user->isAdmin()) {
                $hasPage = \App\Models\Messenger\Page::withoutGlobalScope('tenant')
                    ->where('tenant_id', $user->tenant_id)
                    ->exists();
                if (! $hasPage) {
                    return redirect()->route('tenant_admin.messenger.settings');
                }
            }
            return view('admin.messenger.index');
        }

        $user     = $request->user();
        $tenantId = $user->tenant_id;

        if (! $tenantId) {
            return response()->json(['data' => [], 'meta' => ['count' => 0]]);
        }

        $filter = $request->query('filter', 'all');
        if (! in_array($filter, ['pending', 'mine', 'all', 'closed'], true)) {
            $filter = 'all';
        }

        $rows = Conversation::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->with(['latestMessage', 'claimer:id,name', 'page:id,page_name'])
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
            'contact_name'         => $c->contact_name ?: 'Facebook user',
            'contact_avatar_url'   => $c->contact_avatar_url,
            'page_name'            => $c->page?->page_name,
            'claimer'              => $c->claimer ? ['id' => $c->claimer->id, 'name' => $c->claimer->name] : null,
            'last_activity_at'     => $c->last_activity_at?->toISOString(),
            'last_inbound_at'      => $c->last_inbound_at?->toISOString(),
            'within_window'        => $c->withinMessagingWindow(),
            'last_message_preview' => $c->latestMessage ? Str::limit((string) $c->latestMessage->body, 120) : null,
            'created_at'           => $c->created_at?->toISOString(),
        ])->values();

        return response()->json([
            'data' => $data,
            'meta' => ['count' => $data->count(), 'filter' => $filter],
        ]);
    }

    // ─── Show ────────────────────────────────────────────────────────

    public function show(Request $request, string $uuid): JsonResponse
    {
        $conversation = $this->findOrFail($request, $uuid);

        $conversation->load(['page:id,page_name', 'claimer:id,name', 'closer:id,name']);

        // `after` lets the panel poll for new messages without re-downloading
        // history — same pattern WebChat uses. On the initial open it's absent,
        // so the full thread is returned.
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
                'attachments' => $m->attachments,
                'meta'        => $m->meta,
                'read_at'     => $m->read_at?->toISOString(),
                'created_at'  => $m->created_at?->toISOString(),
            ]);

        return response()->json([
            'conversation' => [
                'uuid'               => $conversation->uuid,
                'status'             => $conversation->status,
                'title'              => $conversation->title,
                'contact_name'       => $conversation->contact_name ?: 'Facebook user',
                'contact_avatar_url' => $conversation->contact_avatar_url,
                'psid'               => $conversation->psid,
                'page'               => $conversation->page ? [
                    'id'   => $conversation->page->id,
                    'name' => $conversation->page->page_name,
                ] : null,
                'claimed_by'       => $conversation->claimed_by,
                'claimer'          => $conversation->claimer ? ['id' => $conversation->claimer->id, 'name' => $conversation->claimer->name] : null,
                'claimed_at'       => $conversation->claimed_at?->toISOString(),
                'closed_by'        => $conversation->closed_by,
                'closer'           => $conversation->closer ? ['id' => $conversation->closer->id, 'name' => $conversation->closer->name] : null,
                'closed_at'        => $conversation->closed_at?->toISOString(),
                'last_activity_at' => $conversation->last_activity_at?->toISOString(),
                'last_inbound_at'  => $conversation->last_inbound_at?->toISOString(),
                'within_window'    => $conversation->withinMessagingWindow(),
                'created_at'       => $conversation->created_at?->toISOString(),
            ],
            'messages' => $messages,
        ]);
    }

    // ─── State transitions ───────────────────────────────────────────

    public function claim(Request $request, string $uuid): JsonResponse
    {
        $user     = $request->user();
        $tenantId = $user->tenant_id;

        if (! $tenantId) {
            abort(404, 'messenger_conversation_not_found');
        }

        // Atomic compare-and-swap, tenant-hardened via WHERE. Accepts `bot`
        // (AI is handling — explicit takeover) OR `pending` (waiting for human).
        // Assigned or closed rows are rejected.
        $updated = Conversation::withoutGlobalScope('tenant')
            ->where('uuid', $uuid)
            ->where('tenant_id', $tenantId)
            ->whereNull('claimed_by')
            ->whereIn('status', self::CLAIMABLE)
            ->update([
                'claimed_by'       => $user->id,
                'claimed_at'       => now(),
                'status'           => Conversation::STATUS_ASSIGNED,
                'last_activity_at' => now(),
            ]);

        if ($updated === 0) {
            $existing = Conversation::withoutGlobalScope('tenant')
                ->where('uuid', $uuid)->where('tenant_id', $tenantId)
                ->first(['status', 'claimed_by']);

            if (! $existing) abort(404, 'messenger_conversation_not_found');
            if ($existing->status === Conversation::STATUS_CLOSED) {
                return response()->json(['error' => 'conversation_closed'], 409);
            }
            return response()->json(['error' => 'already_claimed'], 409);
        }

        $conversation = $this->findOrFail($request, $uuid);

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
        $conversation = $this->findOrFail($request, $uuid);
        $user = $request->user();

        if (! $this->canManageLock($conversation, $user)) {
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

        return response()->json([
            'conversation' => [
                'uuid'   => $conversation->uuid,
                'status' => $conversation->status,
            ],
        ]);
    }

    public function close(Request $request, string $uuid): JsonResponse
    {
        $conversation = $this->findOrFail($request, $uuid);
        $user = $request->user();

        if (! $this->canManageLock($conversation, $user)) {
            abort(403, 'not_your_conversation');
        }
        if ($conversation->isClosed()) {
            return response()->json(['error' => 'already_closed'], 409);
        }

        $data = $request->validate(['title' => 'nullable|string|max:190']);

        $conversation->status           = Conversation::STATUS_CLOSED;
        $conversation->closed_by        = $user->id;
        $conversation->closed_at        = now();
        $conversation->last_activity_at = now();
        if (! empty($data['title'])) {
            $conversation->title = $data['title'];
        }
        $conversation->save();

        return response()->json([
            'conversation' => [
                'uuid'      => $conversation->uuid,
                'status'    => $conversation->status,
                'closed_at' => $conversation->closed_at?->toISOString(),
                'title'     => $conversation->title,
            ],
        ]);
    }

    public function markRead(Request $request, string $uuid): JsonResponse
    {
        $conversation = $this->findOrFail($request, $uuid);

        Message::where('conversation_id', $conversation->id)
            ->where('sender_type', Message::SENDER_VISITOR)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    // ─── Send reply ──────────────────────────────────────────────────

    public function reply(Request $request, string $uuid, MessengerService $messenger): JsonResponse
    {
        $conversation = $this->findOrFail($request, $uuid);
        $user = $request->user();

        if ($conversation->isClosed()) {
            return response()->json(['error' => 'conversation_closed'], 409);
        }

        // Auto-claim: if unclaimed and this agent is sending, claim to them.
        // Matches the WebChat flow — agents shouldn't have to click Claim
        // separately when they simply start typing a reply.
        if ($conversation->claimed_by === null) {
            $conversation->claimed_by       = $user->id;
            $conversation->claimed_at       = now();
            $conversation->status           = Conversation::STATUS_ASSIGNED;
            $conversation->save();
        } elseif ($conversation->claimed_by !== $user->id && ! $user->isAdmin() && ! $user->isSuperAdmin()) {
            return response()->json(['error' => 'not_your_conversation'], 403);
        }

        $data = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        try {
            $message = $messenger->sendText(
                conversationId: $conversation->id,
                body: $data['body'],
                senderUserId: $user->id,
                senderType: Message::SENDER_AGENT,
            );
        } catch (MessengerSendException $e) {
            Log::channel('messenger')->warning('Reply from agent failed', [
                'conversation_id' => $conversation->id,
                'user_id'         => $user->id,
                'reason'          => $e->reason,
            ]);
            return response()->json([
                'error'   => $e->reason,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => [
                'id'          => $message->id,
                'sender_type' => $message->sender_type,
                'body'        => $message->body,
                'created_at'  => $message->created_at?->toISOString(),
            ],
        ], 201);
    }

    // ─── Internals ───────────────────────────────────────────────────

    protected function findOrFail(Request $request, string $uuid): Conversation
    {
        $tenantId = $request->user()?->tenant_id;

        if (! $tenantId) {
            abort(404, 'messenger_conversation_not_found');
        }

        $conversation = Conversation::withoutGlobalScope('tenant')
            ->where('uuid', $uuid)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $conversation) {
            abort(404, 'messenger_conversation_not_found');
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
