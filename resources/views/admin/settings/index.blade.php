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
                    <span class="badge {{ $tenant->subscription_status === 'active' ? 'badge-green' : ($tenant->subscription_status === 'trial' ? 'badge-orange' : 'badge-red') }}">
                        {{ ucfirst($tenant->subscription_status) }}
                    </span>
                </div>
                <div style="padding:0 1.5rem 1.5rem;font-size:.875rem;display:flex;flex-direction:column;gap:.75rem">
                    <div style="display:flex;justify-content:space-between;padding:.625rem 0;border-bottom:1px solid var(--card-border)">
                        <span style="color:var(--text-muted)">{{ __('ui.settings_page.plan') }}</span>
                        <span style="font-weight:600">{{ $tenant->plan?->name ?? __('ui.settings_page.no_plan') }}</span>
                    </div>
                    @if($tenant->plan)
                    <div style="display:flex;justify-content:space-between;padding:.625rem 0;border-bottom:1px solid var(--card-border)">
                        <span style="color:var(--text-muted)">{{ __('ui.settings_page.max_users') }}</span>
                        <span style="font-weight:600">{{ $tenant->plan->max_users }}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:.625rem 0;border-bottom:1px solid var(--card-border)">
                        <span style="color:var(--text-muted)">{{ __('ui.settings_page.max_instances') }}</span>
                        <span style="font-weight:600">{{ $tenant->plan->max_instances }}</span>
                    </div>
                    @endif
                    @if($tenant->trial_ends_at)
                    <div style="display:flex;justify-content:space-between;padding:.625rem 0;border-bottom:1px solid var(--card-border)">
                        <span style="color:var(--text-muted)">{{ __('ui.settings_page.trial_ends') }}</span>
                        <span style="font-weight:600">{{ $tenant->trial_ends_at->format('M j, Y') }}</span>
                    </div>
                    @endif
                    <div style="padding-top:.25rem">
                        @if(auth()->user()->isSuperAdmin())
                            <a href="{{ route('admin.billing.index') }}" class="btn btn-outline btn-sm" style="width:100%;justify-content:center">
                                <i class="ri-bank-card-line"></i> {{ __('ui.settings_page.manage_billing') }}
                            </a>
                        @endif
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
