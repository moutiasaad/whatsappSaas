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

        if (!$key) {
            return response()->json(['message' => 'API key required. Add X-Api-Key header.'], 401);
        }

        $user = User::where('api_key', $key)->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid API key.'], 401);
        }

        Auth::login($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
