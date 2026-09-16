<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->isSuperAdmin()) {
            return $next($request);
        }

        $tenant = $user->tenant;

        // Signup step 2 never finished: the workspace exists but no plan was
        // ever chosen, so there is nothing to bill and nothing to trial. The
        // admin goes back to the picker; anyone else on that workspace is
        // simply told to wait for them.
        if ($tenant && !$tenant->plan_id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'No plan selected for this workspace yet.',
                    'code'    => 'plan_not_selected',
                ], 403);
            }

            if ($user->role === 'admin') {
                return redirect()->route('register.plan');
            }

            return $this->deny($request, $user, 'plan_not_selected',
                'This workspace has no plan yet. Ask your administrator to choose one.');
        }

        // Archived tenant — long-term shelved, hidden from the tenants list,
        // no way in for any user. Checked before the block branch because
        // it's the more terminal state (archive implies block).
        if ($tenant && $tenant->archived_at) {
            return $this->denyArchived($request);
        }

        // Manual block by super-admin (tenants.is_active=false while
        // subscription_status is still active/trial) is a policy action, not
        // a billing problem. Distinguish it from a lapsed subscription so:
        //   - The admin is LOGGED OUT rather than deflected to /billing —
        //     otherwise the "Renew" button suggests they can pay their way
        //     around the block, which defeats the super-admin's intent.
        //   - The message names the actual cause ("suspended by an
        //     administrator") instead of the misleading "subscription
        //     inactive" that a paying customer would see under the same
        //     is_active=false condition.
        // Distinct code (workspace_blocked) so the login page + API callers
        // can render an appropriate copy rather than a stale renewal prompt.
        if ($tenant && ! $tenant->is_active
            && in_array($tenant->subscription_status, ['active', 'trial'], true)) {
            return $this->denyBlocked($request);
        }

        if (!$tenant || !$tenant->isActive()) {
            return $this->deny($request, $user, 'subscription_inactive',
                'Your subscription is inactive. Please renew your plan to continue.');
        }

        if (
            $tenant->subscription_status === 'trial'
            && $tenant->trial_ends_at
            && $tenant->trial_ends_at->isPast()
        ) {
            return $this->deny($request, $user, 'trial_expired',
                'Your trial has expired. Please subscribe to a plan to continue.');
        }

        if (
            $tenant->subscription_status === 'active'
            && $tenant->subscription_ends_at
            && $tenant->subscription_ends_at->isPast()
        ) {
            return $this->deny($request, $user, 'subscription_expired',
                'Your subscription has expired. Please renew your plan to continue.');
        }

        return $next($request);
    }

    /**
     * Manual super-admin block: kill the session regardless of role and land
     * the user on /login with a "workspace suspended" flash. Admins get no
     * /billing off-ramp because paying doesn't lift a policy block — only
     * the super-admin unblocking does.
     */
    /**
     * Archived tenant — same lockout shape as denyBlocked, distinct copy
     * so the login page names the actual cause instead of the misleading
     * "suspended by administrator" line meant for a live block.
     */
    private function denyArchived(Request $request): Response
    {
        $message = __('auth.errors.workspace_archived');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'code'    => 'workspace_archived',
            ], 403);
        }

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => $message]);
    }

    private function denyBlocked(Request $request): Response
    {
        $message = __('auth.errors.workspace_blocked');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'code'    => 'workspace_blocked',
            ], 403);
        }

        // Same logout+invalidate+regenerate shape LoginController's
        // rejectPortalLogin uses for the "super_admin_only" /
        // "use_control_panel_login" flashes, which are known to render on
        // the target login page. withErrors() (not with('error', …)) is
        // the piece that was missing — the login view reads $errors->first()
        // at the top of the form but has no reader for a plain
        // session('error') flash.
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => $message]);
    }

    /**
     * JSON requests get a 403 with a machine-readable code; browsers get
     * routed to a page they can actually recover from — /billing for admins
     * (who can pay), the login screen for everyone else (who cannot).
     */
    private function deny(Request $request, $user, string $code, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'code' => $code], 403);
        }

        if ($user->role === 'admin' && method_exists($user, 'routeNamePrefix')) {
            $target = route($user->routeNamePrefix() . '.billing.index');
        } else {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $target = route('login');
        }

        return redirect($target)->with('error', $message);
    }
}
