<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\SsoHandoffCode;
use App\Services\WavadeskApi;
use App\Support\Wavadesk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function showSuperAdminLoginForm()
    {
        // The platform portal is a core-app door. wavadesk.com never renders
        // it: proxying a super-admin sign-in through the marketing site would
        // put the platform password on the public marketing host for no gain.
        if (Wavadesk::isMarketing()) {
            return $this->redirectToCoreControlPanel();
        }

        return view('auth.login', [
            'pageTitle'   => __('auth.login.portals.super_admin_title'),
            'portalBadge' => __('auth.login.portals.super_admin_badge'),
            'heading'     => __('auth.login.portals.super_admin_heading'),
            'subheading'  => __('auth.login.portals.super_admin_subheading'),
            'loginAction' => route('superadmin.login.submit'),
        ]);
    }

    public function showAdminLoginForm()
    {
        return view('auth.login', [
            'pageTitle'   => __('auth.login.portals.admin_title'),
            'portalBadge' => __('auth.login.portals.admin_badge'),
            'heading'     => __('auth.login.portals.admin_heading'),
            'subheading'  => __('auth.login.portals.admin_subheading'),
            'loginAction' => route('admin.login.submit'),
        ]);
    }

    public function showSupervisorLoginForm()
    {
        return view('auth.login', [
            'pageTitle'   => __('auth.login.portals.supervisor_title'),
            'portalBadge' => __('auth.login.portals.supervisor_badge'),
            'heading'     => __('auth.login.portals.supervisor_heading'),
            'subheading'  => __('auth.login.portals.supervisor_subheading'),
            'loginAction' => route('supervisor.login.submit'),
        ]);
    }

    public function showAgentLoginForm()
    {
        return view('auth.login', [
            'pageTitle'   => __('auth.login.portals.agent_title'),
            'portalBadge' => __('auth.login.portals.agent_badge'),
            'heading'     => __('auth.login.portals.agent_heading'),
            'subheading'  => __('auth.login.portals.agent_subheading'),
            'loginAction' => route('agent.login.submit'),
        ]);
    }

    public function login(Request $request)
    {
        return $this->attemptLogin($request, superAdminOnly: false);
    }

    public function superAdminLogin(Request $request)
    {
        if (Wavadesk::isMarketing()) {
            return $this->redirectToCoreControlPanel();
        }

        return $this->attemptLogin($request, superAdminOnly: true);
    }

    public function adminLogin(Request $request)
    {
        return redirect()->route('login');
    }

    public function supervisorLogin(Request $request)
    {
        return redirect()->route('login');
    }

    public function agentLogin(Request $request)
    {
        return redirect()->route('login');
    }

    private function attemptLogin(
        Request $request,
        bool $superAdminOnly = false
    )
    {
        // On wavadesk.com there is no local users table to attempt against:
        // credentials go to the core app and come back as a token plus a
        // one-shot code that opens the session on app.wavadesk.com.
        if (Wavadesk::isMarketing()) {
            return $this->attemptLoginViaCoreApi($request);
        }

        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = $request->user();

            if ($superAdminOnly && !$user->isSuperAdmin()) {
                return $this->rejectPortalLogin(
                    $request,
                    __('auth.errors.super_admin_only'),
                    'superadmin.login'
                );
            }

            // The platform account is not a workspace account: it has no tenant
            // and no tenant panel to land on. Turning it away from the shared
            // form keeps the two sign-ins genuinely separate rather than one
            // door that happens to branch afterwards.
            if (!$superAdminOnly && $user->isSuperAdmin()) {
                return $this->rejectPortalLogin(
                    $request,
                    __('auth.errors.use_control_panel_login'),
                    'superadmin.login'
                );
            }

            // Belt-and-suspenders: block-checked at login-time too, so the
            // "workspace suspended" message renders on the SAME response
            // instead of via a flash-through-redirect chain (CheckSubscription
            // would also catch this on the very next panel request, but the
            // flash surviving auth-logout + session-regenerate has been
            // fragile across drivers). Same rule CheckSubscription uses:
            // tenants.is_active=false while subscription_status is still
            // active/trial → super-admin block, not a subscription lapse.
            if (!$user->isSuperAdmin() && ($tenant = $user->tenant)) {
                // Archive check first — more terminal than block. A tenant
                // can be blocked AND archived; archive wins the message.
                if ($tenant->archived_at) {
                    return $this->rejectPortalLogin(
                        $request,
                        __('auth.errors.workspace_archived'),
                        $superAdminOnly ? 'superadmin.login' : 'login'
                    );
                }

                if (! $tenant->is_active
                    && in_array($tenant->subscription_status, ['active', 'trial'], true)) {
                    return $this->rejectPortalLogin(
                        $request,
                        __('auth.errors.workspace_blocked'),
                        $superAdminOnly ? 'superadmin.login' : 'login'
                    );
                }
            }

            if ($superAdminOnly) {
                $target = route('super_admin.dashboard');
            } else {
                $target = route($user->homeRouteName());
            }

            return redirect()->intended($target);
        }

        return back()->withErrors([
            'email' => __('auth.errors.credentials_mismatch'),
        ])->onlyInput('email');
    }

    private function rejectPortalLogin(Request $request, string $message, string $redirectRoute)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($redirectRoute)->withErrors([
            'email' => $message,
        ])->onlyInput('email');
    }

    // ── Marketing role (Server A / wavadesk.com) ─────────────────────────────

    /**
     * Forward the credentials to the core app and hand the browser off.
     *
     * The multi-portal shape (admin / supervisor / agent) does not need to be
     * reproduced here: the core app decides where the account lands from its
     * role, so the marketing site only ever needs the one workspace form.
     */
    private function attemptLoginViaCoreApi(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $result = app(WavadeskApi::class)->login($credentials);

        if (! $result['ok']) {
            $this->rethrowCoreValidation($result);

            // 403 carries a real message the user must see — a disabled
            // account, or a platform account told to use the control panel.
            // Everything else collapses into the generic mismatch error so a
            // backend outage cannot be read as "wrong password".
            $message = $result['status'] === 403 && isset($result['body']['message'])
                ? (string) $result['body']['message']
                : __('auth.errors.credentials_mismatch');

            if ($result['status'] === 0) {
                Log::error('Marketing -> core login unreachable', [
                    'core_url' => Wavadesk::coreUrl(),
                ]);
            }

            return back()->onlyInput('email')->withErrors(['email' => $message]);
        }

        $body   = $result['body'];
        $userId = (int) ($body['user']['id'] ?? 0);
        $token  = (string) ($body['token'] ?? '');

        if ($userId === 0 || $token === '') {
            Log::error('Core app returned a login success with no token or user id');

            return back()->onlyInput('email')->withErrors([
                'email' => __('auth.errors.credentials_mismatch'),
            ]);
        }

        $request->session()->put(Wavadesk::SESSION_TOKEN,  $token);
        $request->session()->put(Wavadesk::SESSION_USER,   $body['user'] ?? []);
        $request->session()->put(Wavadesk::SESSION_TENANT, $body['tenant'] ?? []);

        // A user whose workspace has no plan yet needs the picker before the
        // panel, and the picker now lives on wavadesk.com — keep them here
        // instead of a two-hop wavadesk.com → app.wavadesk.com → wavadesk.com.
        // Everyone else (dashboard) hands off to core signed in.
        if (($body['next_step'] ?? null) === 'plan') {
            return redirect()->route('register.plan');
        }

        $code = app(SsoHandoffCode::class)->mint($userId);

        return redirect()->away(
            Wavadesk::coreUrlTo('/auth/sso') . '?code=' . urlencode($code)
        );
    }

    private function redirectToCoreControlPanel()
    {
        $panel = config('app.super_admin_prefix', 'admin-control-panel');

        return redirect()->away(Wavadesk::coreUrlTo($panel . '/login'));
    }

    private function rethrowCoreValidation(array $result): void
    {
        if ($result['status'] !== 422) {
            return;
        }

        $errors = $result['body']['errors'] ?? null;

        if (! is_array($errors) || $errors === []) {
            return;
        }

        throw ValidationException::withMessages($errors);
    }

    public function logout(Request $request)
    {
        // Marketing side: revoke the core app's token so signing out here also
        // ends the API access this session was granted. Best-effort — the
        // local session is cleared whether or not the core app answers, so a
        // backend outage can never leave a user stuck looking signed in.
        if (Wavadesk::isMarketing()) {
            $token = (string) $request->session()->pull(Wavadesk::SESSION_TOKEN, '');

            $request->session()->forget([Wavadesk::SESSION_USER, Wavadesk::SESSION_TENANT]);

            if ($token !== '') {
                rescue(fn () => app(WavadeskApi::class)->logout($token), report: false);
            }
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
