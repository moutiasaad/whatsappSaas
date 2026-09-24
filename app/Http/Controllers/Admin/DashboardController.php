<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppInstance;
use App\Services\Dashboard\DashboardMetrics;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private const RANGES = [7, 14, 30];

    public function index(Request $request)
    {
        $user   = auth()->user();
        $tenant = $user->tenant;

        // Super admins have no tenant of their own — they get a platform roll-up.
        if (!$tenant) {
            return $this->superAdminDashboard();
        }

        // A workspace with nothing connected has nothing to chart, so a new
        // admin lands on setup instead of an empty dashboard — until they
        // finish it or skip it, which is what stops this being a loop.
        $setup = $user->routeNamePrefix() . '.onboarding.show';
        if ($user->isAdmin() && \Illuminate\Support\Facades\Route::has($setup) && !$tenant->onboardingSettled()) {
            return redirect()->route($setup);
        }

        $days = (int) $request->query('range', 14);
        if (!in_array($days, self::RANGES, true)) {
            $days = 14;
        }

        $m = new DashboardMetrics($tenant->id, $days);

        $series = $m->series();
        $ai     = $m->aiBreakdown();
        $reply  = $m->firstReply();
        $pool   = $m->pool();

        $waTotal = array_sum(array_column($series, 'wa'));
        $lcTotal = array_sum(array_column($series, 'lc'));

        // Delta = second half of the window against the first.
        $half   = (int) floor($days / 2);
        $prev   = array_sum(array_map(fn ($d) => $d['wa'] + $d['lc'], array_slice($series, 0, $half)));
        $cur    = array_sum(array_map(fn ($d) => $d['wa'] + $d['lc'], array_slice($series, $half)));
        $delta  = $prev > 0 ? (int) round(($cur - $prev) / $prev * 100) : null;

        $replyDelta = ($reply['previous'] && $reply['current'])
            ? (int) round(($reply['current'] - $reply['previous']) / $reply['previous'] * 100)
            : null;

        return view('admin.dashboard.index', [
            'days'         => $days,
            'ranges'       => self::RANGES,
            'tenant'       => $tenant,
            'trial'        => $this->trial($tenant),
            'series'       => $series,
            'waTotal'      => $waTotal,
            'lcTotal'      => $lcTotal,
            'convTotal'    => $waTotal + $lcTotal,
            'convDelta'    => $delta,
            'reply'        => $reply,
            'replyDelta'   => $replyDelta,
            'ai'           => $ai,
            'aiSettings'   => $tenant->aiSettings,
            'pool'         => $pool,
            'agents'       => $m->agents(),
            'heat'         => $m->heatmap(),
            'needs'        => $m->needsAttention(),
            'repeatPct'    => $m->repeatCustomerPct(),
            'instanceCount'=> WhatsAppInstance::where('tenant_id', $tenant->id)->count(),
        ]);
    }

    /** Trial / subscription state for the banner. */
    private function trial(Tenant $tenant): ?array
    {
        if ($tenant->subscription_status !== 'trial' || !$tenant->trial_ends_at) {
            return null;
        }

        $total = (int) config('app.trial_days', 14);
        $left  = max(0, (int) ceil(now()->floatDiffInDays($tenant->trial_ends_at, false)));
        $used  = max(0, $total - $left);

        return [
            'days_left' => $left,
            'total'     => $total,
            'used'      => $used,
            'percent'   => $total > 0 ? min(100, (int) round($used / $total * 100)) : 0,
            'ends_at'   => $tenant->trial_ends_at,
            'level'     => $left <= 2 ? 'crit' : ($left <= 4 ? 'warn' : 'ok'),
            'plan'      => $tenant->plan?->name,
        ];
    }

    private function superAdminDashboard()
    {
        $days = 14;

        return view('admin.dashboard.index', [
            'days'          => $days,
            'ranges'        => self::RANGES,
            'tenant'        => null,
            'trial'         => null,
            'series'        => [],
            'waTotal'       => 0,
            'lcTotal'       => 0,
            'convTotal'     => Conversation::count(),
            'convDelta'     => null,
            'reply'         => ['median' => null, 'previous' => null, 'current' => null],
            'replyDelta'    => null,
            'ai'            => ['ai_only' => 0, 'both' => 0, 'agent_only' => 0, 'total' => 0, 'ai_pct' => 0, 'ai_median_seconds' => null],
            'aiSettings'    => null,
            'pool'          => ['count' => Conversation::where('state', 'pool')->count(), 'oldest' => null],
            'agents'        => collect(),
            'heat'          => ['grid' => [], 'max' => 0, 'peak' => ['hour' => null, 'n' => 0]],
            'needs'         => collect(),
            'repeatPct'     => 0,
            'instanceCount' => WhatsAppInstance::count(),
            'platform'      => [
                'tenants' => Tenant::count(),
                'users'   => User::count(),
            ],
        ]);
    }
}
