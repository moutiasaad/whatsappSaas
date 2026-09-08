<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WebChat\Conversation as WebChatConversation;
use App\Models\WebChat\Message as WebChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Unified inbox — WhatsApp and Live Chat conversations in one list.
 *
 * Read paths (list + thread) are normalised here so the page renders one shape
 * regardless of channel. Write paths (claim / release / close / reply) stay on
 * each channel's own endpoints, so their policies, events and broadcasts are
 * untouched — the page just picks the right URL from the row's `channel`.
 */
class InboxController extends Controller
{
    public const CHANNELS = ['all', 'whatsapp', 'webchat'];
    public const TABS     = ['pending', 'mine', 'all', 'closed'];

    private const LIMIT = 150;

    public function index()
    {
        return view('admin.inbox.index', [
            'canUseWebChat' => $this->canUseWebChat(),
        ]);
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

        if ($channel !== 'webchat') {
            $rows = $rows->merge($this->whatsappRows($request, $tab, $search));
        }

        if ($channel !== 'whatsapp' && $this->canUseWebChat()) {
            $rows = $rows->merge($this->webchatRows($request, $tab, $search));
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
            default  => $query->where('status', WebChatConversation::STATUS_PENDING),
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
                    'status'           => $c->status === 'bot' ? 'ai' : $c->status,
                    'ai'               => false,
                    'assignee'         => $c->claimer ? ['id' => $c->claimer->id, 'name' => $c->claimer->name] : null,
                    'unread'           => 0,
                    'last_activity_at' => optional($c->last_activity_at ?? $c->created_at)->toISOString(),
                ];
            });
    }

    /** Per-tab badge counts, one grouped query per channel. */
    private function counts(Request $request, string $channel): array
    {
        $user   = $request->user();
        $counts = array_fill_keys(self::TABS, 0);

        if ($channel !== 'webchat') {
            $base = Conversation::query();
            $this->scopeWhatsApp($base, $user);

            $byState = (clone $base)->selectRaw('state, count(*) as n')->groupBy('state')->pluck('n', 'state');
            $counts['pending'] += (int) ($byState['pool'] ?? 0);
            $counts['closed']  += (int) ($byState['closed'] ?? 0);
            $counts['all']     += (int) $byState->except('closed')->sum();
            $counts['mine']    += (int) (clone $base)->where('state', 'claimed')->where('owner_agent_id', $user->id)->count();
        }

        if ($channel !== 'whatsapp' && $this->canUseWebChat()) {
            $base = WebChatConversation::withoutGlobalScope('tenant')->where('tenant_id', $user->tenant_id);

            $byStatus = (clone $base)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
            $counts['pending'] += (int) ($byStatus[WebChatConversation::STATUS_PENDING] ?? 0);
            $counts['closed']  += (int) ($byStatus[WebChatConversation::STATUS_CLOSED] ?? 0);
            $counts['all']     += (int) $byStatus->except(WebChatConversation::STATUS_CLOSED)->sum();
            $counts['mine']    += (int) (clone $base)
                ->where('status', WebChatConversation::STATUS_ASSIGNED)
                ->where('claimed_by', $user->id)
                ->count();
        }

        return $counts;
    }

    // ── thread ──────────────────────────────────────────────────────────────

    public function thread(Request $request, string $channel, string $ref): JsonResponse
    {
        return match ($channel) {
            'whatsapp' => $this->whatsappThread($request, (int) $ref),
            'webchat'  => $this->webchatThread($request, $ref),
            default    => abort(404),
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
            ]);

        $isMine = $c->state === 'claimed' && (int) $c->owner_agent_id === (int) $user->id;
        $name   = $c->customer?->display_name ?: ($c->customer?->phone_e164 ?: __('ui.inbox_page.unknown_contact'));

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
            'header'  => [
                'name'     => $name,
                'subtitle' => $c->customer?->phone_e164,
                'initials' => $this->initials($name),
                'status'   => $this->whatsappStatus($c->state),
                'assignee' => $c->ownerAgent ? ['id' => $c->ownerAgent->id, 'name' => $c->ownerAgent->name] : null,
            ],
            'can' => [
                'claim'   => $c->state === 'pool',
                'reply'   => $isMine,
                'release' => $isMine,
                'close'   => $c->state !== 'closed' && ($isMine || $user->isAdmin() || $user->isSupervisor()),
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
            ]);

        $isMine = $c->status === WebChatConversation::STATUS_ASSIGNED && (int) $c->claimed_by === (int) $user->id;

        return response()->json([
            'channel' => 'webchat',
            'ref'     => $c->uuid,
            'header'  => [
                'name'     => $name,
                'subtitle' => $c->visitor_email,
                'initials' => $this->initials($c->visitor_name ?: $c->uuid),
                'status'   => $c->status === 'bot' ? 'ai' : $c->status,
                'assignee' => $c->claimer ? ['id' => $c->claimer->id, 'name' => $c->claimer->name] : null,
            ],
            'can' => [
                'claim'   => in_array($c->status, [WebChatConversation::STATUS_PENDING, 'bot'], true),
                'reply'   => $isMine,
                'release' => $isMine,
                'close'   => $c->status !== WebChatConversation::STATUS_CLOSED && ($isMine || $user->isAdmin()),
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

    private function kv(string $label, $value): ?array
    {
        $value = trim((string) $value);
        return $value === '' ? null : ['k' => $label, 'v' => $value];
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
