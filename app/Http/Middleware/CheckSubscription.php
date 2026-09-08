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
