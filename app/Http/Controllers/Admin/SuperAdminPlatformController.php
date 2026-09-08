<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
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

        if (!$tenant->subscription_starts_at || !$tenant->subscription_ends_at) {
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
            if ($endsAt && $endsAt->isPast()) {
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
                'is_active'              => $status === 'active',
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
            return response()->json(['message' => 'Tenant deleted.']);
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

        Plan::create([
            'name'                        => $data['name'],
            'stripe_price_id_monthly'     => $data['stripe_price_id_monthly'] ?? null,
            'stripe_price_id_annual'      => $data['stripe_price_id_annual'] ?? null,
            'price_monthly'               => $data['price_monthly'],
            'price_annual'                => $data['price_annual'],
            'max_users'                   => $data['max_users'],
            'max_conversations_per_month' => $data['max_conversations_per_month'],
            'ai_included'                 => (bool) ($data['ai_included'] ?? false),
            'ai_token_quota'              => (int) ($data['ai_token_quota'] ?? 0),
            'reservations_enabled'        => (bool) ($data['reservations_enabled'] ?? false),
            'features'                    => $this->parseFeatures($data['features'] ?? null),
            'is_active'                   => (bool) ($data['is_active'] ?? true),
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

        $plan->update([
            'name'                        => $data['name'],
            'stripe_price_id_monthly'     => $data['stripe_price_id_monthly'] ?? null,
            'stripe_price_id_annual'      => $data['stripe_price_id_annual'] ?? null,
            'price_monthly'               => $data['price_monthly'],
            'price_annual'                => $data['price_annual'],
            'max_users'                   => $data['max_users'],
            'max_conversations_per_month' => $data['max_conversations_per_month'],
            'ai_included'                 => (bool) ($data['ai_included'] ?? false),
            'ai_token_quota'              => (int) ($data['ai_token_quota'] ?? 0),
            'reservations_enabled'        => (bool) ($data['reservations_enabled'] ?? false),
            'features'                    => $this->parseFeatures($data['features'] ?? null),
            'is_active'                   => $nextStatus,
        ]);

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
            'subscription_status'    => 'required|in:active,suspended,cancelled',
            'subscription_starts_at' => 'nullable|date',
            'subscription_ends_at'   => 'nullable|date|after_or_equal:subscription_starts_at',
            'stripe_id'              => 'nullable|string|max:255',
            'settings'            => 'nullable|json',
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
            'stripe_price_id_monthly'     => 'nullable|string|max:255',
            'stripe_price_id_annual'      => 'nullable|string|max:255',
            'price_monthly'               => 'required|numeric|min:0',
            'price_annual'                => 'required|numeric|min:0',
            'max_users'                   => 'required|integer|min:1',
            'max_conversations_per_month' => 'required|integer|min:0',
            'ai_included'                 => 'nullable|boolean',
            'ai_token_quota'              => 'nullable|integer|min:0',
            'reservations_enabled'        => 'nullable|boolean',
            'features'                    => 'nullable|json',
            'is_active'                   => 'nullable|boolean',
        ]);
    }

    private function parseFeatures(?string $features): ?array
    {
        if ($features === null || trim($features) === '') {
            return null;
        }

        return json_decode($features, true);
    }

}
