<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\CloseIdleAiConversations;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Messenger\MetaConfig;
use App\Support\AddonPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class SuperAdminPlatformController extends Controller
{
    public function tenants(Request $request)
    {
        $baseQuery = Tenant::query()
            ->withCount(['users', 'teams', 'whatsappInstances as instances_count'])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($request->status,  fn ($q, $status) => $q->where('subscription_status', $status))
            ->when($request->plan_id, fn ($q, $planId) => $q->where('plan_id', $planId))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->is_active === '1'))
            // Created-date range. Inclusive on both ends via whereDate so
            // typing "2026-09-16" matches rows created that day regardless
            // of the timestamp's HH:MM:SS.
            ->when($request->filled('created_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->query('created_from')))
            ->when($request->filled('created_to'),   fn ($q) => $q->whereDate('created_at', '<=', $request->query('created_to')))
            // Plan-ends range. Which column matters depends on subscription
            // lifecycle (trial_ends_at for trials, subscription_ends_at for
            // paid) — same split project_trial_lifecycle_semantics.md
            // documents. Query mirrors that so a "renewing in 30 days"
            // preset doesn't pick up trial expiries by accident.
            ->when($request->filled('ends_from') || $request->filled('ends_to'), function ($q) use ($request) {
                $from = $request->query('ends_from');
                $to   = $request->query('ends_to');
                $q->where(function ($outer) use ($from, $to) {
                    $outer->where(function ($qq) use ($from, $to) {
                        $qq->where('subscription_status', 'trial');
                        if ($from) $qq->whereDate('trial_ends_at', '>=', $from);
                        if ($to)   $qq->whereDate('trial_ends_at', '<=', $to);
                    })->orWhere(function ($qq) use ($from, $to) {
                        $qq->where('subscription_status', 'active');
                        if ($from) $qq->whereDate('subscription_ends_at', '>=', $from);
                        if ($to)   $qq->whereDate('subscription_ends_at', '<=', $to);
                    });
                });
            })
            // Archived filter: default (unset) → hide archived rows.
            // ?archived=1 → only archived. ?archived=all → both.
            ->when(true, function ($q) use ($request) {
                $filter = (string) $request->query('archived', '');
                if ($filter === '1')   { $q->whereNotNull('archived_at'); return; }
                if ($filter === 'all') { return; }
                $q->whereNull('archived_at');
            });

        $stats = [
            'total'    => (clone $baseQuery)->count(),
            'active'   => (clone $baseQuery)->where('subscription_status', 'active')->count(),
            'trial'    => (clone $baseQuery)->where('subscription_status', 'trial')->count(),
            'inactive' => (clone $baseQuery)->where('is_active', false)->count(),
        ];

        if ($request->expectsJson()) {
            $paginated = (clone $baseQuery)
                ->with('plan:id,name')
                ->orderBy('name')
                ->paginate((int) ($request->integer('per_page') ?: 20));

            return response()->json(
                array_merge($paginated->toArray(), ['stats' => $stats])
            )->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        $plans = Plan::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('admin.platform.tenants', compact('plans'));
    }

    public function createTenant()
    {
        $plans = Plan::where('is_active', true)->orderBy('name')->get();

        return view('admin.platform.tenants-create', compact('plans'));
    }

    public function storeTenant(Request $request)
    {
        $data = $this->validateTenant($request);

        DB::transaction(function () use ($data) {
            $slug = $this->buildUniqueSlug($data['slug'] ?? $data['name']);

            $tenant = Tenant::create([
                'name'                => $data['name'],
                'slug'                => $slug,
                'plan_id'                 => $data['plan_id'] ?? null,
                'subscription_status'    => $data['subscription_status'],
                'subscription_starts_at' => $data['subscription_starts_at'] ?? null,
                'subscription_ends_at'   => $data['subscription_ends_at'] ?? null,
                'stripe_id'              => $data['stripe_id'] ?? null,
                'settings'               => $this->parseSettings($data['settings'] ?? null),
                'timezone'               => $data['timezone'] ?: 'UTC',
                'is_active'              => (bool) ($data['is_active'] ?? true),
            ]);

            if (!empty($data['admin_email']) && !empty($data['admin_password'])) {
                User::create([
                    'tenant_id' => $tenant->id,
                    'name'      => $data['admin_name'] ?: ($tenant->name . ' Admin'),
                    'email'     => $data['admin_email'],
                    'password'  => Hash::make($data['admin_password']),
                    'role'      => 'admin',
                    'is_active' => true,
                    // Super-admin-created admin skips verification (PROC-024).
                    'email_verified_at' => now(),
                ]);
            }
        });

        return redirect()->route('super_admin.platform.tenants')->with('success', __('ui.controller_messages.tenant_created'));
    }

    public function showTenant(Tenant $tenant)
    {
        $tenant->loadCount(['users', 'teams', 'whatsappInstances as instances_count', 'customers', 'conversations']);
        $tenant->load([
            'plan:id,name,price_monthly',
            'users' => fn ($query) => $query->latest()->limit(5),
        ]);

        $payments = \App\Models\TenantPayment::where('tenant_id', $tenant->id)
            ->with('plan:id,name')
            ->latest('created_at')
            ->limit(20)
            ->get();

        // Fraud-detection sidebar: other tenants this one shares a WhatsApp
        // number with (or, in future, an email domain / payment card /
        // signup IP). See TenantLink model + InstanceController::recordTenantLinksForPhone
        // for how these rows get written.
        $linkedTenants = $tenant->linkedTenants();

        // Claude API cost block. Aggregates come from the ai_api_usages
        // ledger (App\Services\AI\UsageTracker records one row per call,
        // cost snapshotted at record time). Same SQL pattern used on the
        // platform-wide report at /platform/claude-usage.
        $claudeBase = \App\Models\AiApiUsage::where('tenant_id', $tenant->id);

        $claudeLifetime = (clone $claudeBase)
            ->selectRaw('COUNT(*) as calls')
            ->selectRaw('COALESCE(SUM(input_tokens), 0)  as in_tok')
            ->selectRaw('COALESCE(SUM(output_tokens), 0) as out_tok')
            ->selectRaw('COALESCE(SUM(cost_usd), 0)      as cost')
            ->first();

        $claude30d = (clone $claudeBase)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('COUNT(*) as calls')
            ->selectRaw('COALESCE(SUM(cost_usd), 0) as cost')
            ->first();

        $claudeByModel = (clone $claudeBase)
            ->selectRaw('model')
            ->selectRaw('COUNT(*) as calls')
            ->selectRaw('COALESCE(SUM(cost_usd), 0) as cost')
            ->groupBy('model')
            ->orderByDesc('cost')
            ->get();

        $claudeBySource = (clone $claudeBase)
            ->selectRaw('source')
            ->selectRaw('COUNT(*) as calls')
            ->selectRaw('COALESCE(SUM(cost_usd), 0) as cost')
            ->groupBy('source')
            ->orderByDesc('cost')
            ->get();

        $claudeRecent = (clone $claudeBase)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        // Extension history — every AuditLog row where the target is this
        // tenant AND the action is a plan extension. Rendered as a small
        // "Recent extensions" card next to the plan info so a super admin
        // sees the extension trail without leaving the tenant page.
        $planExtensions = \App\Models\AuditLog::query()
            ->where('target_type', 'Tenant')
            ->where('target_id', $tenant->id)
            ->whereIn('action', ['tenant.plan_extended', 'tenant.plan_date_set'])
            ->with('user:id,name,email')
            ->latest('created_at')
            ->limit(5)
            ->get();

        return view('admin.platform.tenants-show', compact(
            'tenant', 'payments', 'linkedTenants',
            'claudeLifetime', 'claude30d', 'claudeByModel', 'claudeBySource', 'claudeRecent',
            'planExtensions'
        ));
    }

    public function editTenant(Tenant $tenant)
    {
        $tenant->loadCount(['users', 'teams', 'whatsappInstances as instances_count']);
        $plans = Plan::where('is_active', true)->orderBy('name')->get();
        $tenantAdmin = User::where('tenant_id', $tenant->id)
            ->where('role', 'admin')
            ->orderBy('id')
            ->first();

        $lastPayment   = null;
        $suggestStart  = null;
        $suggestEnd    = null;

        // Trials are bounded by trial_ends_at, not subscription_ends_at, so
        // suggesting a paid-cycle end date for them would pollute the field
        // and make the tenant look "expired" while the trial is still live.
        if ($tenant->subscription_status !== 'trial'
            && (!$tenant->subscription_starts_at || !$tenant->subscription_ends_at)) {
            $lastPayment = \App\Models\TenantPayment::where('tenant_id', $tenant->id)
                ->where('status', 'completed')
                ->latest('paid_at')
                ->first();

            if ($lastPayment?->paid_at) {
                $suggestStart = $lastPayment->paid_at->format('Y-m-d');
                $suggestEnd   = $lastPayment->paid_at->copy()->addMonth()->format('Y-m-d');
            } else {
                // No payment records — fall back to tenant creation date
                $suggestStart = $tenant->created_at->format('Y-m-d');
                $suggestEnd   = $tenant->created_at->copy()->addMonth()->format('Y-m-d');
            }
        }

        return view('admin.platform.tenants-edit', compact('tenant', 'plans', 'tenantAdmin', 'lastPayment', 'suggestStart', 'suggestEnd'));
    }

    public function updateTenant(Request $request, Tenant $tenant)
    {
        $data = $this->validateTenant($request, $tenant);

        DB::transaction(function () use ($data, $tenant) {
            $slug = $this->buildUniqueSlug($data['slug'] ?? $data['name'], $tenant->id);

            $endsAt = !empty($data['subscription_ends_at'])
                ? \Carbon\Carbon::parse($data['subscription_ends_at'])
                : null;

            // Auto-sync status with the end date
            $status = $data['subscription_status'];
            if ($tenant->subscription_status === 'trial'
                && $tenant->trial_ends_at
                && $tenant->trial_ends_at->isFuture()) {
                // A running trial must survive edits to unrelated fields; to end it,
                // set trial_ends_at to a past date rather than flipping status here.
                $status = 'trial';
            } elseif ($endsAt && $endsAt->isPast()) {
                // End date is in the past → force suspended regardless of what was selected
                $status = 'suspended';
            } elseif ($endsAt && $endsAt->isFuture() && $tenant->subscription_status === 'suspended') {
                // Admin set a future end date on a suspended tenant → reactivate
                $status = 'active';
            }

            $tenant->update([
                'name'                   => $data['name'],
                'slug'                   => $slug,
                'plan_id'                => $data['plan_id'] ?? null,
                'subscription_status'    => $status,
                'subscription_starts_at' => $data['subscription_starts_at'] ?? null,
                'subscription_ends_at'   => $endsAt,
                'stripe_id'              => $data['stripe_id'] ?? null,
                'settings'               => $this->parseSettings($data['settings'] ?? null),
                'timezone'               => $data['timezone'] ?: $tenant->timezone ?: 'UTC',
                'is_active'              => in_array($status, ['active', 'trial'], true),
            ]);

            if (!empty($data['admin_email']) || !empty($data['admin_password']) || !empty($data['admin_name'])) {
                $admin = User::where('tenant_id', $tenant->id)
                    ->where('role', 'admin')
                    ->orderBy('id')
                    ->first();

                if (!$admin) {
                    if (!empty($data['admin_email']) && !empty($data['admin_password'])) {
                        User::create([
                            'tenant_id' => $tenant->id,
                            'name'      => $data['admin_name'] ?: ($tenant->name . ' Admin'),
                            'email'     => $data['admin_email'],
                            'password'  => Hash::make($data['admin_password']),
                            'role'      => 'admin',
                            'is_active' => true,
                            // Super-admin-created admin skips verification (PROC-024).
                            'email_verified_at' => now(),
                        ]);
                    }
                    return;
                }

                $adminUpdates = [];
                if (!empty($data['admin_name'])) {
                    $adminUpdates['name'] = $data['admin_name'];
                }
                if (!empty($data['admin_email'])) {
                    $adminUpdates['email'] = $data['admin_email'];
                }
                if (!empty($data['admin_password'])) {
                    $adminUpdates['password'] = Hash::make($data['admin_password']);
                }
                if ($adminUpdates) {
                    $admin->update($adminUpdates);
                }
            }
        });

        return redirect()
            ->route('super_admin.platform.tenants.show', $tenant)
            ->with('success', __('ui.controller_messages.tenant_updated'));
    }

    public function destroyTenant(Request $request, Tenant $tenant)
    {
        $tenantName = $tenant->name;
        $tenant->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => __('ui.controller_messages.tenant_deleted', ['name' => $tenantName])]);
        }

        return redirect()
            ->route('super_admin.platform.tenants')
            ->with('success', __('ui.controller_messages.tenant_deleted', ['name' => $tenantName]));
    }

    /**
     * One-click "log in as this tenant" from the tenants list.
     *
     * Resolves the tenant's primary admin (oldest admin user, matching
     * how editTenant identifies "the tenant admin") and runs the
     * impersonation exactly the way UserController::impersonate does —
     * same ImpersonationLog row shape, same `impersonating` session
     * key, same AuditLog entry — so `/impersonate/leave` restores this
     * super-admin back to their own session with no extra plumbing.
     *
     * `is_active` on the admin is deliberately NOT part of the filter:
     * a tenant admin who's been disabled is exactly the case where a
     * super-admin most often needs to log in as them (to see what they
     * saw, or to re-enable them). editTenant's tenantAdmin lookup uses
     * the same shape — one source of truth for "who's the admin here".
     */
    public function impersonateTenantAdmin(Tenant $tenant)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        // Same query shape as editTenant()'s $tenantAdmin lookup so both
        // paths agree on who "the admin" is — never a footgun where
        // this button skips a row the edit form shows.
        $admin = User::where('tenant_id', $tenant->id)
            ->where('role', 'admin')
            ->orderBy('id')
            ->first();

        // Fallback for tenants with no admin row at all — shouldn't happen
        // in normal flow (register always creates one) but a super-admin
        // needs a way in regardless. Take the oldest user of any role
        // rather than 404-ing.
        if (! $admin) {
            $admin = User::where('tenant_id', $tenant->id)
                ->orderBy('id')
                ->first();
        }

        if (! $admin) {
            return back()->with('error', __('ui.controller_messages.tenant_has_no_active_admin'));
        }

        \App\Models\ImpersonationLog::create([
            'impersonator_user_id' => auth()->id(),
            'impersonated_user_id' => $admin->id,
            'tenant_id'            => $tenant->id,
            'started_at'           => now(),
            'ip_address'           => request()->ip(),
        ]);

        AuditLog::record('user.impersonated', $admin, ['source' => 'platform.tenants']);

        session(['impersonating' => auth()->id()]);
        auth()->login($admin);

        return redirect()->route($admin->homeRouteName());
    }

    /**
     * Block / unblock an entire tenant in one click.
     *
     * Flips tenants.is_active. When false, Tenant::isActive() returns false
     * and CheckSubscription (line ~41) denies every panel request for that
     * tenant's users — effectively logging them all out on their next hop.
     * When flipped back to true, the previous subscription_status decides
     * whether they can immediately resume (active/trial) or need to renew
     * (suspended/cancelled).
     *
     * subscription_status is deliberately NOT touched: block is orthogonal
     * to "is this tenant currently paying". A super-admin can block a paid
     * customer during an incident and unblock them without churning their
     * subscription lifecycle.
     */
    public function toggleTenantActive(Request $request, Tenant $tenant)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $wasActive = (bool) $tenant->is_active;
        $tenant->update(['is_active' => ! $wasActive]);

        AuditLog::record(
            $wasActive ? 'tenant.blocked' : 'tenant.unblocked',
            $tenant,
            ['source' => 'platform.tenants']
        );

        $message = $wasActive
            ? __('ui.controller_messages.tenant_blocked',   ['name' => $tenant->name])
            : __('ui.controller_messages.tenant_unblocked', ['name' => $tenant->name]);

        if ($request->expectsJson()) {
            return response()->json([
                'message'   => $message,
                'is_active' => (bool) $tenant->is_active,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Archive / restore an entire tenant.
     *
     * Different semantic from block:
     *   block     = temporary suspension, tenant stays in the main list,
     *               "suspended by administrator" copy
     *   archive   = long-term shelved, hidden from the main list (filter
     *               to view), "workspace archived" copy
     *
     * Both prevent login. Both preserve data. Both are reversible. Neither
     * touches subscription_status. Distinct columns so a tenant can be
     * blocked AND archived independently; the login/deny messages
     * prioritise archive since it's the more terminal state.
     */
    public function toggleTenantArchive(Request $request, Tenant $tenant)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $wasArchived = $tenant->isArchived();
        $wasArchived ? $tenant->restoreFromArchive() : $tenant->archive();

        AuditLog::record(
            $wasArchived ? 'tenant.unarchived' : 'tenant.archived',
            $tenant,
            ['source' => 'platform.tenants']
        );

        $message = $wasArchived
            ? __('ui.controller_messages.tenant_unarchived', ['name' => $tenant->name])
            : __('ui.controller_messages.tenant_archived',   ['name' => $tenant->name]);

        if ($request->expectsJson()) {
            return response()->json([
                'message'  => $message,
                'archived' => $tenant->isArchived(),
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Back-date trial_ends_at to yesterday so QA can immediately test the
     * post-trial behavior (panel gate, AI throttling, etc) without waiting
     * for the real end date. No-op unless the tenant is currently on trial:
     * an active/expired tenant has nothing here to back-date.
     */
    /**
     * Push a tenant's plan end date forward by N days.
     *
     * Which column moves depends on the current lifecycle state, per the split
     * documented in project_trial_lifecycle_semantics.md: trials extend
     * trial_ends_at, everyone else extends subscription_ends_at. Status is left
     * alone — a suspended tenant stays suspended even after the date moves;
     * reactivating a suspended tenant is a separate deliberate action.
     *
     * Base date is the *later of now() and the current end date*. That means an
     * expired trial extended by 7 days ends 7 days from today, not 7 days from
     * whenever the trial originally ran out — which is the "give them a week"
     * semantics operators actually mean.
     *
     * Every extension writes an AuditLog row with the previous date, the new
     * date, the day-count and the reason, so /admin-control-panel/audit-log is
     * the single source of truth for "who extended what and why".
     */
    public function extendTenantPlan(Request $request, Tenant $tenant)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'days'   => 'required|integer|min:1|max:365',
            'reason' => 'nullable|string|max:500',
        ]);

        // Which date column to move.
        $column = $tenant->subscription_status === 'trial'
            ? 'trial_ends_at'
            : 'subscription_ends_at';

        $current = $tenant->{$column};

        // "Extend from where we are" — later of now() vs. the stored end
        // date. Passing an expired date through addDays would land the
        // extension in the past.
        $baseDate = ($current && $current->isFuture()) ? $current : now();
        $newDate  = $baseDate->copy()->addDays((int) $data['days']);

        $previous = $current?->toIso8601String();

        $tenant->forceFill([$column => $newDate])->save();

        AuditLog::record('tenant.plan_extended', $tenant, [
            'source'      => 'platform.tenants',
            'column'      => $column,
            'days'        => (int) $data['days'],
            'previous'    => $previous,
            'new'         => $newDate->toIso8601String(),
            'reason'      => $data['reason'] ?? null,
            'status_at_extend' => $tenant->subscription_status,
        ]);

        return back()->with('success', __('ui.controller_messages.plan_extended', [
            'name' => $tenant->name,
            'days' => (int) $data['days'],
        ]));
    }

    /**
     * Replace a tenant's plan end date with a specific date the operator picks.
     *
     * Distinct from extendTenantPlan: extend adds N days on top of the current
     * end date (or now, whichever is later), this sets an absolute date. Both
     * write to the same column pair (trial_ends_at for trials, otherwise
     * subscription_ends_at) so the two paths stay symmetric — an operator can
     * flip between them without wondering which knob they're turning.
     *
     * Only forward dates are accepted (today or later). Back-dating already has
     * its own explicit route (expireTrial) and letting Set-date silently expire
     * a tenant would blur the intent.
     */
    public function setTenantPlanDate(Request $request, Tenant $tenant)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'end_date' => 'required|date|after_or_equal:today',
            'reason'   => 'nullable|string|max:500',
        ]);

        $column = $tenant->subscription_status === 'trial'
            ? 'trial_ends_at'
            : 'subscription_ends_at';

        $previous = $tenant->{$column}?->toIso8601String();
        $newDate  = \Carbon\Carbon::parse($data['end_date'])->endOfDay();

        $tenant->forceFill([$column => $newDate])->save();

        AuditLog::record('tenant.plan_date_set', $tenant, [
            'source'           => 'platform.tenants',
            'column'           => $column,
            'previous'         => $previous,
            'new'              => $newDate->toIso8601String(),
            'reason'           => $data['reason'] ?? null,
            'status_at_change' => $tenant->subscription_status,
        ]);

        return back()->with('success', __('ui.controller_messages.plan_date_set', [
            'name' => $tenant->name,
            'date' => $newDate->format('M j, Y'),
        ]));
    }

    public function expireTrial(Request $request, Tenant $tenant)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        if (! $tenant->isOnTrial()) {
            return back()->with('error', __('ui.controller_messages.trial_expire_not_on_trial', [
                'name' => $tenant->name,
            ]));
        }

        $previous = $tenant->trial_ends_at?->toIso8601String();

        $tenant->forceFill([
            'trial_ends_at' => now()->subDay(),
        ])->save();

        AuditLog::record('tenant.trial_expired_manually', $tenant, [
            'source'   => 'platform.tenants',
            'previous' => $previous,
            'new'      => $tenant->trial_ends_at?->toIso8601String(),
        ]);

        return back()->with('success', __('ui.controller_messages.trial_expired', [
            'name' => $tenant->name,
        ]));
    }

    public function bulkTenants(Request $request)
    {
        // Bulk archive is the only action wired to this endpoint on purpose:
        // disable and delete were removed because they're irreversible-ish
        // enough that they should require opening a specific tenant profile
        // (Danger zone on tenants-show handles them one at a time).
        $data = $request->validate([
            'action' => 'required|in:archive',
            'ids' => 'required|string',
        ]);

        $ids = collect(explode(',', $data['ids']))
            ->map(fn ($id) => (int) trim($id))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('ui.controller_messages.no_tenants_selected')], 422);
            }
            return back()->with('error', __('ui.controller_messages.no_tenants_selected'));
        }

        // Only archive tenants that aren't already archived, so the reported
        // count matches what actually changed and a second click on the bulk
        // button doesn't overwrite everyone's archived_at timestamp.
        $affected = Tenant::whereIn('id', $ids)
            ->whereNull('archived_at')
            ->update(['archived_at' => now()]);

        $message = __('ui.controller_messages.tenants_archived', ['count' => $affected]);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }

    public function plans(Request $request)
    {
        $baseQuery = Plan::query()
            ->withCount('tenants')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->is_active === '1'));

        $stats = [
            'total'    => (clone $baseQuery)->count(),
            'active'   => (clone $baseQuery)->where('is_active', true)->count(),
            'inactive' => (clone $baseQuery)->where('is_active', false)->count(),
            'assigned' => (clone $baseQuery)->has('tenants')->count(),
        ];

        if ($request->expectsJson()) {
            $paginated = (clone $baseQuery)
                ->orderBy('price_monthly')
                ->paginate((int) ($request->integer('per_page') ?: 20));

            return response()->json(
                array_merge($paginated->toArray(), ['stats' => $stats])
            )->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        return view('admin.platform.plans');
    }

    public function showPlan(Plan $plan)
    {
        $plan->loadCount('tenants');
        $plan->load([
            'tenants' => fn ($query) => $query
                ->withCount(['users', 'whatsappInstances as instances_count'])
                ->orderBy('name')
                ->limit(8),
        ]);

        return view('admin.platform.plans-show', compact('plan'));
    }

    public function createPlan()
    {
        return view('admin.platform.plans-create');
    }

    public function storePlan(Request $request)
    {
        $data = $this->validatePlan($request);

        Plan::create($this->planAttributes($data) + [
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()
            ->route('super_admin.platform.plans')
            ->with('success', __('ui.controller_messages.plan_created'));
    }

    public function editPlan(Plan $plan)
    {
        $plan->loadCount('tenants');
        $plan->load('countryPrices');

        $countries = \App\Models\Country::where('is_active', true)
            ->orderBy('name')
            ->get();

        // Index by country_code so the blade can hydrate each row's input
        // with the current value without an inline loop-search per country.
        $countryPrices = $plan->countryPrices->keyBy('country_code');

        return view('admin.platform.plans-edit', compact('plan', 'countries', 'countryPrices'));
    }

    /**
     * Save per-country prices for a plan. Row semantics:
     *   - Both monthly + annual blank OR zero → delete the row (falls back
     *     to base USD for that country).
     *   - Either non-zero → upsert.
     * Wrapped in a transaction so a bad payload doesn't leave the plan in
     * a half-migrated state.
     */
    public function updatePlanCountryPrices(Request $request, Plan $plan)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'prices'                    => 'nullable|array',
            'prices.*.price_monthly'    => 'nullable|numeric|min:0|max:999999',
            'prices.*.price_annual'     => 'nullable|numeric|min:0|max:999999',
        ]);

        $incoming = (array) ($data['prices'] ?? []);
        $validCountries = \App\Models\Country::pluck('code')->all();

        DB::transaction(function () use ($plan, $incoming, $validCountries) {
            foreach ($incoming as $code => $row) {
                $code = strtoupper((string) $code);
                if (! in_array($code, $validCountries, true)) {
                    continue;
                }

                $monthly = isset($row['price_monthly']) && $row['price_monthly'] !== '' ? (float) $row['price_monthly'] : null;
                $annual  = isset($row['price_annual'])  && $row['price_annual']  !== '' ? (float) $row['price_annual']  : null;

                // Both empty / zero → drop the row so the country falls back
                // to the plan's base USD price. Saves DB rows and makes the
                // "we don't localise price here" case explicit.
                if (($monthly === null || $monthly <= 0) && ($annual === null || $annual <= 0)) {
                    $plan->countryPrices()->where('country_code', $code)->delete();
                    continue;
                }

                \App\Models\PlanCountryPrice::updateOrCreate(
                    ['plan_id' => $plan->id, 'country_code' => $code],
                    ['price_monthly' => $monthly, 'price_annual' => $annual],
                );
            }
        });

        AuditLog::record('plan.country_prices_updated', $plan);
        app(\App\Support\CountriesRegistry::class)->forget();

        return back()->with('success', __('ui.platform_plans_edit_page.country_prices_saved'));
    }

    public function updatePlan(Request $request, Plan $plan)
    {
        $data = $this->validatePlan($request, $plan);
        $nextStatus = (bool) ($data['is_active'] ?? false);

        if (!$nextStatus && $plan->is_active && Plan::where('is_active', true)->where('id', '!=', $plan->id)->count() === 0) {
            return back()
                ->withInput()
                ->with('error', __('ui.controller_messages.cannot_disable_last_active_plan'));
        }

        $plan->update($this->planAttributes($data) + [
            'is_active' => $nextStatus,
        ]);

        // Sync AI quota to every tenant on this plan when it changed. Matches
        // the "Overwrite every tenant" behaviour the operator picked when this
        // feature landed — a per-tenant override is expected to be re-applied
        // by the super admin if they want it back.
        if ($plan->wasChanged('ai_message_quota')) {
            $tenantIds = \App\Models\Tenant::where('plan_id', $plan->id)->pluck('id');
            if ($tenantIds->isNotEmpty()) {
                \App\Models\AiSettings::whereIn('tenant_id', $tenantIds)
                    ->update(['monthly_message_quota' => $plan->ai_message_quota]);
            }
        }

        return redirect()
            ->route('super_admin.platform.plans.show', $plan)
            ->with('success', __('ui.controller_messages.plan_updated'));
    }

    public function togglePlanStatus(Request $request, Plan $plan)
    {
        $request->validate(['is_active' => 'required|boolean']);
        $nextStatus = $request->boolean('is_active');

        if (!$nextStatus && Plan::where('is_active', true)->where('id', '!=', $plan->id)->count() === 0) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('ui.controller_messages.cannot_disable_last_active_plan')], 422);
            }
            return redirect()
                ->route('super_admin.platform.plans')
                ->with('error', __('ui.controller_messages.cannot_disable_last_active_plan'));
        }

        $plan->update(['is_active' => $nextStatus]);

        if ($request->expectsJson()) {
            return response()->json([
                'message'   => $nextStatus ? __('ui.controller_messages.plan_enabled') : __('ui.controller_messages.plan_disabled'),
                'is_active' => $nextStatus,
            ]);
        }

        return redirect()
            ->route('super_admin.platform.plans')
            ->with('success', $nextStatus ? __('ui.controller_messages.plan_enabled') : __('ui.controller_messages.plan_disabled'));
    }

    public function bulkPlans(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:enable,disable',
            'ids' => 'required|string',
        ]);

        $ids = collect(explode(',', $data['ids']))
            ->map(fn ($id) => (int) trim($id))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('ui.controller_messages.no_plans_selected')], 422);
            }
            return back()->with('error', __('ui.controller_messages.no_plans_selected'));
        }

        $plans = Plan::whereIn('id', $ids)->get();
        $enable = $data['action'] === 'enable';

        if (!$enable) {
            $activeCount = Plan::where('is_active', true)->count();
            $activeSelected = $plans->where('is_active', true)->count();
            if ($activeSelected >= $activeCount) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => __('ui.controller_messages.cannot_disable_all_active_plans')], 422);
                }
                return back()->with('error', __('ui.controller_messages.cannot_disable_all_active_plans'));
            }
        }

        Plan::whereIn('id', $plans->pluck('id'))->update(['is_active' => $enable]);

        $message = $enable
            ? __('ui.controller_messages.plans_enabled', ['count' => $plans->count()])
            : __('ui.controller_messages.plans_disabled', ['count' => $plans->count()]);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }

    /**
     * Conversation automation the super admin owns platform-wide.
     *
     * Kept out of the per-tenant AI settings on purpose: this is a housekeeping
     * rule about abandoned chats, not part of a tenant's AI configuration, and
     * one window across the platform is what was asked for.
     */
    public function conversationSettings()
    {
        return view('admin.platform.conversation-settings', [
            'idleMinutes' => (int) PlatformSetting::get(
                CloseIdleAiConversations::SETTING_KEY,
                CloseIdleAiConversations::DEFAULT_MINUTES,
            ),
            'presets' => [0, 15, 30, 45, 60, 120, 240, 480, 1440],
        ]);
    }

    /**
     * The platform-wide default language for any visitor that hasn't picked
     * one yet (no `locale` in session). Stored in platform_settings so a
     * change lands live without a .env edit or config:cache. Each tenant's
     * users can still override for themselves through the language switcher
     * in the header — this key only decides the fallback.
     *
     * @see \App\Http\Middleware\SetLocale
     */
    public const DEFAULT_LOCALE_KEY = 'default_locale';

    public function localizationSettings()
    {
        return view('admin.platform.localization', [
            'currentDefault' => PlatformSetting::get(
                self::DEFAULT_LOCALE_KEY,
                config('app.locale', 'en'),
            ),
            'supported' => config('locales.supported', []),
        ]);
    }

    public function updateLocalizationSettings(Request $request)
    {
        $supported = array_keys((array) config('locales.supported', []));

        $data = $request->validate([
            'default_locale' => ['required', 'string', \Illuminate\Validation\Rule::in($supported)],
        ]);

        PlatformSetting::set(self::DEFAULT_LOCALE_KEY, $data['default_locale'], 'string');

        return back()->with('success', __('ui.platform_localization_page.saved'));
    }

    public function updateConversationSettings(Request $request)
    {
        // 0 disables the sweep; the ceiling is a day, past which "idle" stops
        // meaning anything useful.
        $data = $request->validate([
            'ai_idle_close_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
        ]);

        PlatformSetting::set(
            CloseIdleAiConversations::SETTING_KEY,
            (int) $data['ai_idle_close_minutes'],
            'integer',
        );

        return back()->with('success', __('ui.platform_conversation_settings_page.saved'));
    }

    /**
     * Meta / Facebook Messenger credentials the super admin can edit
     * from the UI without SSHing to the box. Values sit in
     * platform_settings (secrets encrypted with Crypt); a config/services.php
     * fallback covers a fresh install and a rollback path.
     *
     * Two boxes stay separate on purpose: App ID + Graph version (public)
     * are shown in plain text so ops can eyeball them; secrets are
     * masked and only ever accepted as a NEW value on submit — the
     * form never renders the current secret.
     */
    public function metaSettings()
    {
        return view('admin.platform.meta-settings', [
            'appId'          => MetaConfig::appId(),
            'graphVersion'   => MetaConfig::graphVersion(),
            'appSecretSet'   => MetaConfig::appSecret() !== '',
            'verifyTokenSet' => MetaConfig::verifyToken() !== '',
            'appSecretMask'  => MetaConfig::maskSecret(MetaConfig::appSecret()),
            'verifyTokenMask'=> MetaConfig::maskSecret(MetaConfig::verifyToken()),
            'appIdInDb'      => MetaConfig::isStoredInDb(MetaConfig::KEY_APP_ID),
            'appSecretInDb'  => MetaConfig::isStoredInDb(MetaConfig::KEY_APP_SECRET),
            'verifyTokenInDb'=> MetaConfig::isStoredInDb(MetaConfig::KEY_VERIFY_TOKEN),
            'graphVersionInDb' => MetaConfig::isStoredInDb(MetaConfig::KEY_GRAPH_VERSION),
        ]);
    }

    public function updateMetaSettings(Request $request)
    {
        $data = $request->validate([
            // Meta App ID is a 15–16 digit numeric string.
            'app_id'         => ['nullable', 'string', 'regex:/^[0-9]{10,20}$/'],
            // Graph version like v21.0, v20.0, etc.
            'graph_version'  => ['nullable', 'string', 'regex:/^v\d+\.\d+$/'],
            // Secrets: pass through only when the operator explicitly types a
            // new value. Empty field leaves the stored value alone.
            'app_secret'     => ['nullable', 'string', 'min:8', 'max:255'],
            'verify_token'   => ['nullable', 'string', 'min:16', 'max:255'],
        ], [
            'app_id.regex'        => 'App ID should be a 10-20 digit numeric string.',
            'graph_version.regex' => 'Graph version should look like v21.0.',
            'app_secret.min'      => 'App Secret is too short — did you paste it fully?',
            'verify_token.min'    => 'Verify Token should be at least 16 characters.',
        ]);

        // App ID + Graph version: writing an empty string clears the DB row
        // and falls back to env. Only write when the operator actually typed
        // something, so accidentally submitting an empty form doesn't wipe
        // config.
        if ($request->filled('app_id')) {
            MetaConfig::setAppId($data['app_id']);
        }
        if ($request->filled('graph_version')) {
            MetaConfig::setGraphVersion($data['graph_version']);
        }
        if ($request->filled('app_secret')) {
            MetaConfig::setAppSecret($data['app_secret']);
        }
        if ($request->filled('verify_token')) {
            MetaConfig::setVerifyToken($data['verify_token']);
        }

        AuditLog::record('platform.meta_settings.updated', null, [
            'fields' => array_keys(array_filter([
                'app_id'       => $request->filled('app_id'),
                'app_secret'   => $request->filled('app_secret'),
                'verify_token' => $request->filled('verify_token'),
                'graph_version'=> $request->filled('graph_version'),
            ])),
        ]);

        return redirect()->route('super_admin.platform.meta-settings')
            ->with('success', 'Meta settings saved. The webhook and Send API pick up the new values on the next request.');
    }

    /**
     * Add-on pricing. The tenant billing page, the checkout and the PayPal
     * order all read these through AddonPricing, so a price saved here takes
     * effect everywhere at once — there is no second copy to keep in step.
     */
    public function addonSettings()
    {
        return view('admin.platform.addon-settings', [
            'pricing' => AddonPricing::forView(),
        ]);
    }

    public function updateAddonSettings(Request $request)
    {
        $data = $request->validate([
            // Prices are money, so they validate as decimals rather than
            // integers — $6.50 a seat has to survive the round trip.
            'seat_price'       => ['required', 'numeric', 'min:0', 'max:9999'],
            'seat_max'         => ['required', 'integer', 'min:1', 'max:500'],
            'seats_enabled'    => ['nullable', 'boolean'],
            'ai_pack_price'    => ['required', 'numeric', 'min:0', 'max:9999'],
            'ai_pack_messages' => ['required', 'integer', 'min:1', 'max:1000000'],
            'ai_pack_max'      => ['required', 'integer', 'min:1', 'max:500'],
            'ai_packs_enabled' => ['nullable', 'boolean'],
        ]);

        PlatformSetting::set(AddonPricing::KEY_SEAT_PRICE,    number_format((float) $data['seat_price'], 2, '.', ''));
        PlatformSetting::set(AddonPricing::KEY_SEAT_MAX,      (int) $data['seat_max'], 'integer');
        PlatformSetting::set(AddonPricing::KEY_SEATS_ENABLED, (bool) $request->boolean('seats_enabled'), 'boolean');

        PlatformSetting::set(AddonPricing::KEY_PACK_PRICE,    number_format((float) $data['ai_pack_price'], 2, '.', ''));
        PlatformSetting::set(AddonPricing::KEY_PACK_MESSAGES, (int) $data['ai_pack_messages'], 'integer');
        PlatformSetting::set(AddonPricing::KEY_PACK_MAX,      (int) $data['ai_pack_max'], 'integer');
        PlatformSetting::set(AddonPricing::KEY_PACKS_ENABLED, (bool) $request->boolean('ai_packs_enabled'), 'boolean');

        AuditLog::record('platform.addon_pricing_updated', null, [
            'seat_price'    => (float) $data['seat_price'],
            'ai_pack_price' => (float) $data['ai_pack_price'],
            'ai_pack_size'  => (int) $data['ai_pack_messages'],
        ]);

        return back()->with('success', __('ui.platform_addons_page.saved'));
    }

    public function systemHealth()
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkWritablePath(storage_path()),
            'bootstrap_cache' => $this->checkWritablePath(base_path('bootstrap/cache')),
        ];

        $health = [
            'checks' => $checks,
            'summary' => [
                'total' => count($checks),
                'ok' => collect($checks)->where('status', 'ok')->count(),
                'warning' => collect($checks)->where('status', 'warning')->count(),
                'error' => collect($checks)->where('status', 'error')->count(),
            ],
            'metrics' => [
                'tenants' => Tenant::count(),
                'users' => User::count(),
                'plans' => Plan::count(),
                'active_plans' => Plan::where('is_active', true)->count(),
                'conversations' => \App\Models\Conversation::count(),
            ],
            'runtime' => [
                'app_env' => config('app.env'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'queue_connection' => config('queue.default'),
                'cache_store' => config('cache.default'),
                'mail_mailer' => config('mail.default'),
            ],
            'checked_at' => now(),
        ];

        return view('admin.platform.system-health', compact('health'));
    }

    private function checkDatabase(): array
    {
        try {
            DB::select('select 1');
            return [
                'label' => __('ui.platform_system_health_page.database'),
                'status' => 'ok',
                'detail' => __('ui.platform_system_health_page.database_ok'),
            ];
        } catch (Throwable) {
            return [
                'label' => __('ui.platform_system_health_page.database'),
                'status' => 'error',
                'detail' => __('ui.platform_system_health_page.database_error'),
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'healthcheck:' . now()->timestamp;
            Cache::put($key, 'ok', 10);
            return Cache::get($key) === 'ok'
                ? [
                    'label' => __('ui.platform_system_health_page.cache'),
                    'status' => 'ok',
                    'detail' => __('ui.platform_system_health_page.cache_ok'),
                ]
                : [
                    'label' => __('ui.platform_system_health_page.cache'),
                    'status' => 'error',
                    'detail' => __('ui.platform_system_health_page.cache_error'),
                ];
        } catch (Throwable) {
            return [
                'label' => __('ui.platform_system_health_page.cache'),
                'status' => 'error',
                'detail' => __('ui.platform_system_health_page.cache_exception'),
            ];
        }
    }

    private function checkQueue(): array
    {
        $connection = (string) config('queue.default', 'sync');

        if ($connection === 'sync') {
            return [
                'label' => __('ui.platform_system_health_page.queue_worker'),
                'status' => 'warning',
                'detail' => __('ui.platform_system_health_page.queue_sync'),
            ];
        }

        return [
            'label' => __('ui.platform_system_health_page.queue_worker'),
            'status' => 'ok',
            'detail' => __('ui.platform_system_health_page.queue_connection_is', ['connection' => $connection]),
        ];
    }

    private function checkWritablePath(string $path): array
    {
        $ok = is_dir($path) && is_writable($path);

        return [
            'label' => str_starts_with($path, storage_path())
                ? __('ui.platform_system_health_page.storage_directory')
                : __('ui.platform_system_health_page.bootstrap_cache'),
            'status' => $ok ? 'ok' : 'error',
            'detail' => $ok ? __('ui.platform_system_health_page.directory_writable') : __('ui.platform_system_health_page.directory_missing'),
        ];
    }

    private function validateTenant(Request $request, ?Tenant $tenant = null): array
    {
        $tenantAdmin = $tenant
            ? User::where('tenant_id', $tenant->id)->where('role', 'admin')->orderBy('id')->first()
            : null;

        return $request->validate([
            'name'                => 'required|string|max:150',
            'slug'                => [
                'nullable',
                'string',
                'max:150',
                'alpha_dash',
                Rule::unique('tenants', 'slug')->ignore($tenant?->id),
            ],
            'plan_id'             => 'nullable|exists:plans,id',
            'subscription_status'    => 'required|in:active,trial,suspended,cancelled',
            'subscription_starts_at' => 'nullable|date',
            'subscription_ends_at'   => 'nullable|date|after_or_equal:subscription_starts_at',
            'stripe_id'              => 'nullable|string|max:255',
            'settings'            => 'nullable|json',
            // CALC-010: IANA identifier (e.g. Africa/Tunis). Validated against
            // PHP's built-in list so a typo can't reach the report queries.
            'timezone'            => ['nullable', 'string', 'max:64', Rule::in(timezone_identifiers_list())],
            'is_active'           => 'nullable|boolean',
            'admin_name'          => 'nullable|string|max:150',
            'admin_email'         => [
                'nullable',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($tenantAdmin?->id),
            ],
            'admin_password'      => 'nullable|string|min:8|confirmed',
        ]);
    }

    private function buildUniqueSlug(string $rawSlug, ?int $ignoreTenantId = null): string
    {
        $baseSlug = Str::slug($rawSlug);
        if ($baseSlug === '') {
            $baseSlug = 'tenant';
        }
        $slug = $baseSlug;
        $i = 2;

        while (Tenant::where('slug', $slug)->when($ignoreTenantId, fn ($query) => $query->where('id', '!=', $ignoreTenantId))->exists()) {
            $slug = "{$baseSlug}-{$i}";
            $i++;
        }

        return $slug;
    }

    private function parseSettings(?string $settings): ?array
    {
        if ($settings === null || trim($settings) === '') {
            return null;
        }

        return json_decode($settings, true);
    }

    private function validatePlan(Request $request, ?Plan $plan = null): array
    {
        return $request->validate([
            'name'                        => ['required', 'string', 'max:150', Rule::unique('plans', 'name')->ignore($plan?->id)],
            'price_monthly'               => 'required|numeric|min:0',
            // CALC-013: nullable so "not offered" is expressible as null. The
            // landing page treats 0 and null the same ("no annual") and hides
            // the annual cycle for plans in that state instead of falling back
            // to the monthly price under a yearly label.
            'price_annual'                => 'nullable|numeric|min:0',
            'max_users'                   => 'required|integer|min:1',
            // Blank = no cap. The landing card can advertise this limit, so it
            // needed a field of its own rather than staying DB-only.
            'max_instances'               => 'nullable|integer|min:0',
            'max_conversations_per_month' => 'required|integer|min:0',
            'ai_included'                 => 'nullable|boolean',
            // How many AI replies a tenant on this plan gets each month:
            // 'unlimited' or a positive 'limited' number. Whether AI runs at
            // all is the ai_agent module, not a number — planAttributes()
            // folds the two back into ai_message_quota's three-state column,
            // and the sync in updatePlan pushes it to every tenant.
            'ai_messages_mode'            => 'nullable|in:unlimited,limited',
            'ai_message_limit'            => 'nullable|integer|min:1|required_if:ai_messages_mode,limited',
            'is_active'                   => 'nullable|boolean',

            // Static PayPal "No Code Payment" URL — one per plan, created in
            // the PayPal merchant dashboard. When set, the checkout button
            // links straight to this URL instead of going through the Orders
            // API. Nullable + max 500 to match the DB column.
            'paypal_ncp_link'             => 'nullable|url|max:500',

            // Per-plan free trial. A blank length falls back to the platform
            // default (config app.trial_days) inside Plan::trialDays().
            'trial_enabled'               => 'nullable|boolean',
            'trial_days'                  => 'nullable|integer|min:1|max:365',

            // Module entitlements + the manual landing-page picks. Both are
            // validated against their catalogues so a hand-crafted POST cannot
            // introduce a key nothing knows how to render or gate.
            'modules'                     => 'nullable|array',
            'modules.*'                   => ['string', Rule::in(array_keys(config('plan_modules', [])))],
            'landing_features'            => 'nullable|array',
            'landing_features.*'          => ['string', Rule::in($this->landingAttributeKeys())],
        ]);
    }

    /** Every key the landing picker may submit: built-ins plus module lines. */
    private function landingAttributeKeys(): array
    {
        return array_merge(
            array_keys(config('plan_landing_attributes', [])),
            array_map(
                fn (string $module) => 'module:' . $module,
                array_keys(config('plan_modules', []))
            )
        );
    }

    /**
     * Shared shaping for storePlan/updatePlan.
     *
     * `ai_included` and `reservations_enabled` are no longer edited directly —
     * they are derived from the module checkboxes so there is one control per
     * concept. Keeping the columns in sync matters: ProcessIncomingMessage and
     * ReservationController still read reservations_enabled.
     */
    private function planAttributes(array $data): array
    {
        $modules = array_values(array_unique((array) ($data['modules'] ?? [])));

        // `always` modules are posted via a hidden field, but re-assert them so
        // a stripped POST cannot produce a plan without the core channel.
        foreach (config('plan_modules', []) as $key => $meta) {
            if (($meta['always'] ?? false) && !in_array($key, $modules, true)) {
                $modules[] = $key;
            }
        }

        $trialEnabled = (bool) ($data['trial_enabled'] ?? false);
        $aiIncluded   = in_array('ai_agent', $modules, true);

        return [
            'name'                        => $data['name'],
            'price_monthly'               => $data['price_monthly'],
            'price_annual'                => $data['price_annual'] ?? null,
            'max_users'                   => $data['max_users'],
            'max_instances'               => $data['max_instances'] ?? null,
            'max_conversations_per_month' => $data['max_conversations_per_month'],
            'ai_included'                 => $aiIncluded,
            'ai_message_quota'            => $this->aiMessageQuota($data, $aiIncluded),
            'reservations_enabled'        => in_array('reservations', $modules, true),
            'modules'                     => $modules,
            'landing_features'            => array_values(array_unique((array) ($data['landing_features'] ?? []))),
            'trial_enabled'               => $trialEnabled,
            'trial_days'                  => $trialEnabled && !empty($data['trial_days'])
                ? (int) $data['trial_days']
                : null,
            // Blank input becomes NULL so the checkout controller can gate
            // on `$plan->paypal_ncp_link ?? null` without treating "" as set.
            'paypal_ncp_link'             => trim((string) ($data['paypal_ncp_link'] ?? '')) ?: null,
        ];
    }

    /**
     * Fold the form's two AI controls back into plans.ai_message_quota, which
     * keeps its three-state convention: 0 = no AI on this plan, NULL =
     * unlimited AI messages, positive = the monthly cap.
     *
     * A plan without the ai_agent module is 0 whatever the radio says — the
     * tenant can never reach the AI, so an allowance would be a lie on the
     * pricing card and in the tenant's AI settings.
     */
    private function aiMessageQuota(array $data, bool $aiIncluded): ?int
    {
        if (!$aiIncluded) {
            return 0;
        }

        if (($data['ai_messages_mode'] ?? 'unlimited') !== 'limited') {
            return null;
        }

        return isset($data['ai_message_limit']) ? (int) $data['ai_message_limit'] : null;
    }

}
