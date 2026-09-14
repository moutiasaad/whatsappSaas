<?php

namespace App\Http\Middleware;

use App\Models\Plan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the per-plan module entitlements the super admin ticks on
 * /admin-control-panel/platform/plans.
 *
 * Hiding a sidebar link is presentation, not entitlement — without this the
 * page is still reachable by typing the URL. Super admins bypass the gate:
 * they operate the platform and are not on a plan.
 *
 * A blocked page read (GET/HEAD) renders the upgrade preview: a blurred mock of
 * the module behind a prompt that upgrades to the cheapest plan carrying it.
 * Everything else — writes, JSON — still gets the hard 403/redirect, so the
 * teaser never becomes a way to actually use a module the tenant has not paid
 * for.
 */
class CheckPlanModule
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();

        if (!$user || $user->isSuperAdmin()) {
            return $next($request);
        }

        if ($user->tenant?->planAllows($module)) {
            return $next($request);
        }

        $message = __('ui.controller_messages.module_not_in_plan');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'code' => 'module_not_in_plan'], 403);
        }

        if ($request->isMethodSafe()) {
            return response()->view('admin.locked.module', [
                'module'      => $module,
                // Only a tenant admin can act on the offer; everyone else sees
                // the preview with a "ask your admin" line instead of a CTA.
                'upgradePlan' => $user->isAdmin() ? Plan::cheapestWithModule($module) : null,
            ], 402);
        }

        return redirect()
            ->route($user->routeNamePrefix() . '.dashboard')
            ->with('error', $message);
    }
}
