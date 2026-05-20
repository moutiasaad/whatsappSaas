<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class SuperAdminPlatformController extends Controller
{
    public function tenants()
    {
        $baseQuery = Tenant::query()
            ->withCount(['users', 'teams', 'whatsappInstances as instances_count'])
            ->when(request('search'), function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when(request('status'), fn ($q, $status) => $q->where('subscription_status', $status))
            ->when(request('plan_id'), fn ($q, $planId) => $q->where('plan_id', $planId))
            ->when(request()->filled('is_active'), fn ($q) => $q->where('is_active', request('is_active') === '1'));

        $tenants = (clone $baseQuery)
            ->with('plan')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $plans = Plan::where('is_active', true)->orderBy('name')->get();
        $stats = [
            'total'          => (clone $baseQuery)->count(),
            'active'         => (clone $baseQuery)->where('subscription_status', 'active')->count(),
            'trial'          => (clone $baseQuery)->where('subscription_status', 'trial')->count(),
            'inactive'       => (clone $baseQuery)->where('is_active', false)->count(),
        ];

        return view('admin.platform.tenants', compact('tenants', 'plans', 'stats'));
    }

    public function createTenant()
    {
        $plans = Plan::where('is_active', true)->orderBy('name')->get();

        return view('admin.platform.tenants-create', compact('plans'));
    }

    public function storeTenant(Request $request)
    {
        $data = $this->validateTenant($request);
        $slug = $this->buildUniqueSlug($data['slug'] ?? $data['name']);

        Tenant::create([
            'name'                => $data['name'],
            'slug'                => $slug,
            'plan_id'             => $data['plan_id'] ?? null,
            'subscription_status' => $data['subscription_status'],
            'trial_ends_at'       => $data['trial_ends_at'] ?? null,
            'stripe_id'           => $data['stripe_id'] ?? null,
            'settings'            => $this->parseSettings($data['settings'] ?? null),
            'is_active'           => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()->route('super_admin.platform.tenants')->with('success', 'Tenant created successfully.');
    }

    public function showTenant(Tenant $tenant)
    {
        $tenant->loadCount(['users', 'teams', 'whatsappInstances as instances_count', 'customers', 'conversations']);
        $tenant->load([
            'plan:id,name,price_monthly',
            'users' => fn ($query) => $query->latest()->limit(5),
        ]);

        return view('admin.platform.tenants-show', compact('tenant'));
    }

    public function editTenant(Tenant $tenant)
    {
        $tenant->loadCount(['users', 'teams', 'whatsappInstances as instances_count']);
        $plans = Plan::where('is_active', true)->orderBy('name')->get();

        return view('admin.platform.tenants-edit', compact('tenant', 'plans'));
    }

    public function updateTenant(Request $request, Tenant $tenant)
    {
        $data = $this->validateTenant($request, $tenant);
        $slug = $this->buildUniqueSlug($data['slug'] ?? $data['name'], $tenant->id);

        $tenant->update([
            'name'                => $data['name'],
            'slug'                => $slug,
            'plan_id'             => $data['plan_id'] ?? null,
            'subscription_status' => $data['subscription_status'],
            'trial_ends_at'       => $data['trial_ends_at'] ?? null,
            'stripe_id'           => $data['stripe_id'] ?? null,
            'settings'            => $this->parseSettings($data['settings'] ?? null),
            'is_active'           => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()
            ->route('super_admin.platform.tenants.show', $tenant)
            ->with('success', 'Tenant updated successfully.');
    }

    public function destroyTenant(Tenant $tenant)
    {
        $tenantName = $tenant->name;
        $tenant->delete();

        return redirect()
            ->route('super_admin.platform.tenants')
            ->with('success', "Tenant \"{$tenantName}\" deleted.");
    }

    public function plans()
    {
        $baseQuery = Plan::query()
            ->withCount('tenants')
            ->when(request('search'), function ($query, $search) {
                $query->where('name', 'like', '%' . $search . '%');
            })
            ->when(request()->filled('is_active'), fn ($query) => $query->where('is_active', request('is_active') === '1'));

        $plans = (clone $baseQuery)
            ->orderBy('price_monthly')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total'    => (clone $baseQuery)->count(),
            'active'   => (clone $baseQuery)->where('is_active', true)->count(),
            'inactive' => (clone $baseQuery)->where('is_active', false)->count(),
            'assigned' => (clone $baseQuery)->has('tenants')->count(),
        ];

        return view('admin.platform.plans', compact('plans', 'stats'));
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
                ->with('error', 'Cannot disable the last active plan.');
        }

        $plan->update([
            'name'                        => $data['name'],
            'stripe_price_id_monthly'     => $data['stripe_price_id_monthly'] ?? null,
            'stripe_price_id_annual'      => $data['stripe_price_id_annual'] ?? null,
            'price_monthly'               => $data['price_monthly'],
            'price_annual'                => $data['price_annual'],
            'max_users'                   => $data['max_users'],
            'max_instances'               => $data['max_instances'],
            'max_conversations_per_month' => $data['max_conversations_per_month'],
            'ai_included'                 => (bool) ($data['ai_included'] ?? false),
            'ai_token_quota'              => (int) ($data['ai_token_quota'] ?? 0),
            'features'                    => $this->parseFeatures($data['features'] ?? null),
            'is_active'                   => $nextStatus,
        ]);

        return redirect()
            ->route('super_admin.platform.plans.show', $plan)
            ->with('success', 'Plan updated successfully.');
    }

    public function togglePlanStatus(Request $request, Plan $plan)
    {
        $request->validate(['is_active' => 'required|boolean']);
        $nextStatus = $request->boolean('is_active');

        if (!$nextStatus && Plan::where('is_active', true)->where('id', '!=', $plan->id)->count() === 0) {
            return redirect()
                ->route('super_admin.platform.plans')
                ->with('error', 'Cannot disable the last active plan.');
        }

        $plan->update(['is_active' => $nextStatus]);

        return redirect()
            ->route('super_admin.platform.plans')
            ->with('success', $nextStatus ? 'Plan enabled.' : 'Plan disabled.');
    }

    public function globalSettings()
    {
        $settings = $this->resolvedGlobalSettings();
        $runtime = $this->runtimeGlobalSettings();

        return view('admin.platform.global-settings', compact('settings', 'runtime'));
    }

    public function editGlobalSettings()
    {
        $settings = $this->resolvedGlobalSettings();
        $runtime = $this->runtimeGlobalSettings();

        return view('admin.platform.global-settings-edit', compact('settings', 'runtime'));
    }

    public function updateGlobalSettings(Request $request)
    {
        $schema = $this->globalSettingsSchema();
        $rules = [];

        foreach ($schema as $key => $meta) {
            $rules[$key] = match ($meta['type']) {
                'boolean' => 'nullable|boolean',
                'url' => 'required|url|max:255',
                'email' => 'nullable|email|max:150',
                default => 'required|string|max:150',
            };
        }

        $data = $request->validate($rules);

        foreach ($schema as $key => $meta) {
            $value = match ($meta['type']) {
                'boolean' => (bool) ($data[$key] ?? false),
                default => $data[$key] ?? $meta['default'],
            };

            PlatformSetting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $this->serializePlatformSetting($value, $meta['type']),
                    'type'  => $meta['type'],
                ]
            );
        }

        return redirect()
            ->route('super_admin.platform.global-settings')
            ->with('success', 'Global settings updated.');
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
                'label' => 'Database',
                'status' => 'ok',
                'detail' => 'Connection check succeeded.',
            ];
        } catch (Throwable) {
            return [
                'label' => 'Database',
                'status' => 'error',
                'detail' => 'Unable to execute database health query.',
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
                    'label' => 'Cache',
                    'status' => 'ok',
                    'detail' => 'Read/write cache check succeeded.',
                ]
                : [
                    'label' => 'Cache',
                    'status' => 'error',
                    'detail' => 'Cache read/write validation failed.',
                ];
        } catch (Throwable) {
            return [
                'label' => 'Cache',
                'status' => 'error',
                'detail' => 'Cache driver threw an exception.',
            ];
        }
    }

    private function checkQueue(): array
    {
        $connection = (string) config('queue.default', 'sync');

        if ($connection === 'sync') {
            return [
                'label' => 'Queue Worker',
                'status' => 'warning',
                'detail' => 'Queue is using sync driver.',
            ];
        }

        return [
            'label' => 'Queue Worker',
            'status' => 'ok',
            'detail' => "Queue connection is set to {$connection}.",
        ];
    }

    private function checkWritablePath(string $path): array
    {
        $ok = is_dir($path) && is_writable($path);

        return [
            'label' => str_starts_with($path, storage_path()) ? 'Storage Directory' : 'Bootstrap Cache',
            'status' => $ok ? 'ok' : 'error',
            'detail' => $ok ? 'Directory is writable.' : 'Directory is missing or not writable.',
        ];
    }

    private function validateTenant(Request $request, ?Tenant $tenant = null): array
    {
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
            'subscription_status' => 'required|in:trial,active,suspended,cancelled',
            'trial_ends_at'       => 'nullable|date',
            'stripe_id'           => 'nullable|string|max:255',
            'settings'            => 'nullable|json',
            'is_active'           => 'nullable|boolean',
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
            'max_instances'               => 'required|integer|min:0',
            'max_conversations_per_month' => 'required|integer|min:0',
            'ai_included'                 => 'nullable|boolean',
            'ai_token_quota'              => 'nullable|integer|min:0',
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

    private function globalSettingsSchema(): array
    {
        return [
            'app_name' => [
                'label' => 'Application Name',
                'type' => 'string',
                'default' => config('app.name'),
            ],
            'app_url' => [
                'label' => 'Application URL',
                'type' => 'url',
                'default' => config('app.url'),
            ],
            'support_email' => [
                'label' => 'Support Email',
                'type' => 'email',
                'default' => config('mail.from.address'),
            ],
            'platform_signups_enabled' => [
                'label' => 'Enable New Tenant Signups',
                'type' => 'boolean',
                'default' => true,
            ],
            'billing_features_enabled' => [
                'label' => 'Enable Billing Features',
                'type' => 'boolean',
                'default' => true,
            ],
            'maintenance_mode_enabled' => [
                'label' => 'Enable Maintenance Mode Banner',
                'type' => 'boolean',
                'default' => false,
            ],
        ];
    }

    private function resolvedGlobalSettings(): array
    {
        $schema = $this->globalSettingsSchema();
        $stored = PlatformSetting::whereIn('key', array_keys($schema))->get()->keyBy('key');
        $resolved = [];

        foreach ($schema as $key => $meta) {
            $raw = $stored[$key]->value ?? null;
            $resolved[$key] = [
                'label' => $meta['label'],
                'type' => $meta['type'],
                'value' => $raw === null ? $meta['default'] : $this->deserializePlatformSetting($raw, $meta['type']),
            ];
        }

        return $resolved;
    }

    private function runtimeGlobalSettings(): array
    {
        return [
            'app_env'          => config('app.env'),
            'queue_connection' => config('queue.default'),
            'cache_store'      => config('cache.default'),
            'session_driver'   => config('session.driver'),
            'broadcast_driver' => config('broadcasting.default'),
            'mail_mailer'      => config('mail.default'),
        ];
    }

    private function serializePlatformSetting(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    private function deserializePlatformSetting(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value === '1',
            default => $value,
        };
    }
}
