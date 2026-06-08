<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateWithApiKey
{
    public function handle(Request $request, Closure $next): mixed
    {
        $key = $request->header('X-Api-Key')
            ?? $request->header('x-api-key')
            ?? $request->query('api_key');

        if ($key) {
            $user = User::where('api_key', $key)->first();

            if (!$user) {
                return response()->json(['message' => 'Invalid API key.'], 401);
            }

            Auth::login($user);
            $request->setUserResolver(fn () => $user);

            return $next($request);
        }

        // Fall back to session auth for web users
        $user = Auth::guard('web')->user();

        if (!$user) {
            return response()->json(['message' => 'API key required. Add X-Api-Key header.'], 401);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
