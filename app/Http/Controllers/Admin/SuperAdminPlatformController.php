<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\CloseIdleAiConversations;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\AuditLog;
use App\Models\User;
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
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->is_active === '1'));

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

        return view('admin.platform.tenants-show', compact('tenant', 'payments'));
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

    public function bulkTenants(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:enable,disable,delete',
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

        $tenants = Tenant::whereIn('id', $ids)->get();

        if ($tenants->isEmpty()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('ui.controller_messages.no_valid_tenants_selected')], 422);
            }
            return back()->with('error', __('ui.controller_messages.no_valid_tenants_selected'));
        }

        if ($data['action'] === 'delete') {
            foreach ($tenants as $tenant) {
                $tenant->delete();
            }

            $message = __('ui.controller_messages.tenants_deleted', ['count' => $tenants->count()]);
            if ($request->expectsJson()) {
                return response()->json(['message' => $message]);
            }
            return back()->with('success', $message);
        }

        $enable = $data['action'] === 'enable';
        Tenant::whereIn('id', $tenants->pluck('id'))->update(['is_active' => $enable]);

        $message = $enable
            ? __('ui.controller_messages.tenants_enabled', ['count' => $tenants->count()])
            : __('ui.controller_messages.tenants_disabled', ['count' => $tenants->count()]);

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

        return view('admin.platform.plans-edit', compact('plan'));
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
