@extends('layouts.admin')

@section('title', $tenant->name)

@section('breadcrumb')
    <span>{{ __('ui.platform_tenants_show_page.breadcrumb_root') }}</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <a href="{{ route('super_admin.platform.tenants') }}" style="color:var(--text-secondary);text-decoration:none">{{ __('ui.platform_tenants_show_page.breadcrumb') }}</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ $tenant->name }}</span>
@endsection

@section('content')
<div>
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ $tenant->name }}</div>
            <div class="page-subtitle">{{ __('ui.platform_tenants_show_page.tenant_slug') }}: {{ $tenant->slug }}</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('super_admin.platform.tenants') }}" class="btn btn-outline">
                <i class="ri-arrow-left-line"></i> {{ __('ui.back') }}
            </a>
            <a href="{{ route('super_admin.platform.tenants.edit', $tenant) }}" class="btn btn-primary">
                <i class="ri-pencil-line"></i> {{ __('ui.platform_tenants_show_page.edit_tenant') }}
            </a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-team-line"></i></div>
            <div class="stat-card-value">{{ number_format($tenant->users_count) }}</div>
            <div class="stat-card-label">{{ __('ui.platform_tenants_show_page.users') }}</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-card-icon"><i class="ri-group-line"></i></div>
            <div class="stat-card-value">{{ number_format($tenant->teams_count) }}</div>
            <div class="stat-card-label">{{ __('ui.platform_tenants_show_page.teams') }}</div>
        </div>
        <div class="stat-card purple">
            <div class="stat-card-icon"><i class="ri-whatsapp-line"></i></div>
            <div class="stat-card-value">{{ number_format($tenant->instances_count) }}</div>
            <div class="stat-card-label">{{ __('ui.platform_tenants_show_page.instances') }}</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-card-icon"><i class="ri-message-3-line"></i></div>
            <div class="stat-card-value">{{ number_format($tenant->conversations_count) }}</div>
            <div class="stat-card-label">{{ __('ui.platform_tenants_show_page.conversations') }}</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:1.25rem;align-items:start;">
        <div class="card">
            <div class="card-header">
                <div class="card-title">{{ __('ui.platform_tenants_show_page.tenant_information') }}</div>
            </div>
            <div style="padding:20px;">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.platform_tenants_show_page.plan') }}</label>
                        <div class="form-control" style="display:flex;align-items:center;">
                            {{ $tenant->plan?->name ?? __('ui.platform_tenants_page.no_plan') }}
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.platform_tenants_show_page.subscription_status') }}</label>
                        <div style="height:40px;display:flex;align-items:center;">
                            @php
                                $statusBadge = match($tenant->subscription_status) {
                                    'active' => 'badge-green',
                                    'trial' => 'badge-orange',
                                    'suspended' => 'badge-red',
                                    default => 'badge-gray',
                                };
                                $statusKey = 'ui.platform_tenants_page.statuses.' . $tenant->subscription_status;
                                $statusLabel = __($statusKey);
                                if ($statusLabel === $statusKey) {
                                    $statusLabel = ucfirst($tenant->subscription_status);
                                }
                            @endphp
                            <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                            @if(!$tenant->is_active)
                                <span class="badge badge-gray" style="margin-left:6px;">{{ __('ui.platform_tenants_page.inactive') }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.platform_tenants_show_page.trial_ends_at') }}</label>
                        <div class="form-control" style="display:flex;align-items:center;">
                            {{ $tenant->trial_ends_at?->format('M j, Y') ?? __('ui.platform_tenants_show_page.not_set') }}
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.platform_tenants_show_page.stripe_customer_id') }}</label>
                        <div class="form-control" style="display:flex;align-items:center;font-family:monospace;">
                            {{ $tenant->stripe_id ?: __('ui.platform_tenants_show_page.not_set') }}
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label">{{ __('ui.platform_tenants_show_page.settings_json') }}</label>
                    <textarea class="form-control" rows="8" readonly>{{ $tenant->settings ? json_encode($tenant->settings, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) : '{}' }}</textarea>
                </div>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:1rem;">
            <div class="card">
                <div class="card-header" style="padding-bottom:12px;">
                    <div class="card-title">{{ __('ui.platform_tenants_show_page.meta') }}</div>
                </div>
                <div style="padding:0 18px 18px 18px;display:flex;flex-direction:column;gap:8px;font-size:13px;">
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-muted);">{{ __('ui.platform_tenants_show_page.created') }}</span>
                        <strong>{{ $tenant->created_at?->format('M j, Y') }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-muted);">{{ __('ui.platform_tenants_show_page.last_updated') }}</span>
                        <strong>{{ $tenant->updated_at?->diffForHumans() }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-muted);">{{ __('ui.platform_tenants_show_page.customers') }}</span>
                        <strong>{{ number_format($tenant->customers_count) }}</strong>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="padding-bottom:12px;">
                    <div class="card-title">{{ __('ui.platform_tenants_show_page.recent_users') }}</div>
                </div>
                <div style="padding:0 18px 18px 18px;display:flex;flex-direction:column;gap:8px;">
                    @forelse($tenant->users as $user)
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 10px;background:var(--page-bg);border-radius:8px;">
                            <div style="min-width:0;">
                                <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $user->name }}</div>
                                <div style="font-size:12px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $user->email }}</div>
                            </div>
                            @php
                                $roleKey = 'ui.roles.' . $user->role;
                                $roleLabel = __($roleKey);
                                if ($roleLabel === $roleKey) {
                                    $roleLabel = ucfirst($user->role);
                                }
                            @endphp
                            <span class="badge badge-gray">{{ $roleLabel }}</span>
                        </div>
                    @empty
                        <div style="font-size:13px;color:var(--text-muted);">{{ __('ui.platform_tenants_show_page.no_users_yet') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
