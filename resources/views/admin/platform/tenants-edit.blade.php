@extends('layouts.admin')

@section('title', 'Edit Tenant')

@section('breadcrumb')
    <span>Platform</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <a href="{{ route('super_admin.platform.tenants') }}" style="color:var(--text-secondary);text-decoration:none">Tenants</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ $tenant->name }}</span>
@endsection

@section('content')
<div style="display:grid;grid-template-columns:1fr 300px;gap:1.25rem;align-items:start;">
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Edit Tenant</div>
                <div class="card-subtitle">Update subscription and tenant configuration</div>
            </div>
            <a href="{{ route('super_admin.platform.tenants.show', $tenant) }}" class="btn btn-outline btn-sm">
                <i class="ri-eye-line"></i> View
            </a>
        </div>

        <form method="POST" action="{{ route('super_admin.platform.tenants.update', $tenant) }}" style="padding:20px;">
            @csrf
            @method('PUT')

            @include('admin.platform.partials.tenant-form-fields', ['tenant' => $tenant])

            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                <a href="{{ route('super_admin.platform.tenants.show', $tenant) }}" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ri-save-line"></i> Save Changes
                </button>
            </div>
        </form>
    </div>

    <div style="display:flex;flex-direction:column;gap:1rem;">
        <div class="card">
            <div class="card-header" style="padding-bottom:12px;">
                <div class="card-title">Tenant Stats</div>
            </div>
            <div style="padding:0 18px 18px 18px;display:flex;flex-direction:column;gap:8px;font-size:13px;">
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:var(--text-muted);">Users</span>
                    <strong>{{ number_format($tenant->users_count) }}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:var(--text-muted);">Teams</span>
                    <strong>{{ number_format($tenant->teams_count) }}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:var(--text-muted);">Instances</span>
                    <strong>{{ number_format($tenant->instances_count) }}</strong>
                </div>
            </div>
        </div>

        <div class="card">
            <div style="padding:16px;">
                <div style="font-size:13px;font-weight:700;color:var(--red);margin-bottom:6px;">Danger Zone</div>
                <div style="font-size:12.5px;color:var(--text-muted);margin-bottom:12px;">
                    Deleting this tenant permanently removes all tenant data.
                </div>
                <button type="button"
                        class="btn btn-danger btn-sm"
                        onclick="confirmDelete('{{ route('super_admin.platform.tenants.destroy', $tenant) }}', { title: 'Delete {{ addslashes($tenant->name) }}?', message: 'This will permanently delete the tenant and all related records.' })">
                    <i class="ri-delete-bin-line"></i> Delete Tenant
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
