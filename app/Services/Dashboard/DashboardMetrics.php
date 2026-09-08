<?php

namespace App\Services\Dashboard;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\User;
use App\Models\WebChat\Conversation as WebChatConversation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything the tenant dashboard shows, computed from real rows.
 *
 * Both channels are folded together: WhatsApp lives in `conversations` /
 * `messages`, Live Chat in `webchat_conversations` / `webchat_messages`. The
 * two schemas differ, so each metric normalises them separately and then adds
 * the results.
 */
class DashboardMetrics
{
    public function __construct(
        private int $tenantId,
        private int $days = 14,
    ) {}

    private function from(): Carbon
    {
        return today()->subDays($this->days - 1)->startOfDay();
    }

    /** Day-by-day conversation counts per channel, zero-filled. */
    public function series(): array
    {
        $from = $this->from();

        $wa = Conversation::where('tenant_id', $this->tenantId)
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) d, COUNT(*) n')
            ->groupBy('d')->pluck('n', 'd');

        $lc = WebChatConversation::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantId)
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) d, COUNT(*) n')
            ->groupBy('d')->pluck('n', 'd');

        $resp = $this->firstReplyMinutesByDay();

        $out = [];
        for ($i = 0; $i < $this->days; $i++) {
            $day = $from->copy()->addDays($i);
            $key = $day->toDateString();
            $out[] = [
                'date'  => $key,
                'label' => $day->format('j'),
                'full'  => $day->format('j M'),
                'wa'    => (int) ($wa[$key] ?? 0),
                'lc'    => (int) ($lc[$key] ?? 0),
                'resp'  => $resp[$key] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Per-conversation first-response time in minutes: from the first inbound
     * message to the first human reply. Returned as a median per day.
     */
    private function firstReplyMinutesByDay(): array
    {
        $from = $this->from();

        $rows = collect();

        $wa = DB::table('messages')
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->where('conversations.tenant_id', $this->tenantId)
            ->where('conversations.created_at', '>=', $from)
            ->groupBy('messages.conversation_id')
            ->selectRaw("
                messages.conversation_id,
                MIN(CASE WHEN messages.direction = 'in' THEN messages.created_at END) first_in,
                MIN(CASE WHEN messages.direction = 'out' AND messages.author_type = 'agent' THEN messages.created_at END) first_out
            ")->get();

        $lc = DB::table('webchat_messages')
            ->join('webchat_conversations', 'webchat_conversations.id', '=', 'webchat_messages.conversation_id')
            ->where('webchat_conversations.tenant_id', $this->tenantId)
            ->where('webchat_conversations.created_at', '>=', $from)
            ->groupBy('webchat_messages.conversation_id')
            ->selectRaw("
                webchat_messages.conversation_id,
                MIN(CASE WHEN webchat_messages.sender_type = 'visitor' THEN webchat_messages.created_at END) first_in,
                MIN(CASE WHEN webchat_messages.sender_type = 'agent' THEN webchat_messages.created_at END) first_out
            ")->get();

        foreach ($wa->concat($lc) as $r) {
            if (!$r->first_in || !$r->first_out) {
                continue;
            }
            $in  = Carbon::parse($r->first_in);
            $out = Carbon::parse($r->first_out);
            if ($out->lt($in)) {
                continue;
            }
            $rows->push(['day' => $in->toDateString(), 'min' => $in->diffInSeconds($out) / 60]);
        }

        return $rows->groupBy('day')
            ->map(fn (Collection $g) => round($this->median($g->pluck('min')->all()), 1))
            ->all();
    }

    /** Median first reply across the whole window, and the window before it. */
    public function firstReply(): array
    {
        $byDay   = $this->firstReplyMinutesByDay();
        $current = array_values($byDay);

        $half = (int) floor($this->days / 2);
        $keys = array_keys($byDay);
        sort($keys);
        $prevKeys = array_slice($keys, 0, $half);
        $curKeys  = array_slice($keys, $half);

        $prev = array_values(array_intersect_key($byDay, array_flip($prevKeys)));
        $cur  = array_values(array_intersect_key($byDay, array_flip($curKeys)));

        return [
            'median'   => $current ? round($this->median($current), 1) : null,
            'previous' => $prev ? round($this->median($prev), 1) : null,
            'current'  => $cur ? round($this->median($cur), 1) : null,
        ];
    }

    /**
     * How much the AI carried: conversations where only the AI replied, where
     * the AI replied and a human followed, and where a human handled it alone.
     */
    public function aiBreakdown(): array
    {
        $from = $this->from();

        $wa = DB::table('messages')
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->where('conversations.tenant_id', $this->tenantId)
            ->where('conversations.created_at', '>=', $from)
            ->groupBy('messages.conversation_id')
            ->selectRaw("
                SUM(CASE WHEN messages.author_type = 'ai' THEN 1 ELSE 0 END) ai_n,
                SUM(CASE WHEN messages.direction = 'out' AND messages.author_type = 'agent' THEN 1 ELSE 0 END) agent_n
            ")->get();

        $lc = DB::table('webchat_messages')
            ->join('webchat_conversations', 'webchat_conversations.id', '=', 'webchat_messages.conversation_id')
            ->where('webchat_conversations.tenant_id', $this->tenantId)
            ->where('webchat_conversations.created_at', '>=', $from)
            ->groupBy('webchat_messages.conversation_id')
            ->selectRaw("
                SUM(CASE WHEN webchat_messages.sender_type = 'bot' THEN 1 ELSE 0 END) ai_n,
                SUM(CASE WHEN webchat_messages.sender_type = 'agent' THEN 1 ELSE 0 END) agent_n
            ")->get();

        $aiOnly = $both = $agentOnly = 0;
        foreach ($wa->concat($lc) as $r) {
            $hasAi    = (int) $r->ai_n > 0;
            $hasAgent = (int) $r->agent_n > 0;
            if ($hasAi && !$hasAgent)      $aiOnly++;
            elseif ($hasAi && $hasAgent)   $both++;
            elseif ($hasAgent)             $agentOnly++;
        }

        $total = $aiOnly + $both + $agentOnly;

        return [
            'ai_only'    => $aiOnly,
            'both'       => $both,
            'agent_only' => $agentOnly,
            'total'      => $total,
            'ai_pct'     => $total ? (int) round($aiOnly / $total * 100) : 0,
            'ai_median_seconds' => $this->aiReplySeconds(),
        ];
    }

    /** Median seconds between a visitor message and the AI's answer (Live Chat). */
    private function aiReplySeconds(): ?float
    {
        $rows = DB::table('webchat_messages as m')
            ->join('webchat_conversations as c', 'c.id', '=', 'm.conversation_id')
            ->where('c.tenant_id', $this->tenantId)
            ->where('m.created_at', '>=', $this->from())
            ->where('m.sender_type', 'bot')
            ->selectRaw('m.conversation_id, m.created_at bot_at, (
                SELECT MAX(p.created_at) FROM webchat_messages p
                WHERE p.conversation_id = m.conversation_id
                  AND p.sender_type = "visitor" AND p.created_at <= m.created_at
            ) prev_at')
            ->get();

        $deltas = [];
        foreach ($rows as $r) {
            if (!$r->prev_at) {
                continue;
            }
            $d = Carbon::parse($r->prev_at)->diffInSeconds(Carbon::parse($r->bot_at));
            if ($d >= 0 && $d < 3600) {
                $deltas[] = $d;
            }
        }

        return $deltas ? round($this->median($deltas), 1) : null;
    }

    /** Conversations per weekday × hour, normalised 0..1 for the heatmap. */
    public function heatmap(): array
    {
        $from = $this->from();

        $grab = fn (string $table) => DB::table($table)
            ->where('tenant_id', $this->tenantId)
            ->where('created_at', '>=', $from)
            ->selectRaw('DAYOFWEEK(created_at) dw, HOUR(created_at) h, COUNT(*) n')
            ->groupBy('dw', 'h')
            ->get();

        $grid = [];
        foreach ($grab('conversations')->concat($grab('webchat_conversations')) as $r) {
            // MySQL DAYOFWEEK: 1 = Sunday. Shift so 0 = Monday.
            $dw = ((int) $r->dw + 5) % 7;
            $grid[$dw][(int) $r->h] = ($grid[$dw][(int) $r->h] ?? 0) + (int) $r->n;
        }

        $max  = 0;
        $peak = ['hour' => null, 'n' => 0];
        foreach ($grid as $dw => $hours) {
            foreach ($hours as $h => $n) {
                $max = max($max, $n);
            }
        }

        $byHour = [];
        foreach ($grid as $hours) {
            foreach ($hours as $h => $n) {
                $byHour[$h] = ($byHour[$h] ?? 0) + $n;
            }
        }
        if ($byHour) {
            arsort($byHour);
            $peak = ['hour' => (int) array_key_first($byHour), 'n' => reset($byHour)];
        }

        return ['grid' => $grid, 'max' => $max, 'peak' => $peak];
    }

    /** Open load per agent, plus what they closed today. */
    public function agents(): Collection
    {
        $users = User::where('tenant_id', $this->tenantId)
            ->whereIn('role', ['agent', 'supervisor'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        if ($users->isEmpty()) {
            return collect();
        }

        $open = Conversation::where('tenant_id', $this->tenantId)
            ->where('state', 'claimed')
            ->whereIn('owner_agent_id', $users->pluck('id'))
            ->selectRaw('owner_agent_id, COUNT(*) n')
            ->groupBy('owner_agent_id')->pluck('n', 'owner_agent_id');

        $lcOpen = WebChatConversation::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantId)
            ->where('status', WebChatConversation::STATUS_ASSIGNED)
            ->whereIn('claimed_by', $users->pluck('id'))
            ->selectRaw('claimed_by, COUNT(*) n')
            ->groupBy('claimed_by')->pluck('n', 'claimed_by');

        $closedToday = Conversation::where('tenant_id', $this->tenantId)
            ->whereDate('closed_at', today())
            ->whereIn('owner_agent_id', $users->pluck('id'))
            ->selectRaw('owner_agent_id, COUNT(*) n')
            ->groupBy('owner_agent_id')->pluck('n', 'owner_agent_id');

        $cap = 10;

        return $users->map(fn (User $u) => [
            'id'      => $u->id,
            'name'    => $u->name,
            'role'    => $u->role,
            'open'    => (int) ($open[$u->id] ?? 0) + (int) ($lcOpen[$u->id] ?? 0),
            'cap'     => $cap,
            'today'   => (int) ($closedToday[$u->id] ?? 0),
        ])->sortByDesc('open')->values();
    }

    /** Unclaimed conversations from both channels, longest wait first. */
    public function needsAttention(int $limit = 6): Collection
    {
        $wa = Conversation::with(['customer:id,display_name,phone_e164', 'ownerAgent:id,name'])
            ->where('tenant_id', $this->tenantId)
            ->whereIn('state', ['pool', 'claimed'])
            ->orderByDesc('last_message_at')
            ->limit($limit * 2)
            ->get()
            ->map(fn (Conversation $c) => [
                'channel'  => 'whatsapp',
                'ref'      => (string) $c->id,
                'name'     => $c->customer?->display_name ?: ($c->customer?->phone_e164 ?: __('ui.inbox_page.unknown_contact')),
                'sub'      => $c->customer?->phone_e164,
                'state'    => $c->state === 'pool' ? 'pool' : ($c->ai_suspended ? 'claimed' : 'claimed'),
                'agent'    => $c->ownerAgent?->name,
                'since'    => optional($c->last_message_at)->toISOString(),
                'waiting'  => $c->state === 'pool',
            ]);

        $lc = WebChatConversation::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantId)
            ->whereIn('status', [WebChatConversation::STATUS_PENDING, WebChatConversation::STATUS_ASSIGNED, 'bot'])
            ->with('claimer:id,name')
            ->orderByDesc('last_activity_at')
            ->limit($limit * 2)
            ->get()
            ->map(fn (WebChatConversation $c) => [
                'channel'  => 'webchat',
                'ref'      => $c->uuid,
                'name'     => $c->visitor_name ?: __('ui.webchat_page.visitor_prefix') . substr($c->uuid, 0, 6),
                'sub'      => $c->visitor_email ?: $c->page_url,
                'state'    => $c->status === WebChatConversation::STATUS_PENDING ? 'pool' : ($c->status === 'bot' ? 'ai' : 'claimed'),
                'agent'    => $c->status === 'bot' ? __('ui.inbox_page.ai') : $c->claimer?->name,
                'since'    => optional($c->last_activity_at)->toISOString(),
                'waiting'  => $c->status === WebChatConversation::STATUS_PENDING,
            ]);

        return $wa->concat($lc)
            ->sortBy(fn ($r) => [$r['waiting'] ? 0 : 1, $r['since'] ?? ''])
            ->values()
            ->take($limit);
    }

    /** Share of customers who came back for a second conversation. */
    public function repeatCustomerPct(): int
    {
        $total = Customer::where('tenant_id', $this->tenantId)->count();
        if ($total === 0) {
            return 0;
        }

        $repeat = Conversation::where('tenant_id', $this->tenantId)
            ->whereNotNull('customer_id')
            ->selectRaw('customer_id, COUNT(*) n')
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        return (int) round($repeat / $total * 100);
    }

    /** Live pool depth and how long the oldest one has been waiting. */
    public function pool(): array
    {
        $waCount = Conversation::where('tenant_id', $this->tenantId)->where('state', 'pool')->count();
        $lcCount = WebChatConversation::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantId)
            ->where('status', WebChatConversation::STATUS_PENDING)
            ->count();

        $oldestWa = Conversation::where('tenant_id', $this->tenantId)->where('state', 'pool')->min('last_message_at');
        $oldestLc = WebChatConversation::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantId)
            ->where('status', WebChatConversation::STATUS_PENDING)
            ->min('last_activity_at');

        $oldest = collect([$oldestWa, $oldestLc])->filter()->map(fn ($d) => Carbon::parse($d))->min();

        return [
            'count'  => $waCount + $lcCount,
            'oldest' => $oldest?->toISOString(),
        ];
    }

    private function median(array $values): float
    {
        if (!$values) {
            return 0.0;
        }
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 ? (float) $values[$mid] : (($values[$mid - 1] + $values[$mid]) / 2);
    }
}
