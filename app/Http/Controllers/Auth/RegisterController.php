<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RegisterController extends Controller
{
    // ── Step 1: show registration form ──────────────────────────────────────

    public function show(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route(auth()->user()->homeRouteName());
        }

        $plans        = Plan::where('is_active', true)->orderBy('id')->get();
        $selectedPlan = $plans->firstWhere('id', (int) $request->get('plan')) ?? $plans->first();

        return view('auth.register', compact('plans', 'selectedPlan'));
    }

    // ── Step 2: create the workspace ────────────────────────────────────────
    //
    // Signup is three fields: company, email, password. The workspace and its
    // admin user are created in one transaction — no email verification step.

    public function store(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|string|min:8',
            // PROC-025: retired plans (is_active=false) must not be selectable at
            // signup even if their id is guessed or reused from an old link.
            'plan_id'      => ['required', Rule::exists('plans', 'id')->where('is_active', true)],
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $slug = $this->generateSlug($request->company_name);

        // The trial is a property of the plan now, not a global setting: the
        // super admin turns it on per plan and sets its length. A plan with no
        // trial bills from day one — trial_ends_at lands on now(), so
        // CheckSubscription sends the new admin straight to /billing.
        $trialDays = $plan->trialDays();

        DB::beginTransaction();
        try {
            $tenant = Tenant::create([
                'name'                => $request->company_name,
                'slug'                => $slug,
                'plan_id'             => $plan->id,
                'subscription_status' => 'trial',
                'trial_ends_at'       => now()->addDays($trialDays),
                // Recorded so a later switch back to this plan cannot mint a
                // second free trial on it.
                'trialed_plan_ids'    => $trialDays > 0 ? [$plan->id] : [],
                'is_active'           => true,
            ]);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name'      => $request->company_name,
                'email'     => $request->email,
                'password'  => Hash::make($request->password),
                'role'      => 'admin',
                'is_active' => true,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Registration failed', ['error' => $e->getMessage()]);

            return back()
                ->withInput($request->except(['password']))
                ->withErrors(['general' => __('auth.register.server_error')]);
        }

        // Sends Laravel's signed verification link to the address the user
        // typed — proves ownership before the workspace, trial and billing
        // correspondence attached to it can be used. Panel routes are gated
        // by the 'verified' middleware; billing/profile stay reachable so a
        // lapsed or unverified admin can still pay or fix a wrong email.
        rescue(fn () => event(new Registered($user)));

        Auth::login($user);

        // With the gate off (no working mail provider) the notice page has no
        // link coming to it, so send the new admin straight into the panel.
        $destination = config('auth.require_email_verification')
            ? 'verification.notice'
            : $user->homeRouteName();

        return redirect()->route($destination)
            ->with('success', __('auth.register.welcome_trial'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function generateSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $i    = 1;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
