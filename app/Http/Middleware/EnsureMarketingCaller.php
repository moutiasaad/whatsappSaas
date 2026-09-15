<?php

namespace App\Http\Middleware;

use App\Support\Wavadesk;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureMarketingCaller
 *
 * Gates the v1 auth API on the core app (app.wavadesk.com) so only the
 * marketing app (wavadesk.com) can reach it. The caller proves itself with the
 * shared secret in an X-Wavadesk-Caller header; nothing else gets through.
 *
 * Without this, /api/v1/auth/login is an unauthenticated, internet-facing
 * credential oracle: anyone could grind it for valid email/password pairs at
 * whatever the rate limiter allows, and /api/v1/auth/register would let any
 * script create workspace rows straight into the production database.
 *
 * Failures answer 404, not 401/403. A wrong secret and a route that does not
 * exist are indistinguishable from outside, so the endpoint cannot be probed
 * for existence or used to confirm that a guessed secret is close.
 *
 * Fails closed: with no secret configured the endpoints are simply not
 * reachable, which is the correct state for a split that has not been wired up.
 */
class EnsureMarketingCaller
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = Wavadesk::sharedSecret();

        // A short or absent secret is a misconfiguration, not a valid open
        // state. Log it once per request so a half-finished cutover is visible
        // in the log instead of surfacing as a mystery 404 on the other host.
        if (! Wavadesk::hasSharedSecret()) {
            Log::warning('v1 auth API refused: WAVADESK_SHARED_SECRET is unset or too short', [
                'path' => $request->path(),
            ]);

            abort(404);
        }

        $presented = (string) $request->header(Wavadesk::CALLER_HEADER, '');

        // hash_equals is not constant-time over differing lengths, so compare
        // digests of fixed width rather than the raw values.
        $ok = hash_equals(
            hash('sha256', $expected),
            hash('sha256', $presented)
        );

        if (! $ok) {
            Log::warning('v1 auth API refused: bad caller secret', [
                'path' => $request->path(),
                'ip'   => $request->ip(),
                'ua'   => substr((string) $request->userAgent(), 0, 200),
            ]);

            abort(404);
        }

        return $next($request);
    }
}
