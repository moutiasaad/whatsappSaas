@extends('layouts.admin')

@section('title', __('ui.platform_global_settings_page.edit_title'))

@section('breadcrumb')
    <span>{{ __('ui.platform_global_settings_page.breadcrumb_root') }}</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <a href="{{ route('super_admin.platform.global-settings') }}" style="color:var(--text-secondary);text-decoration:none">{{ __('ui.platform_global_settings_page.breadcrumb') }}</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ __('ui.platform_global_settings_page.edit_title') }}</span>
@endsection

@section('content')
<div style="display:grid;grid-template-columns:1fr 320px;gap:1.25rem;align-items:start;">
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">{{ __('ui.platform_global_settings_page.edit_title') }}</div>
                <div class="card-subtitle">{{ __('ui.platform_global_settings_page.edit_subtitle') }}</div>
            </div>
        </div>

        <form method="POST" action="{{ route('super_admin.platform.global-settings.update') }}" style="padding:20px;">
            @csrf
            @method('PUT')

            @include('admin.platform.partials.global-settings-form-fields')

            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                <a href="{{ route('super_admin.platform.global-settings') }}" class="btn btn-outline">{{ __('ui.cancel') }}</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ri-save-line"></i> {{ __('ui.platform_global_settings_page.save_settings') }}
                </button>
            </div>
        </form>
    </div>

    <div style="display:flex;flex-direction:column;gap:1rem;">
        <div class="card">
            <div class="card-header" style="padding-bottom:12px;">
                <div class="card-title">{{ __('ui.platform_global_settings_page.runtime_snapshot') }}</div>
            </div>
            <div style="padding:0 18px 18px 18px;display:flex;flex-direction:column;gap:8px;font-size:13px;">
                @foreach($runtime as $key => $value)
                    <div style="display:flex;justify-content:space-between;gap:10px;">
                        <span style="color:var(--text-muted);">{{ __('ui.platform_global_settings_page.runtime_labels.' . $key) }}</span>
                        <strong style="text-align:right;">{{ $value ?: '-' }}</strong>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
