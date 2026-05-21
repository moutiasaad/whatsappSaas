@extends('layouts.admin')

@section('title', __('ui.users_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.users_page.breadcrumb') }}</span>
@endsection

@section('content')
    @php
        $routePrefix = explode('.', request()->route()?->getName() ?? 'admin.users.index')[0];
    @endphp

    {{-- Header --}}
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.users_page.title') }}</div>
            <div class="page-subtitle">{{ __('ui.users_page.subtitle') }}</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route($routePrefix . '.users.create') }}" class="btn btn-primary">
                <i class="ri-user-add-line"></i> {{ __('ui.users_page.invite_user') }}
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET">
        <div class="table-toolbar" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-lg);margin-bottom:1rem">
            <div class="filter-input-wrap">
                <i class="ri-search-line"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('ui.users_page.search_placeholder') }}" class="filter-input">
            </div>
            <select name="role" onchange="this.form.submit()" class="toolbar-select">
                <option value="">{{ __('ui.users_page.all_roles') }}</option>
                <option value="admin"      {{ request('role') === 'admin'      ? 'selected' : '' }}>{{ __('ui.roles.admin') }}</option>
                <option value="supervisor" {{ request('role') === 'supervisor' ? 'selected' : '' }}>{{ __('ui.roles.supervisor') }}</option>
                <option value="agent"      {{ request('role') === 'agent'      ? 'selected' : '' }}>{{ __('ui.roles.agent') }}</option>
            </select>
            <select name="status" onchange="this.form.submit()" class="toolbar-select">
                <option value="">{{ __('ui.users_page.all_status') }}</option>
                <option value="active"   {{ request('status') === 'active'   ? 'selected' : '' }}>{{ __('ui.users_page.active') }}</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>{{ __('ui.users_page.inactive') }}</option>
            </select>
            <button type="submit" class="btn btn-outline btn-sm">{{ __('ui.users_page.filter') }}</button>
            @if(request()->hasAny(['search','role','status']))
                <a href="{{ route($routePrefix . '.users.index') }}" class="btn btn-ghost btn-sm">{{ __('ui.users_page.clear') }}</a>
            @endif
        </div>
    </form>

    {{-- Table --}}
    <div class="card" style="padding:0">
        @if($users->isEmpty())
            <div class="empty-state">
                <div class="empty-state-icon"><i class="ri-team-line"></i></div>
                <h4>{{ __('ui.users_page.no_users_found') }}</h4>
                <p>
                    @if(request()->hasAny(['search','role','status']))
                        {{ __('ui.users_page.try_adjusting') }}
                    @else
                        {{ __('ui.users_page.invite_first_member') }}
                    @endif
                </p>
                @if(!request()->hasAny(['search','role','status']))
                    <a href="{{ route($routePrefix . '.users.create') }}" class="btn btn-primary">{{ __('ui.users_page.invite_user') }}</a>
                @endif
            </div>
        @else
            <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:2.5rem">
                                <input type="checkbox" class="header-cb" style="cursor:pointer;accent-color:var(--brand)">
                            </th>
                            <th>{{ __('ui.users_page.user') }}</th>
                            <th>{{ __('ui.users_page.role') }}</th>
                            <th>{{ __('ui.users_page.teams') }}</th>
                            <th>{{ __('ui.users_page.status') }}</th>
                            <th>{{ __('ui.users_page.last_login') }}</th>
                            <th style="width:100px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                        <tr>
                            <td>
                                <input type="checkbox" class="row-cb" value="{{ $user->id }}"
                                       style="cursor:pointer;accent-color:var(--brand)">
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:.75rem">
                                    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}"
                                         style="width:2rem;height:2rem;border-radius:50%;object-fit:cover">
                                    <div>
                                        <div style="font-weight:500;font-size:.875rem">{{ $user->name }}</div>
                                        <div style="font-size:.75rem;color:var(--text-muted)">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @php
                                    $roleColors = ['admin' => 'badge-purple', 'supervisor' => 'badge-blue', 'agent' => 'badge-brand'];
                                @endphp
                                <span class="badge {{ $roleColors[$user->role] ?? 'badge-gray' }}">{{ __('ui.roles.' . $user->role, ['role' => ucfirst($user->role)]) }}</span>
                            </td>
                            <td>
                                <div style="display:flex;flex-wrap:wrap;gap:.25rem">
                                    @foreach($user->teams->take(3) as $team)
                                        <span class="badge badge-gray" style="font-size:.6875rem">{{ $team->name }}</span>
                                    @endforeach
                                    @if($user->teams->count() > 3)
                                        <span class="badge badge-gray" style="font-size:.6875rem">+{{ $user->teams->count() - 3 }}</span>
                                    @endif
                                    @if($user->teams->isEmpty())
                                        <span style="color:var(--text-muted);font-size:.8125rem">—</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if($user->is_active)
                                    <span class="badge badge-green">{{ __('ui.users_page.active') }}</span>
                                @else
                                    <span class="badge badge-gray">{{ __('ui.users_page.inactive') }}</span>
                                @endif
                            </td>
                            <td>
                                <span style="font-size:.8125rem;color:var(--text-muted)">
                                    {{ $user->last_login_at?->diffForHumans() ?? __('ui.users_page.never') }}
                                </span>
                            </td>
                            <td>
                                <div style="display:flex;gap:.25rem">
                                    <a href="{{ route($routePrefix . '.users.edit', $user) }}" class="action-btn" title="{{ __('ui.users_page.edit') }}">
                                        <i class="ri-edit-line"></i>
                                    </a>
                                    @if(auth()->id() !== $user->id)
                                    @can('impersonate', $user)
                                    <a href="{{ route($routePrefix . '.users.impersonate', $user) }}" class="action-btn" title="{{ __('ui.users_page.impersonate') }}"
                                       onclick="event.preventDefault(); confirmSend({ title: @js(__('ui.users_page.impersonate')), message: @js(__('ui.users_page.impersonate_confirm', ['name' => $user->name])), callback: function(){ window.location.href = @js(route($routePrefix . '.users.impersonate', $user)); } })">
                                        <i class="ri-user-shared-line"></i>
                                    </a>
                                    @endcan
                                    <button onclick="confirmDelete('{{ route($routePrefix . '.users.destroy', $user) }}', { title: @js(__('ui.users_page.delete_user_prompt')).replace(':name', '{{ addslashes($user->name) }}'), message: @js(__('ui.users_page.delete_user_message')) })"
                                            class="action-btn danger" title="{{ __('ui.users_page.delete') }}">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($users->hasPages())
            <div style="padding:1rem 1.25rem;border-top:1px solid var(--card-border)">
                {{ $users->links('admin.partials.pagination') }}
            </div>
            @endif
        @endif
    </div>

    {{-- Bulk Bar --}}
    <div class="bulk-bar" id="userBulkBar">
        <span class="bulk-count">{{ __('ui.users_page.bulk_selected_zero') }}</span>
        <span class="bulk-sep">|</span>
        <div class="bulk-actions">
            <form method="POST" action="{{ route($routePrefix . '.users.bulk') }}" id="bulkForm" style="display:flex;gap:.5rem;align-items:center;">
                @csrf
                <input type="hidden" name="ids" id="bulkIds">
                <select name="action" class="form-control" style="min-width:170px;">
                    <option value="activate">{{ __('ui.users_page.activate') }}</option>
                    <option value="deactivate">{{ __('ui.users_page.deactivate') }}</option>
                    <option value="delete">{{ __('ui.users_page.delete') }}</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">
                    {{ __('ui.users_page.apply') }}
                </button>
            </form>
        </div>
        <button type="button" class="bulk-close" onclick="bulk.clear()"><i class="ri-close-line"></i></button>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var headerCheckbox = document.querySelector('.header-cb');
    var rowCheckboxes = Array.from(document.querySelectorAll('.row-cb'));
    var bulkBar = document.getElementById('userBulkBar');
    var bulkCount = bulkBar ? bulkBar.querySelector('.bulk-count') : null;
    var bulkForm = document.getElementById('bulkForm');
    var bulkIds = document.getElementById('bulkIds');
    var bulkAction = bulkForm ? bulkForm.querySelector('select[name="action"]') : null;

    function getSelected() {
        return rowCheckboxes.filter(function (checkbox) {
            return checkbox.checked;
        }).map(function (checkbox) {
            return checkbox.value;
        });
    }

    function syncBulkUi() {
        var selected = getSelected();
        var count = selected.length;

        if (bulkCount) {
            bulkCount.textContent = count + ' ' + @json(__('ui.selected_items'));
        }

        if (bulkBar) {
            bulkBar.classList.toggle('visible', count > 0);
        }

        if (headerCheckbox) {
            var allSelected = rowCheckboxes.length > 0 && count === rowCheckboxes.length;
            headerCheckbox.checked = allSelected;
            headerCheckbox.indeterminate = count > 0 && !allSelected;
        }

        if (bulkIds) {
            bulkIds.value = selected.join(',');
        }
    }

    window.bulk = {
        getSelected: getSelected,
        clear: function () {
            if (headerCheckbox) {
                headerCheckbox.checked = false;
                headerCheckbox.indeterminate = false;
            }

            rowCheckboxes.forEach(function (checkbox) {
                checkbox.checked = false;
            });

            syncBulkUi();
        }
    };

    if (headerCheckbox) {
        headerCheckbox.addEventListener('change', function () {
            rowCheckboxes.forEach(function (checkbox) {
                checkbox.checked = headerCheckbox.checked;
            });

            syncBulkUi();
        });
    }

    rowCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', syncBulkUi);
    });

    if (bulkForm) {
        bulkForm.addEventListener('submit', function (event) {
            syncBulkUi();

            if (bulkAction && bulkAction.value === 'delete' && !window.confirm(@js(__('ui.users_page.delete_selected_confirm')))) {
                event.preventDefault();
            }
        });
    }

    if (window.initSS && bulkForm) {
        window.initSS(bulkForm);
    }

    syncBulkUi();
});
</script>
@endsection
