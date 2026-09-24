<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Messenger\Conversation as MessengerConversation;
use App\Models\Messenger\Message as MessengerMessage;
use App\Models\User;
use App\Models\WebChat\Conversation as WebChatConversation;
use App\Models\WebChat\Message as WebChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Unified inbox — WhatsApp, Live Chat AND Messenger conversations in one list.
 *
 * Read paths (list + thread) are normalised here so the page renders one shape
 * regardless of channel. Write paths (claim / release / close / reply) stay on
 * each channel's own endpoints, so their policies, events and broadcasts are
 * untouched — the page just picks the right URL from the row's `channel`.
 */
class InboxController extends Controller
{
    public const CHANNELS = ['all', 'whatsapp', 'webchat', 'messenger'];
    public const TABS     = ['pending', 'mine', 'all', 'closed'];

    private const LIMIT = 150;

    public function index(Request $request)
    {
        // Open on "Mine" when the agent is already holding something: their own
        // claimed threads are the work in front of them, and landing on Pending
        // hid it behind a tab. Counted here rather than after the first list
        // response so the opening request already asks for the right tab —
        // switching afterwards would mean two requests and a visible flip.
        $counts = $this->counts($request, 'all');

        [$waState, $waFixUrl] = $this->whatsAppLinkState($request);

        return view('admin.inbox.index', [
            'canUseWebChat'   => $this->canUseWebChat(),
            'canUseMessenger' => $this->canUseMessenger(),
            'initialTab'      => ($counts['mine'] ?? 0) > 0 ? 'mine' : 'pending',
            'initialCounts'   => $counts,
            'waState'         => $waState,
            'waFixUrl'        => $waFixUrl,
        ]);
    }

    /**
     * Whether WhatsApp can actually deliver into this inbox, and where to fix
     * it if not.
     *
     * An empty inbox looks the same whether it is quiet or broken, and the
     * difference matters: a workspace whose phone was unlinked keeps waiting
     * for messages that will never arrive. Returns one of `linked`, `pairing`,
     * `offline` or `none`, plus the connection page for whoever is allowed to
     * open it — an agent sees the warning but has nothing to click, since the
     * instance routes are admin-only.
     */
    private function whatsAppLinkState(Request $request): array
    {
        $user = $request->user();

        // Nothing to warn about on a plan that does not sell the channel, or
        // for a super admin, who is not looking at one workspace's phone.
        if (!$user?->tenant_id || $user->isSuperAdmin() || !($user->tenant?->planAllows('whatsapp') ?? false)) {
            return ['linked', null];
        }

        $status = \App\Models\WhatsAppInstance::where('tenant_id', $user->tenant_id)
            ->orderBy('id')
            ->value('status');

        $state = match (true) {
            $status === null                                  => 'none',
            $status === 'connected'                           => 'linked',
            in_array($status, ['qr_pending', 'connecting'], true) => 'pairing',
            default                                           => 'offline',
        };

        $route = $user->routeNamePrefix() . '.instances.index';

        return [
            $state,
            ($state !== 'linked' && \Illuminate\Support\Facades\Route::has($route)) ? route($route) : null,
        ];
    }

    // ── list ────────────────────────────────────────────────────────────────

    public function list(Request $request): JsonResponse
    {
        $channel = in_array($request->query('channel'), self::CHANNELS, true)
            ? $request->query('channel')
            : 'all';
        $tab = in_array($request->query('tab'), self::TABS, true)
            ? $request->query('tab')
            : 'pending';
        $search = trim((string) $request->query('q', ''));

        $rows = collect();

        // Each channel opts-IN when the requested filter is 'all' OR names it
        // explicitly. We collect from all sources, then time-sort + cap.
        if ($channel === 'all' || $channel === 'whatsapp') {
            $rows = $rows->merge($this->whatsappRows($request, $tab, $search));
        }

        if (($channel === 'all' || $channel === 'webchat') && $this->canUseWebChat()) {
            $rows = $rows->merge($this->webchatRows($request, $tab, $search));
        }

        if (($channel === 'all' || $channel === 'messenger') && $this->canUseMessenger()) {
            $rows = $rows->merge($this->messengerRows($request, $tab, $search));
        }

        $rows = $rows
            ->sortByDesc(fn (array $r) => $r['last_activity_at'] ?? '')
            ->values()
            ->take(self::LIMIT);

        return response()->json([
            'data'   => $rows,
            'counts' => $this->counts($request, $channel),
            'meta'   => ['channel' => $channel, 'tab' => $tab, 'count' => $rows->count()],
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    private function whatsappRows(Request $request, string $tab, string $search)
    {
        $user = $request->user();

        $query = Conversation::query()
            ->with(['customer:id,display_name,phone_e164', 'ownerAgent:id,name', 'instance:id,name']);

        $this->scopeWhatsApp($query, $user);

        match ($tab) {
            'mine'   => $query->where('state', 'claimed')->where('owner_agent_id', $user->id),
            'all'    => $query->where('state', '!=', 'closed'),
            'closed' => $query->where('state', 'closed'),
            default  => $query->where('state', 'pool'),
        };

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('last_message_preview', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c
                        ->where('display_name', 'like', "%{$search}%")
                        ->orWhere('phone_e164', 'like', "%{$search}%"));
            });
        }

        $rows = $query->orderByDesc('last_message_at')->limit(self::LIMIT)->get();

        // One extra query rather than a subquery per row: which of these the AI
        // has actually answered, so a pooled thread reads "AI" and not "pending".
        $aiAnswered = $this->aiAnsweredIds($rows->pluck('id')->all());

        return $rows
            ->map(function (Conversation $c) use ($aiAnswered) {
                $name = $c->customer?->display_name ?: ($c->customer?->phone_e164 ?: __('ui.inbox_page.unknown_contact'));

                return [
                    'channel'          => 'whatsapp',
                    'key'              => 'wa-' . $c->id,
                    'ref'              => (string) $c->id,
                    'name'             => $name,
                    'subtitle'         => $c->customer?->phone_e164,
                    'initials'         => $this->initials($name),
                    'preview'          => $c->title ?: $c->last_message_preview,
                    'status'           => $this->whatsappStatus($c->state),
                    'ai'               => isset($aiAnswered[$c->id]) && !$c->ai_suspended,
                    // A closed thread has been dealt with, so the flag stops
                    // being news; it survives a claim on purpose, so a
                    // supervisor can still see which threads the AI handed over.
                    'escalated'        => $c->escalated_at !== null && !$c->isClosed(),
                    'assignee'         => $c->ownerAgent ? ['id' => $c->ownerAgent->id, 'name' => $c->ownerAgent->name] : null,
                    'unread'           => (int) $c->unread_count,
                    'last_activity_at' => optional($c->last_message_at ?? $c->created_at)->toISOString(),
                ];
            });
    }

    private function webchatRows(Request $request, string $tab, string $search)
    {
        $user = $request->user();

        $query = WebChatConversation::withoutGlobalScope('tenant')
            ->where('tenant_id', $user->tenant_id)
            ->with(['latestMessage', 'claimer:id,name']);

        match ($tab) {
            'mine'   => $query->where('status', WebChatConversation::STATUS_ASSIGNED)->where('claimed_by', $user->id),
            'all'    => $query->where('status', '!=', WebChatConversation::STATUS_CLOSED),
            'closed' => $query->where('status', WebChatConversation::STATUS_CLOSED),
            // A bot-handled chat has no human on it, which is the same footing as
            // a pooled WhatsApp thread — and those do list under pending, badged
            // "AI". Leaving 'bot' out meant an agent watching pending never saw a
            // live visitor the AI was still answering; it surfaced only under All.
            default  => $query->whereIn('status', [
                WebChatConversation::STATUS_PENDING,
                WebChatConversation::STATUS_BOT,
            ]),
        };

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('visitor_name', 'like', "%{$search}%")
                    ->orWhere('visitor_email', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(function (WebChatConversation $c) {
                $name = $c->visitor_name ?: __('ui.webchat_page.visitor_prefix') . Str::limit($c->uuid, 6, '');

                return [
                    'channel'          => 'webchat',
                    'key'              => 'lc-' . $c->uuid,
                    'ref'              => $c->uuid,
                    'name'             => $name,
                    'subtitle'         => $c->visitor_email ?: $c->page_url,
                    'initials'         => $this->initials($c->visitor_name ?: $c->uuid),
                    'preview'          => $c->title ?: ($c->latestMessage ? Str::limit($c->latestMessage->body, 120) : null),
                    'status'           => $this->webchatStatus($c->status),
                    // Mirrors the WhatsApp row: pending, but the AI is on it.
                    'ai'               => $c->status === WebChatConversation::STATUS_BOT,
                    'escalated'        => $c->escalated_at !== null && !$c->isClosed(),
                    'assignee'         => $c->claimer ? ['id' => $c->claimer->id, 'name' => $c->claimer->name] : null,
                    'unread'           => 0,
                    'last_activity_at' => optional($c->last_activity_at ?? $c->created_at)->toISOString(),
                ];
            });
    }

    /**
     * Messenger conversations for the shared inbox list. Mirrors
     * webchatRows() shape so the front-end doesn't need channel-specific
     * branches for rendering.
     */
    private function messengerRows(Request $request, string $tab, string $search)
    {
        $user = $request->user();

        $query = MessengerConversation::withoutGlobalScope('tenant')
            ->where('tenant_id', $user->tenant_id)
            ->with(['latestMessage', 'claimer:id,name', 'page:id,page_name']);

        match ($tab) {
            'mine'   => $query->where('status', MessengerConversation::STATUS_ASSIGNED)->where('claimed_by', $user->id),
            'all'    => $query->where('status', '!=', MessengerConversation::STATUS_CLOSED),
            'closed' => $query->where('status', MessengerConversation::STATUS_CLOSED),
            default  => $query->whereIn('status', [
                MessengerConversation::STATUS_PENDING,
                MessengerConversation::STATUS_BOT,
            ]),
        };

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('contact_name', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(function (MessengerConversation $c) {
                $name = $c->contact_name ?: __('ui.webchat_page.visitor_prefix') . Str::limit($c->uuid, 6, '');

                return [
                    'channel'          => 'messenger',
                    'key'              => 'msg-' . $c->uuid,
                    'ref'              => $c->uuid,
                    'name'             => $name,
                    'subtitle'         => $c->page?->page_name,
                    'initials'         => $this->initials($c->contact_name ?: $c->uuid),
                    'avatar_url'       => $c->contact_avatar_url,
                    'preview'          => $c->title ?: ($c->latestMessage ? Str::limit((string) $c->latestMessage->body, 120) : null),
                    'status'           => $this->messengerStatus($c->status),
                    'ai'               => $c->status === MessengerConversation::STATUS_BOT,
                    'escalated'        => $c->escalated_at !== null && ! $c->isClosed(),
                    'assignee'         => $c->claimer ? ['id' => $c->claimer->id, 'name' => $c->claimer->name] : null,
                    'unread'           => 0,
                    'last_activity_at' => optional($c->last_activity_at ?? $c->created_at)->toISOString(),
                ];
            });
    }

    /**
     * Agents this conversation may be handed to.
     *
     * Eligibility repeats what Api\ConversationController::reassign() enforces
     * on the write side — same tenant, role agent, and a member of the
     * conversation's team when it is team-scoped — so the picker cannot offer a
     * name the endpoint would then reject with a 422. The current assignee is
     * left out: reassigning a thread to whoever already holds it is a no-op.
     */
    public function assignable(Request $request, string $channel, string $ref): JsonResponse
    {
        $user = $request->user();

        if ($channel === 'whatsapp') {
            $c = Conversation::withoutGlobalScope('tenant')->findOrFail((int) $ref);
            abort_unless($user->can('reassign', $c), 403);

            $tenantId  = $c->tenant_id;
            $teamId    = $c->team_id;
            $currentId = $c->owner_agent_id;
        } elseif ($channel === 'messenger') {
            abort_unless($this->canUseMessenger(), 403);

            $c = MessengerConversation::withoutGlobalScope('tenant')
                ->where('tenant_id', $user->tenant_id)
                ->where('uuid', $ref)
                ->firstOrFail();

            abort_unless(
                $c->status === MessengerConversation::STATUS_ASSIGNED
                    && ($user->isAdmin() || $user->isSupervisor() || $user->isSuperAdmin()),
                403,
            );

            $tenantId  = $c->tenant_id;
            $teamId    = null;
            $currentId = $c->claimed_by;
        } else {
            abort_unless($this->canUseWebChat(), 403);

            $c = WebChatConversation::withoutGlobalScope('tenant')
                ->where('tenant_id', $user->tenant_id)
                ->where('uuid', $ref)
                ->firstOrFail();

            abort_unless(
                $c->status === WebChatConversation::STATUS_ASSIGNED
                    && ($user->isAdmin() || $user->isSupervisor() || $user->isSuperAdmin()),
                403,
            );

            $tenantId  = $c->tenant_id;
            $teamId    = null;
            $currentId = $c->claimed_by;
        }

        $agents = User::where('tenant_id', $tenantId)
            ->where('role', 'agent')
            ->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))
            ->when($teamId, fn ($q) => $q->whereHas('teams', fn ($t) => $t->where('teams.id', $teamId)))
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'agents' => $agents->map(fn (User $a) => ['id' => $a->id, 'name' => $a->name])->values(),
        ]);
    }

    /** Per-tab badge counts, one grouped query per channel. */
    private function counts(Request $request, string $channel): array
    {
        $user   = $request->user();
        $counts = array_fill_keys(self::TABS, 0);

        if ($channel === 'all' || $channel === 'whatsapp') {
            $base = Conversation::query();
            $this->scopeWhatsApp($base, $user);

            $byState = (clone $base)->selectRaw('state, count(*) as n')->groupBy('state')->pluck('n', 'state');
            $counts['pending'] += (int) ($byState['pool'] ?? 0);
            $counts['closed']  += (int) ($byState['closed'] ?? 0);
            $counts['all']     += (int) $byState->except('closed')->sum();
            $counts['mine']    += (int) (clone $base)->where('state', 'claimed')->where('owner_agent_id', $user->id)->count();
        }

        if (($channel === 'all' || $channel === 'webchat') && $this->canUseWebChat()) {
            $base = WebChatConversation::withoutGlobalScope('tenant')->where('tenant_id', $user->tenant_id);

            $byStatus = (clone $base)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
            // Same rule as the pending query above — bot chats are pending too.
            $counts['pending'] += (int) ($byStatus[WebChatConversation::STATUS_PENDING] ?? 0)
                                + (int) ($byStatus[WebChatConversation::STATUS_BOT] ?? 0);
            $counts['closed']  += (int) ($byStatus[WebChatConversation::STATUS_CLOSED] ?? 0);
            $counts['all']     += (int) $byStatus->except(WebChatConversation::STATUS_CLOSED)->sum();
            $counts['mine']    += (int) (clone $base)
                ->where('status', WebChatConversation::STATUS_ASSIGNED)
                ->where('claimed_by', $user->id)
                ->count();
        }

        if (($channel === 'all' || $channel === 'messenger') && $this->canUseMessenger()) {
            $base = MessengerConversation::withoutGlobalScope('tenant')->where('tenant_id', $user->tenant_id);

            $byStatus = (clone $base)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
            $counts['pending'] += (int) ($byStatus[MessengerConversation::STATUS_PENDING] ?? 0)
                                + (int) ($byStatus[MessengerConversation::STATUS_BOT] ?? 0);
            $counts['closed']  += (int) ($byStatus[MessengerConversation::STATUS_CLOSED] ?? 0);
            $counts['all']     += (int) $byStatus->except(MessengerConversation::STATUS_CLOSED)->sum();
            $counts['mine']    += (int) (clone $base)
                ->where('status', MessengerConversation::STATUS_ASSIGNED)
                ->where('claimed_by', $user->id)
                ->count();
        }

        // WhatsApp is the ONLY channel we DON'T filter by tenant-owned pages
        // here — its scope is applied inside scopeWhatsApp above. WebChat +
        // Messenger both scope on tenant_id inline.
        if ($channel === 'webchat' || $channel === 'messenger') {
            // Zero out WhatsApp counts we accidentally added above when
            // channel is a specific chat channel other than whatsapp.
            // (Not strictly needed — the "if channel !== whatsapp" gate
            //  above already skipped WhatsApp. Kept as a safety comment.)
        }

        return $counts;
    }

    // ── thread ──────────────────────────────────────────────────────────────

    public function thread(Request $request, string $channel, string $ref): JsonResponse
    {
        return match ($channel) {
            'whatsapp'  => $this->whatsappThread($request, (int) $ref),
            'webchat'   => $this->webchatThread($request, $ref),
            'messenger' => $this->messengerThread($request, $ref),
            default     => abort(404),
        };
    }

    private function whatsappThread(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $query = Conversation::query()->with(['customer', 'ownerAgent:id,name', 'team:id,name', 'instance:id,name']);
        $this->scopeWhatsApp($query, $user);

        $c = $query->findOrFail($id);

        $messages = $c->messages()
            ->latest('id')
            ->limit(200)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Message $m) => [
                'id'         => 'wa-' . $m->id,
                'kind'       => 'text',
                'side'       => $m->direction === 'in' ? 'in' : 'out',
                'who'        => $m->author_type === 'customer'
                                    ? ($c->customer?->display_name ?: $c->customer?->phone_e164)
                                    : ($m->author_type === 'agent' ? ($c->ownerAgent?->name ?? __('ui.inbox_page.agent')) : __('ui.inbox_page.ai')),
                'body'       => $m->body,
                'created_at' => optional($m->sent_at ?? $m->created_at)->toISOString(),
                // Without this the UI cannot tell a delivered message from one
                // still sitting in the queue, so a failed send looks like a sent one.
                'status'     => $m->status,
                // Attachments were stored and sent but never returned here, so an
                // uploaded file left an empty bubble in the thread. `file_name`
                // predates media_filename on some rows — fall back to it.
                'media'      => $m->media_url ? [
                    'url'  => $m->media_url,
                    'type' => $m->type,
                    'name' => $m->media_filename ?: ($m->ai_metadata['file_name'] ?? null),
                    'mime' => $m->media_mime,
                    // Inbound WhatsApp media is an AES-encrypted CDN blob
                    // (mmg.whatsapp.net/….enc) that no <img>/<audio> can decode.
                    // Only files this app stored itself are renderable inline;
                    // the rest get a labelled chip instead of a broken element.
                    'inline' => $this->isLocalMedia($m->media_url),
                ] : null,
            ]);

        $name = $c->customer?->display_name ?: ($c->customer?->phone_e164 ?: __('ui.inbox_page.unknown_contact'));

        // Why the AI is or isn't answering this particular thread. `mode` and the
        // WhatsApp switch are tenant-wide; a claim or a manual suspend stops the
        // AI on this conversation alone, which is otherwise invisible to agents.
        $ai       = $c->tenant?->aiSettings;
        $aiOnHere = (bool) $ai?->enabledFor('whatsapp');
        $aiReason = null;
        if ($aiOnHere && !$c->isAiEligible()) {
            $aiReason = $c->state === 'claimed' ? 'claimed' : ($c->ai_suspended ? 'suspended' : null);
        }

        return response()->json([
            'channel' => 'whatsapp',
            'ref'     => (string) $c->id,
            // The live thread subscribes to tenant.{tenant}.conversation.{id};
            // take the tenant from the conversation rather than the viewer, who
            // may be a super admin with no tenant_id of their own.
            'tenant_id' => $c->tenant_id,
            'header'  => [
                'name'     => $name,
                'subtitle' => $c->customer?->phone_e164,
                'initials' => $this->initials($name),
                'status'   => $this->whatsappStatus($c->state),
                'escalated'=> $c->escalated_at !== null && !$c->isClosed(),
                'assignee' => $c->ownerAgent ? ['id' => $c->ownerAgent->id, 'name' => $c->ownerAgent->name] : null,
            ],
            // PROC-023: derive from ConversationPolicy so the buttons match what
            // the endpoints actually authorize. Restating the rules here drifted
            // — Release was granted to the owning agent but the policy denied
            // it, and Close was granted on pooled threads but the policy required
            // isClaimed(). Any future policy change is picked up here for free.
            'can' => [
                'claim'   => $user->can('claim', $c),
                'reply'   => $user->can('reply', $c),
                'release' => $user->can('release', $c),
                'close'   => $user->can('close', $c),
                'reassign'=> $user->can('reassign', $c),
            ],
            'ai' => [
                'applies'   => $aiOnHere,
                'suspended' => (bool) $c->ai_suspended,
                'eligible'  => $aiOnHere && $c->isAiEligible(),
                'reason'    => $aiReason,
                'can_toggle'=> $aiOnHere && $c->state !== 'closed',
            ],
            'info' => array_values(array_filter([
                $this->kv(__('ui.inbox_page.phone'), $c->customer?->phone_e164),
                $this->kv(__('ui.inbox_page.instance'), $c->instance?->name),
                $this->kv(__('ui.teams_page.team'), $c->team?->name),
                $this->kv(__('ui.inbox_page.started'), optional($c->created_at)->toDateTimeString()),
            ])),
            'messages' => $messages,
        ]);
    }

    private function webchatThread(Request $request, string $uuid): JsonResponse
    {
        abort_unless($this->canUseWebChat(), 403);

        $user = $request->user();

        $c = WebChatConversation::withoutGlobalScope('tenant')
            ->where('tenant_id', $user->tenant_id)
            ->with(['visitor', 'claimer:id,name'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $name = $c->visitor_name ?: __('ui.webchat_page.visitor_prefix') . Str::limit($c->uuid, 6, '');

        $messages = $c->messages()
            ->with('sender:id,name')
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->map(fn (WebChatMessage $m) => [
                'id'         => 'lc-' . $m->id,
                'kind'       => $m->sender_type === 'system' ? 'system' : 'text',
                'side'       => $m->sender_type === 'visitor' ? 'in' : 'out',
                'who'        => $m->sender_type === 'visitor'
                                    ? $name
                                    : ($m->sender?->name ?? ($m->sender_type === 'bot' ? __('ui.inbox_page.ai') : __('ui.inbox_page.agent'))),
                'body'       => $m->body,
                'created_at' => $m->created_at?->toISOString(),
                'status'     => 'sent',
                // Live-chat attachments live on the message's meta; they are
                // always files we host, so they render inline.
                'media'      => ($a = $m->meta['attachment'] ?? null) ? [
                    'url'    => $a['url'] ?? null,
                    'type'   => $a['type'] ?? 'document',
                    'name'   => $a['name'] ?? null,
                    'mime'   => null,
                    'inline' => $this->isLocalMedia($a['url'] ?? null),
                ] : null,
            ]);

        $isMine = $c->status === WebChatConversation::STATUS_ASSIGNED && (int) $c->claimed_by === (int) $user->id;

        return response()->json([
            'channel' => 'webchat',
            'ref'     => $c->uuid,
            'header'  => [
                'name'     => $name,
                'subtitle' => $c->visitor_email,
                'initials' => $this->initials($c->visitor_name ?: $c->uuid),
                'status'   => $this->webchatStatus($c->status),
                'escalated'=> $c->escalated_at !== null && !$c->isClosed(),
                'assignee' => $c->claimer ? ['id' => $c->claimer->id, 'name' => $c->claimer->name] : null,
            ],
            'can' => [
                'claim'   => in_array($c->status, [WebChatConversation::STATUS_PENDING, 'bot'], true),
                'reply'   => $isMine,
                'release' => $isMine,
                'close'   => $c->status !== WebChatConversation::STATUS_CLOSED && ($isMine || $user->isAdmin()),
                // Handing a live thread to a different agent is a supervisory
                // act, not the owner's — the owner releases instead. Mirrors
                // ConversationPolicy::reassign, minus the team check webchat
                // conversations have no column for.
                'reassign'=> $c->status === WebChatConversation::STATUS_ASSIGNED
                    && ($user->isAdmin() || $user->isSupervisor() || $user->isSuperAdmin()),
            ],
            'ai' => [
                'applies'    => (bool) $user->tenant?->aiSettings?->enabledFor('webchat'),
                'suspended'  => false,
                'eligible'   => (bool) $user->tenant?->aiSettings?->enabledFor('webchat'),
                'reason'     => null,
                'can_toggle' => false,
            ],
            'info' => array_values(array_filter([
                $this->kv(__('ui.webchat_page.email'), $c->visitor_email),
                $this->kv(__('ui.webchat_page.page'), $c->page_url),
                $this->kv(__('ui.webchat_page.referrer'), $c->referrer),
                $this->kv(__('ui.webchat_page.browser'), $c->user_agent),
                $this->kv(__('ui.webchat_page.ip'), $c->ip),
                $this->kv(__('ui.inbox_page.started'), optional($c->created_at)->toDateTimeString()),
            ])),
            'messages' => $messages,
        ]);
    }

    private function messengerThread(Request $request, string $uuid): JsonResponse
    {
        abort_unless($this->canUseMessenger(), 403);

        $user = $request->user();

        $c = MessengerConversation::withoutGlobalScope('tenant')
            ->where('tenant_id', $user->tenant_id)
            ->with(['page:id,page_name', 'claimer:id,name'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $name = $c->contact_name ?: __('ui.webchat_page.visitor_prefix') . Str::limit($c->uuid, 6, '');

        $messages = $c->messages()
            ->with('sender:id,name')
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->map(fn (MessengerMessage $m) => [
                'id'         => 'msg-' . $m->id,
                'kind'       => $m->sender_type === 'system' ? 'system' : 'text',
                'side'       => $m->sender_type === 'visitor' ? 'in' : 'out',
                'who'        => $m->sender_type === 'visitor'
                                    ? $name
                                    : ($m->sender?->name ?? ($m->sender_type === 'bot' ? __('ui.inbox_page.ai') : __('ui.inbox_page.agent'))),
                'body'       => $m->body,
                'created_at' => $m->created_at?->toISOString(),
                'status'     => 'sent',
                'media'      => null,
            ]);

        $isMine = $c->status === MessengerConversation::STATUS_ASSIGNED
            && (int) $c->claimed_by === (int) $user->id;

        return response()->json([
            'channel' => 'messenger',
            'ref'     => $c->uuid,
            'header'  => [
                'name'      => $name,
                'subtitle'  => $c->page?->page_name,
                'initials'  => $this->initials($c->contact_name ?: $c->uuid),
                'avatar_url'=> $c->contact_avatar_url,
                'status'    => $this->messengerStatus($c->status),
                'escalated' => $c->escalated_at !== null && ! $c->isClosed(),
                'assignee'  => $c->claimer ? ['id' => $c->claimer->id, 'name' => $c->claimer->name] : null,
            ],
            'can' => [
                'claim'   => in_array($c->status, [MessengerConversation::STATUS_PENDING, MessengerConversation::STATUS_BOT], true),
                // Reply also gated on the 24h Meta window — outside that,
                // sends require a MESSAGE_TAG we don't yet support.
                'reply'   => $isMine && $c->withinMessagingWindow(),
                'release' => $isMine,
                'close'   => $c->status !== MessengerConversation::STATUS_CLOSED && ($isMine || $user->isAdmin()),
                'reassign'=> $c->status === MessengerConversation::STATUS_ASSIGNED
                    && ($user->isAdmin() || $user->isSupervisor() || $user->isSuperAdmin()),
            ],
            'ai' => [
                'applies'    => (bool) $user->tenant?->aiSettings?->enabledFor('messenger'),
                'suspended'  => false,
                'eligible'   => (bool) $user->tenant?->aiSettings?->enabledFor('messenger'),
                'reason'     => null,
                'can_toggle' => false,
            ],
            'info' => array_values(array_filter([
                $this->kv(__('ui.inbox_page.started'), optional($c->created_at)->toDateTimeString()),
                $this->kv('Page', $c->page?->page_name),
                $this->kv('PSID', $c->psid),
                $c->last_inbound_at ? $this->kv('Last inbound', $c->last_inbound_at->toDateTimeString() . ($c->withinMessagingWindow() ? '' : ' (outside 24h window)')) : null,
            ])),
            'messages' => $messages,
        ]);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    /** Mirrors the scoping in Api\ConversationController@index. */
    private function scopeWhatsApp($query, $user): void
    {
        if ($user->isAgent() || $user->isSupervisor()) {
            $teamIds = $user->teams->pluck('id');
            $query->where(function ($q) use ($teamIds) {
                $q->whereIn('team_id', $teamIds)->orWhereNull('team_id');
            })->where('tenant_id', $user->tenant_id);
        } elseif ($user->isAdmin()) {
            $query->where('tenant_id', $user->tenant_id);
        }
    }

    /** Live Chat is not available to the platform owner — they aren't frontline. */
    private function canUseWebChat(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'supervisor', 'agent']) ?? false;
    }

    /**
     * Same role rule as WebChat: super-admins are not frontline agents.
     * ADDITIONALLY requires the plan to grant the messenger module —
     * unlike WebChat, whose action routes are not module-gated. Without
     * this check the unified inbox showed the Messenger tab + listed
     * conversations for tenants whose plan doesn't include messenger,
     * then every claim / reply / close 403'd on CheckPlanModule. The
     * 2026-09-17 launch's second debug round chased exactly this
     * mismatch — hide the channel here so the button never appears
     * unless the actions will actually work.
     */
    private function canUseMessenger(): bool
    {
        $user = auth()->user();
        if (! $user?->hasAnyRole(['admin', 'supervisor', 'agent'])) {
            return false;
        }
        return (bool) $user->tenant?->planAllows('messenger');
    }

    /** Conversation ids (of those given) that already carry an AI reply. */
    private function aiAnsweredIds(array $ids): array
    {
        if (!$ids) {
            return [];
        }

        return Message::whereIn('conversation_id', $ids)
            ->where('author_type', 'ai')
            ->distinct()
            ->pluck('conversation_id')
            ->flip()
            ->all();
    }

    private function whatsappStatus(?string $state): string
    {
        return match ($state) {
            'pool'    => 'pending',
            'claimed' => 'assigned',
            'closed'  => 'closed',
            default   => 'pending',
        };
    }

    /**
     * Webchat status as a workflow state, the same vocabulary whatsappStatus()
     * speaks.
     *
     * 'bot' used to map to its own 'ai' state, which rendered an "AI" status
     * badge — while the row's separate `ai` flag rendered a second, identical
     * "AI" badge right beside it. A bot-held chat is waiting for a human just
     * like a pooled WhatsApp thread, so it reads 'pending' here and lets the
     * `ai` flag be the single thing that says the AI is on it.
     */
    private function webchatStatus(?string $status): string
    {
        return match ($status) {
            WebChatConversation::STATUS_BOT      => 'pending',
            WebChatConversation::STATUS_ASSIGNED => 'assigned',
            WebChatConversation::STATUS_CLOSED   => 'closed',
            default                              => 'pending',
        };
    }

    /** Messenger status → the same workflow vocabulary the other two speak. */
    private function messengerStatus(?string $status): string
    {
        return match ($status) {
            MessengerConversation::STATUS_BOT      => 'pending',
            MessengerConversation::STATUS_ASSIGNED => 'assigned',
            MessengerConversation::STATUS_CLOSED   => 'closed',
            default                                => 'pending',
        };
    }

    private function kv(string $label, $value): ?array
    {
        $value = trim((string) $value);
        return $value === '' ? null : ['k' => $label, 'v' => $value];
    }

    /**
     * Can the browser render this media directly?
     *
     * True only for files served from this app (agent uploads land on the public
     * disk). Inbound WhatsApp media points at mmg.whatsapp.net and is encrypted,
     * so it has to be shown as a chip rather than an <img>/<audio>/<video>.
     */
    private function isLocalMedia(?string $url): bool
    {
        if (!$url) return false;

        $host = parse_url($url, PHP_URL_HOST);
        if ($host === null) return true; // relative path — served by us

        return strcasecmp($host, (string) parse_url((string) config('app.url'), PHP_URL_HOST)) === 0;
    }

    private function initials(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '·';
        }

        $parts = preg_split('/\s+/', $name);
        $a = mb_substr($parts[0] ?? '', 0, 1);
        $b = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';

        return mb_strtoupper($a . $b) ?: '·';
    }
}
