<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            'plan_id'      => 'required|exists:plans,id',
        ]);

        $plan   = Plan::findOrFail($request->plan_id);
        $isFree = !$plan->price_monthly || (float) $plan->price_monthly === 0.0;
        $slug   = $this->generateSlug($request->company_name);

        DB::beginTransaction();
        try {
            $tenant = Tenant::create([
                'name'                => $request->company_name,
                'slug'                => $slug,
                'plan_id'             => $plan->id,
                'subscription_status' => $isFree ? 'trial' : 'suspended',
                'trial_ends_at'       => $isFree ? now()->addDays((int) config('app.trial_days', 14)) : null,
                'is_active'           => $isFree,
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

        if ($isFree) {
            Auth::login($user);

            return redirect()->route('tenant_admin.dashboard')
                ->with('success', __('auth.register.welcome_trial'));
        }

        // Paid plan — the workspace stays suspended until the payment lands.
        session(['_pending_register_user' => $user->id]);

        return redirect()->route('payment.checkout', ['tenant' => $tenant->id]);
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
