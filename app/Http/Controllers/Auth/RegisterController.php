<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\SsoHandoffCode;
use App\Services\WavadeskApi;
use App\Support\Wavadesk;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    // ── Step 1: show registration form ──────────────────────────────────────

    public function show(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route(auth()->user()->homeRouteName());
        }

        // A plan picked on the landing page is only a hint here: it survives
        // the form as a hidden field and pre-selects the card on step 2. The
        // workspace is still created plan-less, so an old or tampered ?plan=
        // can never grant a plan nobody paid or started a trial for.
        $plans = Plan::where('is_active', true)->orderBy('id')->get();

        $intendedPlan = $plans->firstWhere('id', (int) $request->get('plan'));

        // The trial promised on step 1 is the one step 2 can actually grant,
        // so it is read off the plan rather than hardcoded in the copy.
        $trialDays = $plans->first(fn (Plan $p) => $p->hasTrial())?->trialDays()
            ?? (int) config('app.trial_days', 7);

        return view('auth.register', compact('intendedPlan', 'trialDays'));
    }

    // ── Step 1 submit: create the workspace ─────────────────────────────────
    //
    // Signup is three fields: company, email, password. The workspace and its
    // admin user are created in one transaction — no email verification step.
    //
    // No plan is attached here on purpose. The account exists first, and the
    // new admin picks a plan on step 2 (/register/plan), where the trial plan
    // starts instantly and a paid one goes through checkout. `plan_id` stays
    // NULL until one of those two completes, which is what CheckSubscription
    // reads to keep an un-planned workspace out of the panel.

    public function store(Request $request)
    {
        // wavadesk.com owns no identity: the same form posts to the same route,
        // but the row is created in the core app's database over HTTP and the
        // browser is handed off to app.wavadesk.com already signed in.
        if (Wavadesk::isMarketing()) {
            return $this->storeViaCoreApi($request);
        }

        $request->validate([
            'company_name' => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|string|min:8',
            // Carried from a landing-page pricing card. A hint for step 2 only
            // — never persisted here — so a stale or retired id costs nothing.
            'plan_id'      => ['nullable', Rule::exists('plans', 'id')->where('is_active', true)],
        ]);

        $slug = $this->generateSlug($request->company_name);

        DB::beginTransaction();
        try {
            $tenant = Tenant::create([
                'name'                => $request->company_name,
                'slug'                => $slug,
                'plan_id'             => null,
                'subscription_status' => 'trial',
                'trial_ends_at'       => null,
                'trialed_plan_ids'    => [],
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

        // Step 2. Email verification still gates the panel itself; it must not
        // gate the plan picker, or a workspace whose verification mail never
        // arrived could never be paid for either.
        return redirect()->route('register.plan', array_filter([
            'plan' => (int) $request->input('plan_id') ?: null,
        ]))->with('success', __('auth.register.account_created'));
    }

    // ── Step 2: pick a plan ──────────────────────────────────────────────────
    //
    // Reached with the workspace already created and the admin signed in, so
    // an abandoned checkout costs the account nothing — they come back to this
    // page and either start the trial or pay.

    public function plan(Request $request)
    {
        $user   = $request->user();
        $tenant = $user->tenant;

        // Plan already settled (trial started or subscription paid): nothing
        // left to choose here, and re-picking belongs on /billing.
        if ($tenant?->plan_id) {
            return redirect()->route($user->homeRouteName());
        }

        $plans = Plan::where('is_active', true)->orderBy('id')->get();

        abort_if($plans->isEmpty(), 404);

        $selectedPlan = $plans->firstWhere('id', (int) $request->get('plan'))
            ?? $plans->first(fn (Plan $p) => $p->hasTrial())
            ?? $plans->first();

        return view('auth.register-plan', compact('plans', 'selectedPlan', 'tenant'));
    }

    /**
     * Commit the choice made on step 2.
     *
     * A plan that offers a free trial is activated on the spot — that is the
     * "start for free" button. Everything else is carried into checkout and
     * only lands on the tenant once the payment captures, which is why
     * plan_id is not written here for the paid branch.
     */
    public function choosePlan(Request $request)
    {
        $request->validate([
            // PROC-025: retired plans (is_active=false) must not be selectable
            // at signup even if their id is guessed or reused from an old link.
            'plan_id' => ['required', Rule::exists('plans', 'id')->where('is_active', true)],
        ]);

        $user   = $request->user();
        $tenant = $user->tenant;
        abort_unless($tenant, 403);

        if ($tenant->plan_id) {
            return redirect()->route($user->homeRouteName());
        }

        $plan = Plan::findOrFail($request->plan_id);

        $isFree = !$plan->price_monthly || (float) $plan->price_monthly === 0.0;

        if ($isFree || ($plan->hasTrial() && !$tenant->hasTrialedPlan($plan->id))) {
            $trialDays = $plan->trialDays();

            $tenant->markPlanTrialed($plan->id);
            $tenant->forceFill([
                'plan_id'              => $plan->id,
                'subscription_status'  => $isFree && $trialDays === 0 ? 'active' : 'trial',
                'trial_ends_at'        => $trialDays > 0 ? now()->addDays($trialDays) : null,
                'subscription_ends_at' => null,
                'is_active'            => true,
            ])->save();

            AuditLog::record('tenant.trial_started', $tenant, [
                'plan_id'    => $plan->id,
                'trial_days' => $trialDays,
                'source'     => 'signup',
            ]);

            $destination = config('auth.require_email_verification')
                ? 'verification.notice'
                : $user->homeRouteName();

            return redirect()->route($destination)
                ->with('success', $trialDays > 0
                    ? __('auth.register.welcome_trial')
                    : __('auth.register.welcome_free'));
        }

        // Paid plan, no trial left to give: straight to checkout. plan_id stays
        // NULL until the capture activates it, so an abandoned payment cannot
        // hand out a plan for free.
        return redirect()->route('payment.checkout', [
            'tenant'  => $tenant->id,
            'plan_id' => $plan->id,
        ]);
    }

    // ── Marketing role (Server A / wavadesk.com) ─────────────────────────────

    /**
     * Proxy the register form to the core app, then hand the browser off.
     *
     * Validation here is deliberately the loose half: shape only (present, an
     * email, long enough), with no `unique:users` and no `exists:plans` rule,
     * because the marketing app is not the authority on either table. The core
     * app re-validates against the database that actually owns those rows and
     * its 422 comes back as field-level errors on this very form.
     */
    private function storeViaCoreApi(Request $request)
    {
        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'email'        => 'required|email|max:255',
            'password'     => 'required|string|min:8',
            'plan_id'      => 'nullable|integer',
        ]);

        $result = app(WavadeskApi::class)->register($data);

        if (! $result['ok']) {
            // A 422 from the core app is a real field error (email taken, weak
            // password) and belongs on the field. Anything else is our problem,
            // not the user's, so it renders as a generic retry message.
            $this->rethrowCoreValidation($result);

            Log::warning('Marketing -> core register failed', [
                'status' => $result['status'],
                'email'  => $data['email'],
            ]);

            return back()
                ->withInput($request->except('password'))
                ->withErrors(['general' => __('auth.register.server_error')]);
        }

        return $this->handoff($request, $result['body'])
            ?? back()
                ->withInput($request->except('password'))
                ->withErrors(['general' => __('auth.register.server_error')]);
    }

    /**
     * Stash the core app's token in the (encrypted, server-side) session and
     * redirect through the single-use handoff code.
     *
     * Returns null when the response was well-formed JSON but missing the two
     * fields the handoff needs, so the caller can render its own error rather
     * than redirect to a code that cannot be minted.
     */
    private function handoff(Request $request, array $body)
    {
        $userId = (int) ($body['user']['id'] ?? 0);
        $token  = (string) ($body['token'] ?? '');

        if ($userId === 0 || $token === '') {
            Log::error('Core app returned a register/login success with no token or user id');

            return null;
        }

        // The PAT stays here, server-side, so later marketing-side pages
        // (billing, account) can call the core app on the user's behalf. It is
        // never put in a URL and never handed to the browser.
        $request->session()->put(Wavadesk::SESSION_TOKEN,  $token);
        $request->session()->put(Wavadesk::SESSION_USER,   $body['user'] ?? []);
        $request->session()->put(Wavadesk::SESSION_TENANT, $body['tenant'] ?? []);

        $code = app(SsoHandoffCode::class)->mint($userId);

        // The plan the user clicked on the pricing page rides along unsigned,
        // exactly as it does in the single-host flow's /register/plan?plan=N.
        // It only pre-selects a card; the plan picker re-validates exists and
        // is_active before granting anything, so a tampered value buys nothing.
        $query = ['code' => $code];

        if ($hint = (int) ($body['plan_hint'] ?? 0)) {
            $query['plan'] = $hint;
        }

        return redirect()->away(
            Wavadesk::coreUrlTo('/auth/sso') . '?' . http_build_query($query)
        );
    }

    /**
     * Re-raise the core app's 422 as a local ValidationException so an "email
     * already taken" error lands on the email field exactly as it did when
     * this form ran against a local database.
     */
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
