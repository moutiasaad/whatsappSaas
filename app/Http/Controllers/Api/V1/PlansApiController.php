<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * PlansApiController (Server B — app.wavadesk.com)
 *
 * Powers the marketing site's plan picker without a shared database.
 * `wavadesk.com` renders the picker itself; the two endpoints below let it
 * read the catalog and commit a pick against the same authoritative rows the
 * web signup writes to (`RegisterController::plan` and `::choosePlan`).
 *
 * Guarded by `wavadesk.caller`, the shared-secret gate. A pick that grants a
 * plan also needs `auth:sanctum` — the marketing box carries the user's PAT
 * from the SSO handoff and forwards it here as a Bearer token.
 *
 * Registered only on the core role (see routes/api.php). On a marketing host
 * these endpoints do not exist at all.
 *
 * @see \App\Http\Controllers\Auth\RegisterController::plan
 * @see \App\Http\Controllers\Auth\RegisterController::choosePlan
 */
class PlansApiController extends Controller
{
    /**
     * GET /api/v1/plans
     *
     * Public catalog for the marketing plan picker. No user auth: the
     * `wavadesk.caller` gate on the group already scopes the endpoint to the
     * marketing app, and the response has nothing per-user in it.
     *
     * Returns only active plans in id order so a retired plan's card cannot
     * be re-clicked from a stale marketing page.
     */
    public function index(): JsonResponse
    {
        $plans = Plan::where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(fn (Plan $plan) => $this->planPayload($plan))
            ->values();

        return response()->json(['plans' => $plans]);
    }

    /**
     * POST /api/v1/plans/choose
     *
     * Commits the pick made on the marketing plan picker. Mirrors the exact
     * shape of `RegisterController::choosePlan` — a plan with an unused trial
     * (or a $0 plan) activates on the spot, everything else returns
     * `next_step=checkout` so the caller can route the user to a hosted
     * checkout flow. `plan_id` is only written for the free/trial branch, so
     * an abandoned checkout cannot leave a paid plan attached for free.
     *
     * Body: { plan_id: int }
     * Returns:
     *   200 { next_step: 'dashboard', tenant: {...}, home_route: str }        (free/trial activated)
     *   200 { next_step: 'checkout',  tenant: {...}, plan_id: int }           (paid, needs hosted checkout)
     *   422 (validation)                                                       (unknown/inactive plan)
     *   403                                                                    (auth ok but no tenant)
     *   409 { next_step: 'dashboard', message: str }                           (plan already picked)
     */
    public function choose(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Deliberately validates against active plans — a retired id
            // guessed from an old marketing page rejects at the boundary.
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
        ]);

        $user   = $request->user();
        $tenant = $user?->tenant;

        if (! $tenant) {
            return response()->json(['message' => 'No workspace attached to this token.'], 403);
        }

        // Already settled: don't overwrite. Same rule as the web picker —
        // re-picking belongs on /billing after signup, not here.
        if ($tenant->plan_id) {
            return response()->json([
                'next_step'  => 'dashboard',
                'message'    => 'Plan already chosen.',
                'home_route' => method_exists($user, 'homeRouteName') ? $user->homeRouteName() : null,
                'tenant'     => $this->tenantPayload($tenant),
            ], 409);
        }

        $plan = Plan::findOrFail($data['plan_id']);

        $isFree = ! $plan->price_monthly || (float) $plan->price_monthly === 0.0;

        // Free plan, OR a paid plan the tenant has never trialed: grant now.
        // Same branch condition as the web signup so a marketing pick can
        // never grant something the single-host flow would have gated.
        if ($isFree || ($plan->hasTrial() && ! $tenant->hasTrialedPlan($plan->id))) {
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
                'source'     => 'api.v1.plans.choose',
            ]);

            return response()->json([
                'next_step'  => 'dashboard',
                'tenant'     => $this->tenantPayload($tenant->fresh()),
                'home_route' => method_exists($user, 'homeRouteName') ? $user->homeRouteName() : null,
            ]);
        }

        // Paid plan with no trial left to give. Do not write plan_id — that
        // happens in the checkout capture path, so an abandoned payment
        // cannot leave the workspace on a plan nobody paid for.
        return response()->json([
            'next_step' => 'checkout',
            'plan_id'   => $plan->id,
            'tenant'    => $this->tenantPayload($tenant),
        ]);
    }

    /**
     * Response payload for a plan card. Same fields the marketing picker
     * needs — nothing operational (feature flags, module lists) that a leaked
     * response would let a stranger reason about.
     */
    private function planPayload(Plan $plan): array
    {
        return [
            'id'                => $plan->id,
            'name'              => $plan->name,
            'price_monthly'     => (float) $plan->price_monthly,
            'price_annual'      => (float) $plan->price_annual,
            'trial_enabled'     => (bool)  $plan->trial_enabled,
            'trial_days'        => $plan->trialDays(),
            'has_trial'         => $plan->hasTrial(),
            'is_free'           => ! $plan->price_monthly || (float) $plan->price_monthly === 0.0,
        ];
    }

    /**
     * Tenant payload — same shape AuthApiController returns so the marketing
     * app can decode a plans/choose response the same way it decodes a
     * register/login response.
     */
    private function tenantPayload($tenant): ?array
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
}
