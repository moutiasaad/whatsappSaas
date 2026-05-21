<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function showSuperAdminLoginForm()
    {
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

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
