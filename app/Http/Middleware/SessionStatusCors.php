<?php

namespace App\Http\Middleware;

use App\Support\Wavadesk;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SessionStatusCors
 *
 * Per-route CORS shim for GET /api/v1/session/status. The global
 * config/cors.php is scoped to `api/webchat/*` on purpose — widening it
 * to cover this endpoint would either force `supports_credentials => true`
 * on the widget (which the widget explicitly does not want, see the comment
 * block in config/cors.php) or fail preflight for credentialed calls.
 *
 * This shim answers preflight and stamps response headers only when the
 * request comes from the configured marketing origin. Any other origin
 * silently gets the response with no CORS headers, which the browser then
 * blocks in the caller — same effect as a 403 but without giving away
 * whether the endpoint exists.
 *
 * See app/Http/Controllers/Api/V1/SessionStatusController.php for the
 * fetch shape and why the origin allowlist stands in for the shared
 * secret on this browser-called endpoint.
 */
class SessionStatusCors
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin  = (string) $request->headers->get('Origin', '');
        $allowed = $this->originAllowed($origin);

        // Preflight — answered here without hitting the controller so the
        // browser doesn't need a session cookie to negotiate CORS.
        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 204);
            return $this->stamp($response, $origin, $allowed);
        }

        /** @var Response $response */
        $response = $next($request);

        return $this->stamp($response, $origin, $allowed);
    }

    private function originAllowed(string $origin): bool
    {
        if ($origin === '') {
            return false;
        }

        $marketing = Wavadesk::marketingOrigin();
        if ($marketing === '') {
            return false;
        }

        return strcasecmp($origin, $marketing) === 0;
    }

    private function stamp(Response $response, string $origin, bool $allowed): Response
    {
        // Vary always — even when we refuse the origin. A shared cache
        // must not serve one visitor's answer to a different origin.
        $response->headers->set('Vary', 'Origin, Cookie', false);

        // Never cache the answer itself: it flips as soon as the visitor
        // logs in or out on core.
        $response->headers->set('Cache-Control', 'no-store, private');

        if (! $allowed) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Accept, X-Requested-With, Content-Type');
        $response->headers->set('Access-Control-Max-Age', '300');

        return $response;
    }
}
