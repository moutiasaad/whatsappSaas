<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\SsoHandoffCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * SsoHandoffController (Server B — app.wavadesk.com)
 *
 * Turns a short-lived signed handoff code from Server A (wavadesk.com) into a
 * first-party web session on Server B. This is the one hop where the browser
 * actually crosses domains — the redirect URL carries the code and nothing
 * else, so a leaked URL is useless after 60 seconds and useless immediately
 * if another request already redeemed the nonce.
 *
 * Never accept a Sanctum bearer token on this endpoint: tokens don't belong
 * in URLs. The code is a purpose-built, single-use, expiring credential.
 */
class SsoHandoffController extends Controller
{
    /**
     * GET /auth/sso?code=<handoff>
     *
     * Verifies the code, logs the user into the web guard, regenerates the
     * session (fixation defence), and redirects to the tenant's home route.
     * All failure modes return the login form with a generic error — never
     * an oracle that tells Server A *why* the code was rejected.
     */
    public function redeem(Request $request, SsoHandoffCode $codes)
    {
        $code = (string) $request->query('code', '');

        if ($code === '') {
            return redirect()->route('login')->withErrors([
                'email' => __('auth.errors.credentials_mismatch'),
            ]);
        }

        try {
            $userId = $codes->verify($code);
        } catch (\Throwable $e) {
            // Log the reason server-side for ops, but do not leak it to the
            // browser. Any failure is "invalid handoff" from the caller's POV.
            Log::warning('SSO handoff rejected', [
                'reason' => $e->getMessage(),
                'ip'     => $request->ip(),
                'ua'     => substr((string) $request->userAgent(), 0, 200),
            ]);
            return redirect()->route('login')->withErrors([
                'email' => __('auth.errors.credentials_mismatch'),
            ]);
        }

        /** @var User|null $user */
        $user = User::find($userId);

        if (! $user || ! $user->is_active) {
            return redirect()->route('login')->withErrors([
                'email' => __('auth.errors.credentials_mismatch'),
            ]);
        }

        Auth::guard('web')->login($user, remember: false);
        $request->session()->regenerate();

        // Match the web LoginController's post-login routing: workspace admins
        // and staff land on their role's home; a workspace that hasn't picked
        // a plan yet gets pushed to the plan step. Super-admins never arrive
        // here — the API login already refuses them — so no branch needed.
        $tenant = $user->tenant;
        if ($tenant && ! $tenant->plan_id) {
            return redirect()->route('register.plan');
        }

        return redirect()->route(
            method_exists($user, 'homeRouteName') ? $user->homeRouteName() : 'admin.dashboard'
        );
    }
}
