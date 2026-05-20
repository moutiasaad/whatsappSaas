<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if ($user?->tenant_id) {
            app()->instance('current_tenant_id', $user->tenant_id);
        } elseif ($user && $user->role !== 'super_admin') {
            abort(403, 'Your account is not linked to a tenant. Please contact your administrator.');
        }

        return $next($request);
    }
}
