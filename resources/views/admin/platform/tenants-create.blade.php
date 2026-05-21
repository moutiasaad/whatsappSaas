@extends('layouts.admin')

@section('title', __('ui.platform_tenants_create_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.platform_tenants_create_page.breadcrumb_root') }}</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <a href="{{ route('super_admin.platform.tenants') }}" style="color:var(--text-secondary);text-decoration:none">{{ __('ui.platform_tenants_create_page.breadcrumb') }}</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ __('ui.platform_tenants_create_page.page_title') }}</span>
@endsection

@section('content')
<div>
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.platform_tenants_create_page.page_title') }}</div>
            <div class="page-subtitle">{{ __('ui.platform_tenants_create_page.subtitle') }}</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('super_admin.platform.tenants') }}" class="btn btn-outline">
                <i class="ri-arrow-left-line"></i> {{ __('ui.platform_tenants_create_page.back_to_tenants') }}
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">{{ __('ui.platform_tenants_create_page.tenant_details') }}</div>
                <div class="card-subtitle">{{ __('ui.platform_tenants_create_page.tenant_details_subtitle') }}</div>
            </div>
        </div>

        <form method="POST" action="{{ route('super_admin.platform.tenants.store') }}" style="padding:20px;">
            @csrf
            @include('admin.platform.partials.tenant-form-fields')

            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                <a href="{{ route('super_admin.platform.tenants') }}" class="btn btn-outline">{{ __('ui.cancel') }}</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ri-save-line"></i> {{ __('ui.platform_tenants_create_page.create_tenant') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
