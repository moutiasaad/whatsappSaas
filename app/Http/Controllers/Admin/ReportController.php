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
                SUM(CASE WHEN ce.type = "closed"  THEN 1 ELSE 0 END) AS closed
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

        $responseTimes = DB::table('conversations')
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('owner_agent_id')
            ->whereNotNull('claimed_at')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('owner_agent_id, ROUND(AVG(TIMESTAMPDIFF(MINUTE, created_at, claimed_at)), 0) as avg_min')
            ->groupBy('owner_agent_id')
            ->pluck('avg_min', 'owner_agent_id');

        return $events->map(fn($r) => [
            'name'             => $r->name,
            'claimed'          => (int) $r->claimed,
            'closed'           => (int) $r->closed,
            'messages_sent'    => (int) ($msgCounts[$r->id] ?? 0),
            'avg_response_min' => isset($responseTimes[$r->id]) ? (int) $responseTimes[$r->id] : null,
        ])->toArray();
    }

    // ── CHARTS ───────────────────────────────────────────────────────────────

    private function dailyVolume(?int $tenantId, $from, $to, int $period): array
    {
        $rows = DB::table('conversations')
            ->whereBetween('created_at', [$from, $to])
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('DATE(created_at) as day, COUNT(*) as cnt')
            ->groupBy('day')
            ->pluck('cnt', 'day');

        $result = [];
        for ($i = $period; $i >= 0; $i--) {
            $day      = now()->subDays($i)->format('Y-m-d');
            $result[] = ['day' => $day, 'count' => (int) ($rows[$day] ?? 0)];
        }
        return $result;
    }

    private function hourlyHeatmap(?int $tenantId, $from, $to): array
    {
        $rows = DB::table('messages')
            ->whereBetween('sent_at', [$from, $to])
            ->where('direction', 'in')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('HOUR(sent_at) as hr, COUNT(*) as cnt')
            ->groupBy('hr')
            ->pluck('cnt', 'hr');

        return collect(range(0, 23))
            ->map(fn($h) => ['hour' => str_pad($h, 2, '0', STR_PAD_LEFT) . 'h', 'count' => (int) ($rows[$h] ?? 0)])
            ->toArray();
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
