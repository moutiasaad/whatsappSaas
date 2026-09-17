<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * SessionStatusController (Server B — app.wavadesk.com)
 *
 * A tiny public-ish endpoint the marketing site (wavadesk.com) calls from
 * the visitor's browser so the header CTA can flip from
 * "Sign in / Start free trial" to "Go to dashboard" when the same browser
 * already carries a valid app.wavadesk.com session cookie.
 *
 * Cross-site fetch shape:
 *   fetch('https://app.wavadesk.com/api/v1/session/status',
 *         { credentials: 'include', mode: 'cors' })
 *
 * Same-eTLD subdomain (wavadesk.com ↔ app.wavadesk.com) is same-site, so a
 * SameSite=Lax session cookie IS sent on this request. CORS with
 * Allow-Credentials + an origin allowlist (see SessionStatusCors middleware)
 * is what lets the marketing origin actually READ the response.
 *
 * The payload is deliberately minimal — display name, initials, role, home
 * URL — nothing that isn't already shown to the user in their own panel.
 * A leaked response reveals no session token and no email.
 *
 * Guarded by SessionStatusCors (not `wavadesk.caller`), because the caller
 * here is the visitor's browser, which has no shared secret to sign with.
 * The origin allowlist is what stands in for the shared secret.
 */
class SessionStatusController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        // Explicit `web` guard — routes/api.php shares the web session (see
        // bootstrap/app.php's `then` block), so this reads the same
        // Laravel session cookie a signed-in visitor already carries.
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        if (! $user) {
            return response()->json(['authenticated' => false]);
        }

        return response()->json([
            'authenticated' => true,
            'user' => [
                'name'     => $user->name,
                'initials' => $this->initials($user->name),
                'role'     => $user->role,
            ],
            'home_url' => method_exists($user, 'homeRouteName')
                ? url(route($user->homeRouteName(), [], false))
                : url('/'),
        ]);
    }

    private function initials(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '?';
        }
        $parts = preg_split('/\s+/', $name) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
        return mb_strtoupper($first . $last);
    }
}
