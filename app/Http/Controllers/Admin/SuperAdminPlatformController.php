<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SuperAdminPlatformController extends Controller
{
    public function tenants()
    {
        $tenants = Tenant::with('plan')
            ->withCount(['users', 'teams', 'whatsappInstances as instances_count'])
            ->when(request('search'), function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when(request('status'), fn ($q, $status) => $q->where('subscription_status', $status))
            ->when(request('plan_id'), fn ($q, $planId) => $q->where('plan_id', $planId))
            ->when(request()->filled('is_active'), fn ($q) => $q->where('is_active', request('is_active') === '1'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $plans = Plan::where('is_active', true)->orderBy('name')->get();

        return view('admin.platform.tenants', compact('tenants', 'plans'));
    }

    public function createTenant()
    {
        $plans = Plan::where('is_active', true)->orderBy('name')->get();

        return view('admin.platform.tenants-create', compact('plans'));
    }

    public function storeTenant(Request $request)
    {
        $data = $request->validate([
            'name'                => 'required|string|max:150',
            'slug'                => 'nullable|string|max:150|alpha_dash|unique:tenants,slug',
            'plan_id'             => 'nullable|exists:plans,id',
            'subscription_status' => 'required|in:trial,active,suspended,cancelled',
            'trial_ends_at'       => 'nullable|date',
            'stripe_id'           => 'nullable|string|max:255',
            'settings'            => 'nullable|json',
            'is_active'           => 'nullable|boolean',
        ]);

        $baseSlug = Str::slug($data['slug'] ?? $data['name']);
        $slug = $baseSlug;
        $i = 2;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$i}";
            $i++;
        }

        Tenant::create([
            'name'                => $data['name'],
            'slug'                => $slug,
            'plan_id'             => $data['plan_id'] ?? null,
            'subscription_status' => $data['subscription_status'],
            'trial_ends_at'       => $data['trial_ends_at'] ?? null,
            'stripe_id'           => $data['stripe_id'] ?? null,
            'settings'            => !empty($data['settings']) ? json_decode($data['settings'], true) : null,
            'is_active'           => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()->route('admin.platform.tenants')->with('success', 'Tenant created successfully.');
    }

    public function plans()
    {
        $plans = Plan::withCount('tenants')
            ->orderBy('price_monthly')
            ->get();

        return view('admin.platform.plans', compact('plans'));
    }

    public function globalSettings()
    {
        $settings = [
            'app_name'         => config('app.name'),
            'app_env'          => config('app.env'),
            'app_url'          => config('app.url'),
            'queue_connection' => config('queue.default'),
            'cache_store'      => config('cache.default'),
            'session_driver'   => config('session.driver'),
            'broadcast_driver' => config('broadcasting.default'),
            'mail_mailer'      => config('mail.default'),
        ];

        return view('admin.platform.global-settings', compact('settings'));
    }

    public function systemHealth()
    {
        $health = [
            'database' => $this->checkDatabase(),
            'cache'    => $this->checkCache(),
            'users'    => User::count(),
            'tenants'  => Tenant::count(),
        ];

        return view('admin.platform.system-health', compact('health'));
    }

    private function checkDatabase(): string
    {
        try {
            DB::select('select 1');
            return 'ok';
        } catch (Throwable) {
            return 'error';
        }
    }

    private function checkCache(): string
    {
        try {
            $key = 'healthcheck:' . now()->timestamp;
            Cache::put($key, 'ok', 10);
            return Cache::get($key) === 'ok' ? 'ok' : 'error';
        } catch (Throwable) {
            return 'error';
        }
    }
}
