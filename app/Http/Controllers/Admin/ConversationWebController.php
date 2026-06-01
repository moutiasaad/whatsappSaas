<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\ConversationController as ApiConversationController;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SavedReply;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppInstance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ConversationWebController extends Controller
{
    private const PRELOAD_MESSAGES = 40;
    private const RAIL_LIST_LIMIT  = 80;
    private const PRESENCE_TTL     = 90;

    public function index(Request $request)
    {
        if ($request->expectsJson()) {
            return app(ApiConversationController::class)
                ->index($request)
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        $actor = auth()->user();

        // Agents go straight to the workspace — redirect to their most recent active conversation
        if ($actor->isAgent()) {
            $teamIds = $actor->teams()->pluck('teams.id');
            $first = Conversation::query()
                ->where('tenant_id', $actor->tenant_id)
                ->where(function ($q) use ($actor, $teamIds) {
                    $q->where('owner_agent_id', $actor->id)
                      ->orWhereIn('team_id', $teamIds);
                })
                ->where('state', '!=', 'closed')
                ->orderByDesc('last_message_at')
                ->first();

            if ($first) {
                return redirect()->route($actor->routeNamePrefix() . '.conversations.show', $first);
            }
        }
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

        $actor = auth()->user();

        $conversation->load(['customer', 'instance', 'ownerAgent', 'team', 'tenant']);

        $events = $conversation->events()->with('actor')->orderByDesc('created_at')->limit(50)->get();

        $teamAgents = $this->loadAssignableAgents($conversation);

        $customerConversationCount = Conversation::where('customer_id', $conversation->customer_id)->count();

        $aiSettings = $conversation->tenant?->aiSettings;
        $aiMode = $aiSettings?->mode ?? 'off';

        $messagesPage      = $this->loadInitialMessages($conversation);
        $preloadedMessages = $messagesPage['data'];
        $messagesCursor    = $messagesPage['next_cursor'];

        $conversationList = $this->loadConversationList($actor, $conversation);

        $savedReplies = $this->loadSavedReplies($actor);

        $onlineAgents = $this->loadOnlineAgents($actor);

        $i18n = $this->chatI18n();

        return view('admin.conversations.show', compact(
            'conversation',
            'events',
            'teamAgents',
            'customerConversationCount',
            'aiMode',
            'conversationList',
            'preloadedMessages',
            'messagesCursor',
            'savedReplies',
            'onlineAgents',
            'i18n'
        ));
    }

    private function chatI18n(): array
    {
        $keys = [
            'search_placeholder', 'search_replies_placeholder', 'caption_placeholder',
            'typing', 'agent_typing', 'reconnecting', 'team_online', 'browse_replies',
            'no_replies_match', 'more', 'internal_note', 'ai_reply', 'document',
            'clear', 'remove', 'profile', 'whatsapp', 'call',
            'attach_file', 'attach_image', 'attach_document', 'attach_audio',
            'drop_to_send', 'drop_hint', 'uploading', 'upload_failed', 'file_too_large',
            'not_on_whatsapp', 'unassigned', 'system', 'no_conversations',
            'state_pool', 'state_claimed', 'state_closed',
            'release_title', 'release_desc', 'close_title', 'close_desc',
            'reopen_title', 'reopen_desc',
            'claim_success', 'claim_error', 'close_success', 'reopen_success',
            'reassign_success', 'reassign_failed',
            'ai_updated', 'ai_update_failed', 'send_failed', 'network_error',
            'load_messages_failed', 'load_workspace_failed',
            'live_workspace', 'suspended',
        ];

        $bag = [];
        foreach ($keys as $k) {
            $bag[$k] = __('ui.conversation_show_page.' . $k);
        }
        return $bag;
    }

    private function loadAssignableAgents(Conversation $conversation): \Illuminate\Support\Collection
    {
        if ($conversation->team) {
            return $conversation->team->users()
                ->whereIn('role', ['agent', 'supervisor'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        return User::query()
            ->whereIn('role', ['agent', 'supervisor'])
            ->where('is_active', true)
            ->where('tenant_id', $conversation->tenant_id)
            ->orderBy('name')
            ->get();
    }

    private function loadInitialMessages(Conversation $conversation): array
    {
        $rows = Message::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit(self::PRELOAD_MESSAGES + 1)
            ->get();

        $hasMore = $rows->count() > self::PRELOAD_MESSAGES;
        if ($hasMore) {
            $rows = $rows->take(self::PRELOAD_MESSAGES);
        }

        $ordered = $rows->reverse()->values();

        return [
            'data'        => $ordered->map(fn ($m) => $this->serializeMessage($m))->all(),
            'next_cursor' => $hasMore ? $ordered->first()->id : null,
        ];
    }

    private function serializeMessage(Message $message): array
    {
        return [
            'id'                  => $message->id,
            'conversation_id'     => $message->conversation_id,
            'direction'           => $message->direction,
            'author_type'         => $message->author_type,
            'author_id'           => $message->author_id,
            'type'                => $message->type,
            'body'                => $message->body,
            'media_url'           => $message->media_url,
            'media_mime'          => $message->media_mime,
            'status'              => $message->status,
            'ai_metadata'         => $message->ai_metadata,
            'external_message_id' => $message->external_message_id,
            'sent_at'             => optional($message->sent_at)->toIso8601String(),
            'created_at'          => optional($message->created_at)->toIso8601String(),
        ];
    }

    private function loadConversationList(User $user, Conversation $current): \Illuminate\Support\Collection
    {
        $query = Conversation::query()
            ->with([
                'customer:id,display_name,phone_e164,profile_pic_url',
                'ownerAgent:id,name',
                'instance:id,name',
                'team:id,name',
            ])
            ->orderByDesc('last_message_at');

        if ($user->isAgent() || $user->isSupervisor()) {
            $teamIds = $user->teams->pluck('id');
            $query->where(function ($q) use ($teamIds) {
                $q->whereIn('team_id', $teamIds)->orWhereNull('team_id');
            })->where('tenant_id', $user->tenant_id);
        } elseif ($user->isAdmin()) {
            $query->where('tenant_id', $user->tenant_id);
        } elseif ($user->isSuperAdmin()) {
            $query->where('tenant_id', $current->tenant_id);
        }

        return $query->limit(self::RAIL_LIST_LIMIT)->get();
    }

    private function loadSavedReplies(User $user): \Illuminate\Support\Collection
    {
        if (!$user->tenant_id) {
            return collect();
        }

        $this->ensureDefaultRepliesForTenant($user->tenant_id);

        return SavedReply::query()
            ->visibleTo($user)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['id', 'scope', 'owner_user_id', 'title', 'shortcut', 'body', 'sort_order']);
    }

    private function ensureDefaultRepliesForTenant(int $tenantId): void
    {
        $exists = SavedReply::query()
            ->where('tenant_id', $tenantId)
            ->where('scope', 'tenant')
            ->exists();

        if ($exists) {
            return;
        }

        $defaults = [
            ['Greeting',  '/hi',     'Hello! Thanks for reaching out. How can I help you today?', 10],
            ['Hold on',   '/wait',   'Thanks for waiting. I am checking this now and will update you shortly.', 20],
            ['Resolved',  '/done',   'This issue is now resolved. Please confirm on your side.', 30],
            ['Follow up', '/follow', 'Just following up to make sure everything is working as expected.', 40],
            ['Closing',   '/close',  'Glad we could help! I am closing this conversation — feel free to reach out anytime.', 50],
        ];

        foreach ($defaults as [$title, $shortcut, $body, $order]) {
            SavedReply::create([
                'tenant_id'     => $tenantId,
                'owner_user_id' => null,
                'scope'         => 'tenant',
                'title'         => $title,
                'shortcut'      => $shortcut,
                'body'          => $body,
                'sort_order'    => $order,
            ]);
        }
    }

    private function loadOnlineAgents(User $actor): \Illuminate\Support\Collection
    {
        $agents = User::query()
            ->whereIn('role', ['admin', 'supervisor', 'agent'])
            ->where('is_active', true)
            ->when(!$actor->isSuperAdmin(), fn ($q) => $q->where('tenant_id', $actor->tenant_id))
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $now = now()->timestamp;

        return $agents->map(function ($agent) use ($now) {
            $last = Cache::get("agent_presence:{$agent->id}");
            $isOnline = $last && ($now - (int) $last) <= self::PRESENCE_TTL;
            return [
                'id'     => $agent->id,
                'name'   => $agent->name,
                'role'   => $agent->role,
                'online' => (bool) $isOnline,
            ];
        })->sortByDesc('online')->values();
    }
}
