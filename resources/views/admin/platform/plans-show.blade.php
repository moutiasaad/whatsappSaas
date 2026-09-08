@extends('layouts.admin')

@section('title', $plan->name)

@section('breadcrumb')
    <span>{{ __('ui.platform_plans_show_page.breadcrumb_root') }}</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <a href="{{ route('super_admin.platform.plans') }}" style="color:var(--text-secondary);text-decoration:none">{{ __('ui.platform_plans_show_page.breadcrumb') }}</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ $plan->name }}</span>
@endsection

@section('content')
<div>
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ $plan->name }}</div>
            <div class="page-subtitle">{{ __('ui.platform_plans_show_page.subtitle') }}</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('super_admin.platform.plans') }}" class="btn btn-outline">
                <i class="ri-arrow-left-line"></i> {{ __('ui.back') }}
            </a>
            <a href="{{ route('super_admin.platform.plans.edit', $plan) }}" class="btn btn-primary">
                <i class="ri-pencil-line"></i> {{ __('ui.platform_plans_show_page.edit_plan') }}
            </a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-building-2-line"></i></div>
            <div class="stat-card-value">{{ number_format($plan->tenants_count) }}</div>
            <div class="stat-card-label">{{ __('ui.platform_plans_show_page.assigned_tenants') }}</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-card-icon"><i class="ri-user-line"></i></div>
            <div class="stat-card-value">{{ number_format($plan->max_users) }}</div>
            <div class="stat-card-label">{{ __('ui.platform_plans_show_page.max_users') }}</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-card-icon"><i class="ri-message-3-line"></i></div>
            <div class="stat-card-value">{{ number_format($plan->max_conversations_per_month) }}</div>
            <div class="stat-card-label">{{ __('ui.platform_plans_show_page.conversations_per_month') }}</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 360px;gap:1.25rem;align-items:start;">
        <div class="card">
            <div class="card-header">
                <div class="card-title">{{ __('ui.platform_plans_show_page.plan_configuration') }}</div>
            </div>
            <div style="padding:20px;">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.platform_plans_show_page.monthly_price') }}</label>
                        <div class="form-control" style="display:flex;align-items:center;">${{ number_format((float) $plan->price_monthly, 2) }}</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.platform_plans_show_page.annual_price') }}</label>
                        <div class="form-control" style="display:flex;align-items:center;">${{ number_format((float) $plan->price_annual, 2) }}</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.platform_plans_show_page.stripe_monthly_price_id') }}</label>
                        <div class="form-control" style="display:flex;align-items:center;font-family:monospace;">{{ $plan->stripe_price_id_monthly ?: __('ui.platform_plans_show_page.not_set') }}</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.platform_plans_show_page.stripe_annual_price_id') }}</label>
                        <div class="form-control" style="display:flex;align-items:center;font-family:monospace;">{{ $plan->stripe_price_id_annual ?: __('ui.platform_plans_show_page.not_set') }}</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.platform_plans_show_page.ai_included') }}</label>
                        <div style="height:40px;display:flex;align-items:center;">
                            @if($plan->ai_included)
                                <span class="badge badge-green"><i class="ri-check-line"></i> {{ __('ui.yes') }}</span>
                            @else
                                <span class="badge badge-gray"><i class="ri-close-line"></i> {{ __('ui.no') }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.platform_plans_show_page.ai_token_quota') }}</label>
                        <div class="form-control" style="display:flex;align-items:center;">{{ number_format($plan->ai_token_quota) }}</div>
                    </div>
                </div>

                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label">{{ __('ui.platform_plans_show_page.features_json') }}</label>
                    <textarea class="form-control" rows="8" readonly>{{ $plan->features ? json_encode($plan->features, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) : '[]' }}</textarea>
                </div>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:1rem;">
            <div class="card">
                <div class="card-header" style="padding-bottom:12px;">
                    <div class="card-title">{{ __('ui.platform_plans_show_page.status') }}</div>
                </div>
                <div style="padding:0 18px 18px 18px;">
                    <div style="margin-bottom:12px;">
                        @if($plan->is_active)
                            <span class="badge badge-green"><i class="ri-checkbox-circle-line"></i> {{ __('ui.platform_plans_page.active') }}</span>
                        @else
                            <span class="badge badge-red"><i class="ri-close-circle-line"></i> {{ __('ui.platform_plans_page.disabled') }}</span>
                        @endif
                    </div>
                    <form id="toggle-plan-status-show" method="POST" action="{{ route('super_admin.platform.plans.status', $plan) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="is_active" value="{{ $plan->is_active ? 0 : 1 }}">
                        <button type="button"
                                class="btn {{ $plan->is_active ? 'btn-danger' : 'btn-primary' }} btn-sm"
                                onclick="confirmSend({ title: '{{ $plan->is_active ? __('ui.platform_plans_page.disable_prompt') : __('ui.platform_plans_page.enable_prompt') }}', message: '{{ $plan->is_active ? __('ui.platform_plans_page.disable_message') : __('ui.platform_plans_page.enable_message') }}', callback: function(){ document.getElementById('toggle-plan-status-show').submit(); } })">
                            <i class="{{ $plan->is_active ? 'ri-pause-circle-line' : 'ri-play-circle-line' }}"></i>
                            {{ $plan->is_active ? __('ui.platform_plans_page.disable') : __('ui.platform_plans_page.enable') }}
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="padding-bottom:12px;">
                    <div class="card-title">{{ __('ui.platform_plans_show_page.assigned_tenants_card') }}</div>
                </div>
                <div style="padding:0 18px 18px 18px;display:flex;flex-direction:column;gap:8px;">
                    @forelse($plan->tenants as $tenant)
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 10px;background:var(--page-bg);border-radius:8px;">
                            <div style="min-width:0;">
                                <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $tenant->name }}</div>
                                <div style="font-size:12px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $tenant->slug }}</div>
                            </div>
                            <span class="badge badge-gray">{{ number_format($tenant->users_count) }} {{ __('ui.platform_plans_show_page.users') }}</span>
                        </div>
                    @empty
                        <div style="font-size:13px;color:var(--text-muted);">{{ __('ui.platform_plans_show_page.no_assigned_tenants') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
