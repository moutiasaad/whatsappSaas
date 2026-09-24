<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Services\Security\Turnstile;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * AuthApiController (Server B — app.wavadesk.com)
 *
 * Register / login endpoints called by the marketing app (Server A /
 * wavadesk.com) so a user typed into the landing-page form ends up as a
 * workspace row in this DB, with a Sanctum PAT the caller can carry.
 *
 * The token is only half of the SSO story: the caller trades it for a
 * short-lived signed handoff code and redirects the browser through
 * SsoHandoffController::redeem() so the token itself never enters a URL.
 * See app/Services/Auth/SsoHandoffCode.php for the format.
 *
 * Mirrors the shape of the two-step web signup in
 * app/Http/Controllers/Auth/RegisterController.php: register creates the
 * workspace with plan_id=NULL and returns next_step="plan"; the client
 * then routes the user through the plan picker on Server B via SSO.
 */
class AuthApiController extends Controller
{
    /**
     * POST /api/v1/auth/register
     *
     * Body: { company_name, email, password, plan_id? }
     * Returns: { token, user, tenant, next_step }
     */
    public function register(Request $request): JsonResponse
    {
        // The signup form lives on the marketing host, which forwards the
        // visitor's challenge token rather than checking it: the secret key
        // belongs on the box that owns the users table, and this is that box.
        // A 422 keyed on `general` is what the marketing form knows how to
        // put in front of the visitor.
        if (! Turnstile::passes($request->input(Turnstile::FIELD), $request->ip())) {
            return response()->json([
                'errors' => ['general' => [__('auth.register.challenge_failed')]],
            ], 422);
        }

        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|string|min:8',
            // plan_id is a hint only. It is never persisted on the tenant here
            // (same as the web signup) and is handed straight back so the
            // caller can pre-select that card on the plan picker.
            //
            // Deliberately NOT validated against the plans table: the caller is
            // the marketing site, whose pricing page may list an id this
            // database has since retired. Rejecting it would fail an otherwise
            // valid signup over a value nothing acts on. The plan picker
            // re-validates exists+is_active before anything is granted.
            'plan_id'      => ['nullable', 'integer'],
        ]);

        $tenant = null;
        $user   = null;

        DB::beginTransaction();
        try {
            $tenant = Tenant::create([
                'name'                => $data['company_name'],
                'slug'                => $this->generateSlug($data['company_name']),
                'plan_id'             => null,
                'subscription_status' => 'trial',
                'trial_ends_at'       => null,
                'trialed_plan_ids'    => [],
                'is_active'           => true,
            ]);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name'      => $data['company_name'],
                'email'     => $data['email'],
                'password'  => Hash::make($data['password']),
                'role'      => 'admin',
                'is_active' => true,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('API v1 registration failed', [
                'error' => $e->getMessage(),
                'email' => $data['email'],
            ]);
            return response()->json(['message' => 'Registration failed.'], 500);
        }

        // Fire Laravel's Registered event so the verification mail path stays
        // identical to the web signup — Server A never touches the mailer.
        rescue(fn () => event(new Registered($user)));

        return response()->json([
            'token'     => $user->createToken('sso:marketing')->plainTextToken,
            'user'      => $this->userPayload($user),
            'tenant'    => $this->tenantPayload($tenant),
            // Signals to Server A whether to route through the plan picker or
            // straight to the panel. Registration always lands on 'plan' since
            // plan_id is NULL by design; login can return 'dashboard'.
            'next_step' => 'plan',
            // Echoed so the handoff can carry the card the user clicked on the
            // pricing page through to the plan picker. A hint, not a grant.
            'plan_hint' => isset($data['plan_id']) ? (int) $data['plan_id'] : null,
        ], 201);
    }

    /**
     * POST /api/v1/auth/login
     *
     * Body: { email, password }
     * Returns: { token, user, tenant, next_step }
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // Checked by hand rather than with Auth::attempt(). routes/api.php is
        // loaded inside the `web` middleware group, so attempt() would open a
        // real session on this host for the caller's throwaway cookie jar and
        // then need logging out again on every rejection branch below. The
        // caller wants a token, not a session.
        /** @var User|null $user */
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.errors.credentials_mismatch'),
            ])->status(422);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Account disabled.'], 403);
        }

        // Archived user — same treatment as blocked tenant on the marketing
        // side: 403 with a distinct code so client apps can tell them apart.
        // Marketing's attemptLoginViaCoreApi renders the message on the
        // email field of wavadesk.com/login.
        if (method_exists($user, 'isArchived') && $user->isArchived()) {
            return response()->json([
                'message' => __('auth.errors.account_archived'),
                'code'    => 'account_archived',
            ], 403);
        }

        // Marketing-origin logins are always workspace logins — the platform
        // super-admin has a dedicated portal on Server B and never signs in
        // through wavadesk.com. Rejecting here keeps the two doors separate.
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return response()->json([
                'message' => __('auth.errors.use_control_panel_login'),
            ], 403);
        }

        // Tenant guards — refuse before minting a token. Archive checked
        // first because it's the more terminal state (an archived tenant
        // may or may not also be blocked; archive wins the message).
        // Marketing's attemptLoginViaCoreApi renders the 403 message as
        // the email field's error, so both copies show on wavadesk.com/login.
        $tenant = $user->tenant;

        if ($tenant && $tenant->archived_at) {
            return response()->json([
                'message' => __('auth.errors.workspace_archived'),
                'code'    => 'workspace_archived',
            ], 403);
        }

        if ($tenant
            && ! $tenant->is_active
            && in_array($tenant->subscription_status, ['active', 'trial'], true)) {
            return response()->json([
                'message' => __('auth.errors.workspace_blocked'),
                'code'    => 'workspace_blocked',
            ], 403);
        }

        return response()->json([
            'token'     => $user->createToken('sso:marketing')->plainTextToken,
            'user'      => $this->userPayload($user),
            'tenant'    => $this->tenantPayload($user->tenant),
            'next_step' => $user->tenant?->plan_id ? 'dashboard' : 'plan',
            'plan_hint' => null,
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     *
     * Revokes the token used to make this request. Sanctum middleware puts it
     * on `currentAccessToken()`.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * GET /api/v1/auth/me
     *
     * Small helper Server A can use to validate a stored token and rehydrate
     * the user object without a full re-login.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user'   => $this->userPayload($user),
            'tenant' => $this->tenantPayload($user->tenant),
        ]);
    }

    /**
     * Response payload for a user row. Kept minimal on purpose — anything the
     * marketing site actually needs (name/email/role/home route) and nothing
     * more, so a leaked API response cannot dump internal columns.
     */
    private function userPayload(User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'role'       => $user->role,
            'home_route' => method_exists($user, 'homeRouteName')
                ? $user->homeRouteName()
                : null,
        ];
    }

    private function tenantPayload(?Tenant $tenant): ?array
    {
        if (! $tenant) {
            return null;
        }

        return [
            'id'                  => $tenant->id,
            'name'                => $tenant->name,
            'slug'                => $tenant->slug,
            'plan_id'             => $tenant->plan_id,
            'subscription_status' => $tenant->subscription_status,
            'trial_ends_at'       => $tenant->trial_ends_at?->toIso8601String(),
            'is_active'           => (bool) $tenant->is_active,
        ];
    }

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
