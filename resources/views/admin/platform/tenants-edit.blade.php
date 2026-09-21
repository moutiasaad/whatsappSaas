@extends('layouts.admin')

@section('title', __('ui.platform_tenants_edit_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.platform_tenants_edit_page.breadcrumb_root') }}</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <a href="{{ route('super_admin.platform.tenants') }}" style="color:var(--text-secondary);text-decoration:none">{{ __('ui.platform_tenants_edit_page.breadcrumb') }}</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ $tenant->name }}</span>
@endsection

@section('content')
    <div style="display:grid;grid-template-columns:1fr 300px;gap:1.25rem;align-items:start;">
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">{{ __('ui.platform_tenants_edit_page.page_title') }}</div>
                <div class="card-subtitle">{{ __('ui.platform_tenants_edit_page.subtitle') }}</div>
            </div>
            <a href="{{ route('super_admin.platform.tenants.show', $tenant) }}" class="btn btn-outline btn-sm">
                <i class="ri-eye-line"></i> {{ __('ui.platform_tenants_edit_page.view') }}
            </a>
        </div>

        <form method="POST" action="{{ route('super_admin.platform.tenants.update', $tenant) }}" style="padding:20px;">
            @csrf
            @method('PUT')

            @include('admin.platform.partials.tenant-form-fields', [
    'tenant'       => $tenant,
    'lastPayment'  => $lastPayment  ?? null,
    'suggestStart' => $suggestStart ?? null,
    'suggestEnd'   => $suggestEnd   ?? null,
])

            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                <a href="{{ route('super_admin.platform.tenants.show', $tenant) }}" class="btn btn-outline">{{ __('ui.cancel') }}</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ri-save-line"></i> {{ __('ui.platform_tenants_edit_page.save_changes') }}
                </button>
            </div>
        </form>
    </div>

    <div style="display:flex;flex-direction:column;gap:1rem;">
        <div class="card">
            <div class="card-header" style="padding-bottom:12px;">
                <div class="card-title">{{ __('ui.platform_tenants_edit_page.tenant_stats') }}</div>
            </div>
            <div style="padding:0 18px 18px 18px;display:flex;flex-direction:column;gap:8px;font-size:13px;">
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:var(--text-muted);">{{ __('ui.platform_tenants_edit_page.users') }}</span>
                    <strong>{{ number_format($tenant->users_count) }}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:var(--text-muted);">{{ __('ui.platform_tenants_edit_page.teams') }}</span>
                    <strong>{{ number_format($tenant->teams_count) }}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:var(--text-muted);">{{ __('ui.platform_tenants_edit_page.instances') }}</span>
                    <strong>{{ number_format($tenant->instances_count) }}</strong>
                </div>
            </div>
        </div>

        <div class="card">
            <div style="padding:16px;">
                <div style="font-size:13px;font-weight:700;color:var(--red);margin-bottom:6px;">{{ __('ui.platform_tenants_edit_page.danger_zone') }}</div>
                <div style="font-size:12.5px;color:var(--text-muted);margin-bottom:12px;">
                    {{ __('ui.platform_tenants_edit_page.danger_zone_hint') }}
                </div>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    {{-- Block / Unblock. Copies the row action's semantics
                         (flip is_active) but drops the modal in favour of a
                         plain form + native confirm — same pattern as the
                         expire-trial form on tenants-show. --}}
                    <form method="POST" action="{{ route('super_admin.platform.tenants.toggle-active', $tenant) }}"
                          onsubmit="return confirm(@js($tenant->is_active ? __('ui.platform_tenants_page.block_prompt', ['name' => $tenant->name]) : __('ui.platform_tenants_page.unblock_prompt', ['name' => $tenant->name])))"
                          style="margin:0;">
                        @csrf
                        @method('PATCH')
                        @if($tenant->is_active)
                            <button type="submit" class="btn btn-danger btn-sm" style="width:100%;">
                                <i class="ri-forbid-2-line"></i> {{ __('ui.platform_tenants_page.block') }}
                            </button>
                        @else
                            <button type="submit" class="btn btn-outline btn-sm" style="width:100%;color:var(--brand);border-color:rgba(16,185,129,.4);">
                                <i class="ri-checkbox-circle-line"></i> {{ __('ui.platform_tenants_page.unblock') }}
                            </button>
                        @endif
                    </form>

                    {{-- Archive / Restore. Distinct from block: archive
                         soft-hides the tenant from the default list; block
                         is a live suspension that stays visible. --}}
                    <form method="POST" action="{{ route('super_admin.platform.tenants.toggle-archive', $tenant) }}"
                          onsubmit="return confirm(@js($tenant->archived_at ? __('ui.platform_tenants_page.restore_prompt', ['name' => $tenant->name]) : __('ui.platform_tenants_page.archive_prompt', ['name' => $tenant->name])))"
                          style="margin:0;">
                        @csrf
                        @method('PATCH')
                        @if($tenant->archived_at)
                            <button type="submit" class="btn btn-outline btn-sm" style="width:100%;color:var(--brand);border-color:rgba(16,185,129,.4);">
                                <i class="ri-inbox-unarchive-line"></i> {{ __('ui.platform_tenants_page.restore') }}
                            </button>
                        @else
                            <button type="submit" class="btn btn-outline btn-sm" style="width:100%;">
                                <i class="ri-inbox-archive-line"></i> {{ __('ui.platform_tenants_page.archive') }}
                            </button>
                        @endif
                    </form>

                    <button type="button"
                            class="btn btn-danger btn-sm" style="width:100%;"
                            onclick="confirmDelete('{{ route('super_admin.platform.tenants.destroy', $tenant) }}', { title: @js(__('ui.platform_tenants_page.delete_prompt', ['name' => $tenant->name])), message: @js(__('ui.platform_tenants_page.delete_message')) })">
                        <i class="ri-delete-bin-line"></i> {{ __('ui.platform_tenants_page.delete') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
