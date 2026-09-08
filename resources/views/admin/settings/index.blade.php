@extends('layouts.admin')

@section('title', __('ui.settings_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.settings_page.breadcrumb') }}</span>
@endsection

@section('content')

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.settings_page.title') }}</div>
            <div class="page-subtitle">{{ __('ui.settings_page.subtitle') }}</div>
        </div>
    </div>

    <form action="{{ route('admin.settings.update') }}" method="POST" data-unsaved data-loading>
        @csrf @method('PUT')

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;margin-bottom:1.5rem">

            {{-- Left: General --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">{{ __('ui.settings_page.general') }}</div>
                </div>
                <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:1.25rem">

                    <div class="form-group">
                        <label class="form-label" for="name">{{ __('ui.settings_page.workspace_name') }} <span style="color:#ef4444">*</span></label>
                        <input type="text" id="name" name="name"
                               value="{{ old('name', $tenant->name) }}"
                               class="form-control @error('name') error @enderror"
                               required>
                        @error('name') <div class="form-error">{{ $message }}</div> @enderror
                        <div class="form-hint">{{ __('ui.settings_page.workspace_name_hint') }}</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">{{ __('ui.settings_page.workspace_slug') }}</label>
                        <input type="text" value="{{ $tenant->slug }}" class="form-control"
                               style="background:var(--page-bg);color:var(--text-muted);cursor:not-allowed" disabled>
                        <div class="form-hint">{{ __('ui.settings_page.workspace_slug_hint') }}</div>
                    </div>
                </div>
            </div>

            {{-- Right: Subscription --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">{{ __('ui.settings_page.subscription') }}</div>
                    @php
                        $statusColor = match($tenant->subscription_status) {
                            'active' => 'badge-green',
                            'trial'  => 'badge-orange',
                            default  => 'badge-red',
                        };
                    @endphp
                    <span class="badge {{ $statusColor }}">{{ ucfirst($tenant->subscription_status) }}</span>
                </div>
                <div style="padding:0 1.5rem 1.5rem;font-size:.875rem;display:flex;flex-direction:column;gap:0">

                    {{-- Plan name + price --}}
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:.75rem 0;border-bottom:1px solid var(--card-border)">
                        <span style="color:var(--text-muted)">{{ __('ui.settings_page.plan') }}</span>
                        <span style="font-weight:700;color:var(--text-primary)">
                            {{ $tenant->plan?->name ?? __('ui.settings_page.no_plan') }}
                            @if($tenant->plan?->price_monthly)
                                <span style="font-weight:400;color:var(--text-muted);font-size:.8125rem">&nbsp;${{ number_format((float)$tenant->plan->price_monthly, 2) }}/mo</span>
                            @endif
                        </span>
                    </div>

                    {{-- Subscription / trial date --}}
                    @if($tenant->subscription_status === 'trial' && $tenant->trial_ends_at)
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:.75rem 0;border-bottom:1px solid var(--card-border)">
                        <span style="color:var(--text-muted)">{{ __('ui.settings_page.trial_ends') }}</span>
                        <span style="font-weight:600;color:{{ $tenant->trial_ends_at->isPast() ? '#ef4444' : ($tenant->trial_ends_at->diffInDays() < 5 ? '#f59e0b' : 'var(--text-primary)') }}">
                            {{ $tenant->trial_ends_at->format('M j, Y') }}
                            <span style="font-weight:400;font-size:.8125rem;color:var(--text-muted)">({{ $tenant->trial_ends_at->diffForHumans() }})</span>
                        </span>
                    </div>
                    @elseif($latestPayment?->paid_at)
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:.75rem 0;border-bottom:1px solid var(--card-border)">
                        <span style="color:var(--text-muted)">Subscribed since</span>
                        <span style="font-weight:600">{{ $latestPayment->paid_at->format('M j, Y') }}</span>
                    </div>
                    @endif

                    {{-- Plan limits --}}
                    @if($tenant->plan)
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:.75rem 0;border-bottom:1px solid var(--card-border)">
                        <span style="color:var(--text-muted)">{{ __('ui.settings_page.max_users') }}</span>
                        <span style="font-weight:600">{{ $tenant->plan->max_users }}</span>
                    </div>
                    @endif

                    {{-- Actions --}}
                    <div style="display:flex;flex-direction:column;gap:.625rem;padding-top:1rem">
                        @if($upgradePlans->isNotEmpty())
                        <a href="{{ route(auth()->user()->routeNamePrefix() . '.billing.index') }}"
                           class="btn btn-primary btn-sm" style="width:100%;justify-content:center">
                            <i class="ri-arrow-up-circle-line"></i> Upgrade Plan
                        </a>
                        @endif
                        <a href="{{ route(auth()->user()->routeNamePrefix() . '.billing.index') }}"
                           class="btn btn-outline btn-sm" style="width:100%;justify-content:center">
                            <i class="ri-bank-card-line"></i> {{ __('ui.settings_page.manage_billing') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div style="display:flex;justify-content:flex-end;gap:.5rem">
            <button type="submit" class="btn btn-primary">{{ __('ui.settings_page.save_settings') }}</button>
        </div>
    </form>

@endsection
