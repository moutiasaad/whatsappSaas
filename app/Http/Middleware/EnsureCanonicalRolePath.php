<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class EnsureCanonicalRolePath
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if (!$user || !$request->isMethodSafe()) {
            return $next($request);
        }

        $name = $request->route()?->getName();
        if (!$name) {
            return $next($request);
        }

        $prefixes = ['admin', 'super_admin', 'tenant_admin', 'supervisor', 'agent'];
        $currentPrefix = Str::before($name, '.');
        $canonicalPrefix = $user->routeNamePrefix();

        if (!in_array($currentPrefix, $prefixes, true) || $currentPrefix === $canonicalPrefix) {
            return $next($request);
        }

        $suffix = Str::after($name, '.');
        $targetRoute = $canonicalPrefix . '.' . $suffix;

        if (!Route::has($targetRoute)) {
            return $next($request);
        }

        $parameters = array_merge(
            $request->route()->parametersWithoutNulls(),
            $request->query()
        );

        return redirect()->route($targetRoute, $parameters);
    }
}
