<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiApiUsage;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide Claude API cost view.
 *
 * Reads the ai_api_usages ledger and surfaces:
 *   - KPI totals for the selected window (spend / tokens / calls)
 *   - Per-tenant breakdown (which tenant is costing us the most)
 *   - Per-model breakdown (Haiku vs Sonnet vs Opus mix)
 *   - Per-source breakdown (whatsapp vs webchat vs messenger vs title vs ask)
 *
 * Aggregations run in SQL so a 100k-row ledger doesn't hydrate 100k models.
 */
class ClaudeUsageController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $range = (string) $request->query('range', '30d');
        [$from, $to, $rangeLabel] = $this->resolveRange($range);

        // Base window filter reused by every aggregation. Applying it
        // to a fresh AiApiUsage::query() each time keeps the composed
        // queries independent (clone is cheaper but harder to reason
        // about when SUM/COUNT branches share a builder).
        $windowed = fn () => AiApiUsage::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to,   fn ($q) => $q->where('created_at', '<=', $to));

        $totals = $windowed()
            ->selectRaw('COUNT(*) as calls')
            ->selectRaw('COALESCE(SUM(input_tokens), 0)  as in_tok')
            ->selectRaw('COALESCE(SUM(output_tokens), 0) as out_tok')
            ->selectRaw('COALESCE(SUM(cost_usd), 0)      as cost')
            ->first();

        $byTenant = $windowed()
            ->selectRaw('tenant_id')
            ->selectRaw('COUNT(*) as calls')
            ->selectRaw('COALESCE(SUM(input_tokens), 0)  as in_tok')
            ->selectRaw('COALESCE(SUM(output_tokens), 0) as out_tok')
            ->selectRaw('COALESCE(SUM(cost_usd), 0)      as cost')
            ->groupBy('tenant_id')
            ->orderByDesc('cost')
            ->limit(50)
            ->get();

        // Fetch tenant names in one query rather than N+1 during the
        // view iteration. keyBy for O(1) lookup in the blade.
        $tenants = Tenant::whereIn('id', $byTenant->pluck('tenant_id'))
            ->get(['id', 'name', 'slug'])
            ->keyBy('id');

        $byModel = $windowed()
            ->selectRaw('model')
            ->selectRaw('COUNT(*) as calls')
            ->selectRaw('COALESCE(SUM(input_tokens), 0)  as in_tok')
            ->selectRaw('COALESCE(SUM(output_tokens), 0) as out_tok')
            ->selectRaw('COALESCE(SUM(cost_usd), 0)      as cost')
            ->groupBy('model')
            ->orderByDesc('cost')
            ->get();

        $bySource = $windowed()
            ->selectRaw('source')
            ->selectRaw('COUNT(*) as calls')
            ->selectRaw('COALESCE(SUM(cost_usd), 0) as cost')
            ->groupBy('source')
            ->orderByDesc('cost')
            ->get();

        $recent = $windowed()
            ->with('tenant:id,name,slug')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        // Lifetime totals — not window-scoped. Gives the platform owner
        // a "since day one" anchor next to the "last 30 days" KPI so a
        // quiet week doesn't hide accumulated spend.
        $lifetime = AiApiUsage::query()
            ->selectRaw('COUNT(*) as calls')
            ->selectRaw('COALESCE(SUM(cost_usd), 0) as cost')
            ->first();

        return view('admin.platform.claude-usage', [
            'range'       => $range,
            'rangeLabel'  => $rangeLabel,
            'from'        => $from,
            'to'          => $to,
            'totals'      => $totals,
            'lifetime'    => $lifetime,
            'byTenant'    => $byTenant,
            'tenants'     => $tenants,
            'byModel'     => $byModel,
            'bySource'    => $bySource,
            'recent'      => $recent,
        ]);
    }

    /**
     * Convert the range slug into [Carbon $from, Carbon $to, label].
     * `all` returns [null, null] so the queries drop the where clauses.
     *
     * @return array{0: ?Carbon, 1: ?Carbon, 2: string}
     */
    private function resolveRange(string $range): array
    {
        $now = now();

        return match ($range) {
            '7d'    => [$now->copy()->subDays(7),  $now, __('ui.claude_usage.range_7d')],
            'month' => [$now->copy()->startOfMonth(), $now, __('ui.claude_usage.range_month')],
            '90d'   => [$now->copy()->subDays(90), $now, __('ui.claude_usage.range_90d')],
            'all'   => [null, null, __('ui.claude_usage.range_all')],
            default => [$now->copy()->subDays(30), $now, __('ui.claude_usage.range_30d')],
        };
    }
}
