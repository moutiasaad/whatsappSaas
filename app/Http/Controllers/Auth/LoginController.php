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
            'pageTitle'   => 'Super Admin Sign In',
            'portalBadge' => 'Super Admin Control Plane',
            'heading'     => 'Super Admin access',
            'subheading'  => 'Sign in to the platform owner control plane',
            'loginAction' => route('superadmin.login.submit'),
        ]);
    }

    public function showAdminLoginForm()
    {
        return view('auth.login', [
            'pageTitle'   => 'Tenant Admin Sign In',
            'portalBadge' => 'Tenant Admin Portal',
            'heading'     => 'Tenant Admin access',
            'subheading'  => 'Sign in to your tenant administration portal',
            'loginAction' => route('admin.login.submit'),
        ]);
    }

    public function showSupervisorLoginForm()
    {
        return view('auth.login', [
            'pageTitle'   => 'Supervisor Sign In',
            'portalBadge' => 'Supervisor Portal',
            'heading'     => 'Supervisor access',
            'subheading'  => 'Sign in to manage your support teams',
            'loginAction' => route('supervisor.login.submit'),
        ]);
    }

    public function showAgentLoginForm()
    {
        return view('auth.login', [
            'pageTitle'   => 'Support Agent Sign In',
            'portalBadge' => 'Support Agent Portal',
            'heading'     => 'Support Agent access',
            'subheading'  => 'Sign in to handle customer conversations',
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
        return $this->attemptLogin($request, adminOnly: true);
    }

    public function supervisorLogin(Request $request)
    {
        return $this->attemptLogin($request, supervisorOnly: true);
    }

    public function agentLogin(Request $request)
    {
        return $this->attemptLogin($request, agentOnly: true);
    }

    private function attemptLogin(
        Request $request,
        bool $superAdminOnly = false,
        bool $adminOnly = false,
        bool $supervisorOnly = false,
        bool $agentOnly = false
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
                $redirect = $user->isAdmin() ? 'admin.login' : ($user->isSupervisor() ? 'supervisor.login' : 'login');

                return $this->rejectPortalLogin(
                    $request,
                    'This portal is only for super admins.',
                    $redirect
                );
            }

            if ($adminOnly && !$user->isAdmin()) {
                $redirect = $user->isSuperAdmin()
                    ? 'superadmin.login'
                    : ($user->isSupervisor() ? 'supervisor.login' : 'login');

                return $this->rejectPortalLogin(
                    $request,
                    'This portal is only for tenant admins.',
                    $redirect
                );
            }

            if ($supervisorOnly && !$user->isSupervisor()) {
                $redirect = $user->isSuperAdmin()
                    ? 'superadmin.login'
                    : ($user->isAdmin() ? 'admin.login' : ($user->isAgent() ? 'agent.login' : 'login'));

                return $this->rejectPortalLogin(
                    $request,
                    'This portal is only for supervisors.',
                    $redirect
                );
            }

            if ($agentOnly && !$user->isAgent()) {
                $redirect = $user->isSuperAdmin()
                    ? 'superadmin.login'
                    : ($user->isAdmin() ? 'admin.login' : ($user->isSupervisor() ? 'supervisor.login' : 'login'));

                return $this->rejectPortalLogin(
                    $request,
                    'This portal is only for support agents.',
                    $redirect
                );
            }

            if (!$superAdminOnly && $user->isSuperAdmin()) {
                return $this->rejectPortalLogin(
                    $request,
                    'Use the Super Admin login page.',
                    'superadmin.login'
                );
            }

            if (!$superAdminOnly && !$adminOnly && !$supervisorOnly && $user->isAdmin()) {
                return $this->rejectPortalLogin(
                    $request,
                    'Use the Tenant Admin login page.',
                    'admin.login'
                );
            }

            if (!$superAdminOnly && !$adminOnly && !$supervisorOnly && $user->isSupervisor()) {
                return $this->rejectPortalLogin(
                    $request,
                    'Use the Supervisor login page.',
                    'supervisor.login'
                );
            }

            if (!$superAdminOnly && !$adminOnly && !$supervisorOnly && !$agentOnly && $user->isAgent()) {
                return $this->rejectPortalLogin(
                    $request,
                    'Use the Support Agent login page.',
                    'agent.login'
                );
            }

            if ($superAdminOnly) {
                $target = route('super_admin.dashboard');
            } elseif ($adminOnly) {
                $target = route('tenant_admin.dashboard');
            } elseif ($supervisorOnly) {
                $target = route('supervisor.conversations.index');
            } elseif ($agentOnly) {
                $target = route('agent.conversations.index');
            } else {
                $target = route($user->homeRouteName());
            }

            return redirect()->intended($target);
        }

        return back()->withErrors([
            'email' => 'These credentials do not match our records.',
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
