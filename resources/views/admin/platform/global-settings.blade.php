@extends('layouts.admin')

@section('title', __('ui.platform_global_settings_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.platform_global_settings_page.breadcrumb_root') }}</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ __('ui.platform_global_settings_page.breadcrumb') }}</span>
@endsection

@section('content')
<div>
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.platform_global_settings_page.page_title') }}</div>
            <div class="page-subtitle">{{ __('ui.platform_global_settings_page.subtitle') }}</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('super_admin.platform.global-settings.edit') }}" class="btn btn-primary">
                <i class="ri-pencil-line"></i> {{ __('ui.platform_global_settings_page.edit_settings') }}
            </a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-toggle-line"></i></div>
            <div class="stat-card-value">{{ collect($settings)->where('type', 'boolean')->count() }}</div>
            <div class="stat-card-label">{{ __('ui.platform_global_settings_page.feature_toggles') }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-check-line"></i></div>
            <div class="stat-card-value">{{ collect($settings)->where('type', 'boolean')->filter(fn($s) => $s['value'])->count() }}</div>
            <div class="stat-card-label">{{ __('ui.platform_global_settings_page.enabled_toggles') }}</div>
        </div>
        <div class="stat-card red">
            <div class="stat-card-icon"><i class="ri-close-line"></i></div>
            <div class="stat-card-value">{{ collect($settings)->where('type', 'boolean')->filter(fn($s) => !$s['value'])->count() }}</div>
            <div class="stat-card-label">{{ __('ui.platform_global_settings_page.disabled_toggles') }}</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-card-icon"><i class="ri-tools-line"></i></div>
            <div class="stat-card-value">{{ count($runtime) }}</div>
            <div class="stat-card-label">{{ __('ui.platform_global_settings_page.runtime_signals') }}</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;align-items:start;">
        <div class="card">
            <div class="card-header">
                <div class="card-title">{{ __('ui.platform_global_settings_page.editable_settings') }}</div>
            </div>
            <div style="padding:1rem;display:grid;gap:.75rem;">
                @foreach($settings as $key => $meta)
                    <div style="padding:.875rem;border:1px solid var(--card-border);border-radius:.75rem;background:var(--page-bg);">
                        <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;">{{ $meta['label'] }}</div>
                        <div style="margin-top:.35rem;">
                            @if($meta['type'] === 'boolean')
                                @if($meta['value'])
                                    <span class="badge badge-green"><i class="ri-checkbox-circle-line"></i> {{ __('ui.platform_global_settings_page.enabled') }}</span>
                                @else
                                    <span class="badge badge-red"><i class="ri-close-circle-line"></i> {{ __('ui.platform_global_settings_page.disabled') }}</span>
                                @endif
                            @else
                                <div style="font-size:.9375rem;font-weight:600;color:var(--text-primary);">{{ $meta['value'] ?: '-' }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">{{ __('ui.platform_global_settings_page.runtime_configuration') }}</div>
            </div>
            <div style="padding:1rem;display:grid;gap:.75rem;">
                @foreach($runtime as $key => $value)
                    <div style="padding:.875rem;border:1px solid var(--card-border);border-radius:.75rem;background:#fff;">
                        <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;">{{ __('ui.platform_global_settings_page.runtime_labels.' . $key) }}</div>
                        <div style="font-size:.9375rem;font-weight:600;color:var(--text-primary);margin-top:.25rem;">{{ $value ?: '-' }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
