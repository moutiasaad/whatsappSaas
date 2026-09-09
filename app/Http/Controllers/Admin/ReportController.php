<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.index', [
            'panelPrefix' => auth()->user()->routeNamePrefix(),
        ]);
    }

    public function data(Request $request)
    {
        $actor    = auth()->user();
        $period   = (int) $request->input('period', 30);
        $period   = in_array($period, [7, 30, 90]) ? $period : 30;
        $from     = now()->subDays($period)->startOfDay();
        $to       = now()->endOfDay();
        $tenantId = $actor->isSuperAdmin() ? null : $actor->tenant_id;

        return response()->json([
            'kpi'               => $this->kpi($tenantId, $from, $to),
            'team_leaderboard'  => $this->teamLeaderboard($tenantId, $from, $to),
            'agent_leaderboard' => $this->agentLeaderboard($tenantId, $from, $to),
            'daily_volume'      => $this->dailyVolume($tenantId, $from, $to, $period),
            'hourly_heatmap'    => $this->hourlyHeatmap($tenantId, $from, $to),
            'state_breakdown'   => $this->stateBreakdown($tenantId, $from, $to),
            'ai_vs_agent'       => $this->aiVsAgent($tenantId, $from, $to),
        ]);
    }

    // ── KPI ──────────────────────────────────────────────────────────────────

    private function kpi(?int $tenantId, $from, $to): array
    {
        $base = fn() => DB::table('conversations')
            ->whereBetween('created_at', [$from, $to])
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        $total  = $base()->count();
        $closed = $base()->where('state', 'closed')->count();

        $avgResponse = $base()
            ->whereNotNull('claimed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, claimed_at)) as v')
            ->value('v');

        $avgResolution = $base()
            ->where('state', 'closed')
            ->whereNotNull('closed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, closed_at)) as v')
            ->value('v');

        $msgs = fn($dir) => DB::table('messages')
            ->whereBetween('sent_at', [$from, $to])
            ->where('direction', $dir)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->count();

        return [
            'total_conversations'    => $total,
            'closed_conversations'   => $closed,
            'resolution_rate'        => $total > 0 ? round($closed / $total * 100, 1) : 0,
            'avg_first_response_min' => $avgResponse !== null ? (int) round($avgResponse) : null,
            'avg_resolution_min'     => $avgResolution !== null ? (int) round($avgResolution) : null,
            'inbound_messages'       => $msgs('in'),
            'outbound_messages'      => $msgs('out'),
        ];
    }

    // ── TEAM LEADERBOARD ─────────────────────────────────────────────────────

    private function teamLeaderboard(?int $tenantId, $from, $to): array
    {
        return DB::table('conversations')
            ->join('teams', 'conversations.team_id', '=', 'teams.id')
            ->whereBetween('conversations.created_at', [$from, $to])
            ->whereNotNull('conversations.team_id')
            ->when($tenantId, fn($q) => $q->where('conversations.tenant_id', $tenantId))
            ->selectRaw('
                teams.id,
                teams.name,
                COUNT(conversations.id)                                                                            AS total,
                SUM(CASE WHEN conversations.state = "closed" THEN 1 ELSE 0 END)                                   AS closed,
                ROUND(AVG(TIMESTAMPDIFF(MINUTE, conversations.created_at, conversations.claimed_at)), 0)           AS avg_response_min,
                ROUND(AVG(CASE WHEN conversations.state = "closed"
                               THEN TIMESTAMPDIFF(MINUTE, conversations.created_at, conversations.closed_at)
                               ELSE NULL END), 0)                                                                  AS avg_resolution_min
            ')
            ->groupBy('teams.id', 'teams.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => [
                'name'               => $r->name,
                'total'              => (int) $r->total,
                'closed'             => (int) $r->closed,
                'resolution_rate'    => $r->total > 0 ? round($r->closed / $r->total * 100) : 0,
                'avg_response_min'   => $r->avg_response_min !== null ? (int) $r->avg_response_min : null,
                'avg_resolution_min' => $r->avg_resolution_min !== null ? (int) $r->avg_resolution_min : null,
            ])
            ->toArray();
    }

    // ── AGENT LEADERBOARD ────────────────────────────────────────────────────

    private function agentLeaderboard(?int $tenantId, $from, $to): array
    {
        // CALC-009: derive avg_response_min from the same conversation_events
        // stream as claimed/closed so reassignment doesn't credit the wrong
        // agent. Previously the response time was pulled from a separate query
        // keyed on conversations.owner_agent_id — a mutable column that
        // ConversationController::reassign rewrites — and then joined back by
        // event actor id. On any conversation reassigned after the claim, the
        // latency of the original claim was attributed to the new owner.
        //
        // The CASE below only samples rows where ce.type = "claimed", so
        // "closed" events don't dilute the average with time-to-close, and
        // any agent who claimed something in the period gets counted even
        // if the conversation has since moved on.
        $events = DB::table('conversation_events as ce')
            ->join('conversations as c', 'ce.conversation_id', '=', 'c.id')
            ->join('users', 'ce.actor_id', '=', 'users.id')
            ->whereBetween('ce.created_at', [$from, $to])
            ->whereIn('ce.type', ['claimed', 'closed'])
            ->whereIn('users.role', ['agent', 'supervisor', 'admin'])
            ->when($tenantId, fn($q) => $q->where('c.tenant_id', $tenantId))
            ->selectRaw('
                users.id,
                users.name,
                SUM(CASE WHEN ce.type = "claimed" THEN 1 ELSE 0 END) AS claimed,
                SUM(CASE WHEN ce.type = "closed"  THEN 1 ELSE 0 END) AS closed,
                ROUND(AVG(CASE WHEN ce.type = "claimed"
                    THEN TIMESTAMPDIFF(MINUTE, c.created_at, ce.created_at)
                    END), 0) AS avg_response_min
            ')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('claimed')
            ->get();

        $msgCounts = DB::table('messages')
            ->whereBetween('sent_at', [$from, $to])
            ->where('direction', 'out')
            ->where('author_type', 'agent')
            ->whereNotNull('author_id')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('author_id, COUNT(*) as cnt')
            ->groupBy('author_id')
            ->pluck('cnt', 'author_id');

        return $events->map(fn($r) => [
            'name'             => $r->name,
            'claimed'          => (int) $r->claimed,
            'closed'           => (int) $r->closed,
            'messages_sent'    => (int) ($msgCounts[$r->id] ?? 0),
            'avg_response_min' => is_null($r->avg_response_min) ? null : (int) $r->avg_response_min,
        ])->toArray();
    }

    // ── CHARTS ───────────────────────────────────────────────────────────────

    private function dailyVolume(?int $tenantId, $from, $to, int $period): array
    {
        // CALC-010: bucket by tenant-local calendar day and build the axis in
        // the same zone, otherwise a UTC+1 tenant sees traffic between 00:00
        // and 01:00 local time filed on the previous day.
        [$tz, $offset] = $this->tenantZone($tenantId);

        $rows = DB::table('conversations')
            ->whereBetween('created_at', [$from, $to])
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('DATE(CONVERT_TZ(created_at, ?, ?)) as day, COUNT(*) as cnt', ['+00:00', $offset])
            ->groupBy('day')
            ->pluck('cnt', 'day');

        $result = [];
        for ($i = $period; $i >= 0; $i--) {
            $day      = now($tz)->subDays($i)->format('Y-m-d');
            $result[] = ['day' => $day, 'count' => (int) ($rows[$day] ?? 0)];
        }
        return $result;
    }

    private function hourlyHeatmap(?int $tenantId, $from, $to): array
    {
        // CALC-010: bucket by tenant-local hour, not UTC. Otherwise the
        // heat-map is rotated by the tenant's UTC offset and the "busiest
        // hour" it shows is not the busiest local hour.
        [, $offset] = $this->tenantZone($tenantId);

        $rows = DB::table('messages')
            ->whereBetween('sent_at', [$from, $to])
            ->where('direction', 'in')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('HOUR(CONVERT_TZ(sent_at, ?, ?)) as hr, COUNT(*) as cnt', ['+00:00', $offset])
            ->groupBy('hr')
            ->pluck('cnt', 'hr');

        return collect(range(0, 23))
            ->map(fn($h) => ['hour' => str_pad($h, 2, '0', STR_PAD_LEFT) . 'h', 'count' => (int) ($rows[$h] ?? 0)])
            ->toArray();
    }

    /**
     * Resolve the tenant's IANA timezone + its current numeric offset (e.g.
     * "+01:00") for MySQL CONVERT_TZ. Super-admin cross-tenant view falls
     * back to the app default. The numeric offset is used rather than the
     * IANA name because many MySQL installs don't have the timezone tables
     * loaded and would otherwise return NULL from CONVERT_TZ. Trade-off is
     * that DST transitions within the report window use the current offset
     * throughout — acceptable for a Minor report finding.
     */
    private function tenantZone(?int $tenantId): array
    {
        if ($tenantId) {
            $tenant = \App\Models\Tenant::find($tenantId);
            $tz = $tenant?->effectiveTimezone() ?? config('app.timezone', 'UTC');
        } else {
            $tz = config('app.timezone', 'UTC');
        }

        $offset = now($tz)->format('P'); // "+01:00"
        return [$tz, $offset];
    }

    private function stateBreakdown(?int $tenantId, $from, $to): array
    {
        $rows = DB::table('conversations')
            ->whereBetween('created_at', [$from, $to])
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('state, COUNT(*) as cnt')
            ->groupBy('state')
            ->pluck('cnt', 'state');

        return [
            'pool'    => (int) ($rows['pool']    ?? 0),
            'claimed' => (int) ($rows['claimed'] ?? 0),
            'closed'  => (int) ($rows['closed']  ?? 0),
        ];
    }

    private function aiVsAgent(?int $tenantId, $from, $to): array
    {
        $rows = DB::table('messages')
            ->whereBetween('sent_at', [$from, $to])
            ->where('direction', 'out')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('author_type, COUNT(*) as cnt')
            ->groupBy('author_type')
            ->pluck('cnt', 'author_type');

        return [
            'agent'  => (int) ($rows['agent']  ?? 0),
            'ai'     => (int) ($rows['ai']     ?? 0),
            'system' => (int) ($rows['system'] ?? 0),
        ];
    }
}
