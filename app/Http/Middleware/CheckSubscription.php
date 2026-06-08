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
            return response()->json([
                'message' => 'Your subscription is inactive. Please renew your plan to continue.',
                'code'    => 'subscription_inactive',
            ], 403);
        }

        if (
            $tenant->subscription_status === 'trial'
            && $tenant->trial_ends_at
            && $tenant->trial_ends_at->isPast()
        ) {
            return response()->json([
                'message' => 'Your trial has expired. Please subscribe to a plan to continue.',
                'code'    => 'trial_expired',
            ], 403);
        }

        if (
            $tenant->subscription_status === 'active'
            && $tenant->subscription_ends_at
            && $tenant->subscription_ends_at->isPast()
        ) {
            return response()->json([
                'message' => 'Your subscription has expired. Please renew your plan to continue.',
                'code'    => 'subscription_expired',
            ], 403);
        }

        return $next($request);
    }
}
