@extends('layouts.admin')

@section('title', 'Billing Control Plane')

@section('breadcrumb')
    <span>Billing</span>
@endsection

@section('content')
<div>
    @php
        $activeTenants = $tenants->where('subscription_status', 'active')->count();
        $trialTenants = $tenants->where('subscription_status', 'trial')->count();
        $suspendedTenants = $tenants->where('subscription_status', 'suspended')->count();
        $aiPlans = $plans->where('ai_included', true)->count();
    @endphp

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">Billing Control Plane</div>
            <div class="page-subtitle">Review tenant subscriptions, plan coverage, and platform billing visibility.</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('super_admin.platform.plans') }}" class="btn btn-outline">
                <i class="ri-price-tag-3-line"></i> Manage Plans
            </a>
            <a href="{{ route('super_admin.platform.global-settings') }}" class="btn btn-primary">
                <i class="ri-settings-3-line"></i> Global Settings
            </a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-building-2-line"></i></div>
            <div class="stat-card-value">{{ number_format($tenants->count()) }}</div>
            <div class="stat-card-label">Total Tenants</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-checkbox-circle-line"></i></div>
            <div class="stat-card-value">{{ number_format($activeTenants) }}</div>
            <div class="stat-card-label">Active Subscriptions</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-card-icon"><i class="ri-time-line"></i></div>
            <div class="stat-card-value">{{ number_format($trialTenants) }}</div>
            <div class="stat-card-label">Trialing Tenants</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-card-icon"><i class="ri-sparkling-2-line"></i></div>
            <div class="stat-card-value">{{ number_format($aiPlans) }}</div>
            <div class="stat-card-label">AI-Ready Plans</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:minmax(0,1.35fr) minmax(320px,.9fr);gap:1.25rem;align-items:start">
        <div class="card" style="padding:0">
            <div class="card-header">
                <div>
                    <div class="card-title">Tenant Billing Overview</div>
                    <div class="card-subtitle">Current plan assignment and subscription status across the platform</div>
                </div>
                <span class="badge badge-gray">{{ $tenants->count() }} tenants</span>
            </div>
            <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>Trial Ends</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tenants as $tenant)
                            @php
                                $statusBadge = match($tenant->subscription_status) {
                                    'active' => 'badge-green',
                                    'trial' => 'badge-orange',
                                    'suspended' => 'badge-red',
                                    default => 'badge-gray',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <div style="display:flex;flex-direction:column;gap:.125rem">
                                        <span style="font-weight:600;color:var(--text-primary)">{{ $tenant->name }}</span>
                                        <span style="font-size:.75rem;color:var(--text-muted)">{{ $tenant->slug }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-gray">{{ $tenant->plan?->name ?? 'No plan' }}</span>
                                </td>
                                <td>
                                    <span class="badge {{ $statusBadge }}">{{ ucfirst($tenant->subscription_status) }}</span>
                                </td>
                                <td>
                                    <span style="font-size:.8125rem;color:var(--text-muted)">
                                        {{ $tenant->trial_ends_at?->format('M j, Y') ?? '-' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state" style="padding:2rem 1rem">
                                        <div class="empty-state-icon"><i class="ri-building-2-line"></i></div>
                                        <h4>No tenants found</h4>
                                        <p>Tenant subscription data will appear here once the platform has active workspaces.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div style="display:grid;gap:1.25rem">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Billing Snapshot</div>
                </div>
                <div style="padding:0 1.5rem 1.5rem;display:grid;gap:.875rem">
                    <div style="display:flex;justify-content:space-between;gap:1rem;padding:.75rem 0;border-bottom:1px solid var(--card-border)">
                        <span style="color:var(--text-muted)">Suspended tenants</span>
                        <span style="font-weight:700;color:var(--text-primary)">{{ number_format($suspendedTenants) }}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;gap:1rem;padding:.75rem 0;border-bottom:1px solid var(--card-border)">
                        <span style="color:var(--text-muted)">Published plans</span>
                        <span style="font-weight:700;color:var(--text-primary)">{{ number_format($plans->count()) }}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;gap:1rem;padding:.75rem 0">
                        <span style="color:var(--text-muted)">Billing features</span>
                        <span class="badge badge-green">Enabled</span>
                    </div>
                </div>
            </div>

            <div class="card" style="padding:0">
                <div class="card-header">
                    <div>
                        <div class="card-title">Published Plans</div>
                        <div class="card-subtitle">Commercial limits and plan readiness</div>
                    </div>
                </div>
                <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Plan</th>
                                <th>Monthly</th>
                                <th>Limits</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($plans as $plan)
                                <tr>
                                    <td>
                                        <div style="display:flex;flex-direction:column;gap:.125rem">
                                            <span style="font-weight:600;color:var(--text-primary)">{{ $plan->name }}</span>
                                            <span style="font-size:.75rem;color:var(--text-muted)">
                                                {{ $plan->ai_included ? 'AI included' : 'Standard routing only' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-weight:700;color:var(--text-primary)">${{ number_format((float) $plan->price_monthly, 2) }}</span>
                                    </td>
                                    <td>
                                        <div style="display:flex;flex-wrap:wrap;gap:.25rem">
                                            <span class="badge badge-gray">{{ number_format($plan->max_users) }} users</span>
                                            <span class="badge badge-gray">{{ number_format($plan->max_instances) }} instances</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        <div class="empty-state" style="padding:2rem 1rem">
                                            <div class="empty-state-icon"><i class="ri-price-tag-3-line"></i></div>
                                            <h4>No active plans</h4>
                                            <p>Create or publish a plan to expose billing choices to tenants.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:1.25rem">
        <div class="card-header">
            <div class="card-title">Billing Operations</div>
        </div>
        <div style="padding:0 1.5rem 1.5rem;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem">
            <div style="padding:1rem;border:1px solid var(--card-border);border-radius:var(--radius-lg);background:var(--page-bg)">
                <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.5rem">
                    <div style="width:2.25rem;height:2.25rem;border-radius:.75rem;background:rgba(37,99,235,.12);display:flex;align-items:center;justify-content:center;color:var(--blue)">
                        <i class="ri-bank-card-line"></i>
                    </div>
                    <div style="font-weight:700;color:var(--text-primary)">Stripe Readiness</div>
                </div>
                <div style="font-size:.8125rem;color:var(--text-secondary);line-height:1.55">
                    Payment processing is configured at platform level. Connect Stripe pricing IDs on plans before enabling self-serve upgrades.
                </div>
            </div>
            <div style="padding:1rem;border:1px solid var(--card-border);border-radius:var(--radius-lg);background:var(--page-bg)">
                <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.5rem">
                    <div style="width:2.25rem;height:2.25rem;border-radius:.75rem;background:rgba(16,185,129,.12);display:flex;align-items:center;justify-content:center;color:var(--brand)">
                        <i class="ri-route-line"></i>
                    </div>
                    <div style="font-weight:700;color:var(--text-primary)">Next Step</div>
                </div>
                <div style="font-size:.8125rem;color:var(--text-secondary);line-height:1.55">
                    Use the plans manager to adjust quotas, publish new tiers, or disable legacy plans without touching tenant records directly.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
