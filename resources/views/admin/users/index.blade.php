@extends('layouts.admin')

@section('title', 'Users')

@section('breadcrumb')
    <span>Users</span>
@endsection

@section('content')
    @php
        $routePrefix = explode('.', request()->route()?->getName() ?? 'admin.users.index')[0];
    @endphp

    {{-- Header --}}
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">Users</div>
            <div class="page-subtitle">Manage team members and permissions</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route($routePrefix . '.users.create') }}" class="btn btn-primary">
                <i class="ri-user-add-line"></i> Invite User
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET">
        <div class="table-toolbar" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-lg);margin-bottom:1rem">
            <div class="filter-input-wrap">
                <i class="ri-search-line"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search users…" class="filter-input">
            </div>
            <select name="role" onchange="this.form.submit()" class="toolbar-select">
                <option value="">All Roles</option>
                <option value="admin"      {{ request('role') === 'admin'      ? 'selected' : '' }}>Admin</option>
                <option value="supervisor" {{ request('role') === 'supervisor' ? 'selected' : '' }}>Supervisor</option>
                <option value="agent"      {{ request('role') === 'agent'      ? 'selected' : '' }}>Agent</option>
            </select>
            <select name="status" onchange="this.form.submit()" class="toolbar-select">
                <option value="">All Status</option>
                <option value="active"   {{ request('status') === 'active'   ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
            @if(request()->hasAny(['search','role','status']))
                <a href="{{ route($routePrefix . '.users.index') }}" class="btn btn-ghost btn-sm">Clear</a>
            @endif
        </div>
    </form>

    {{-- Table --}}
    <div class="card" style="padding:0">
        @if($users->isEmpty())
            <div class="empty-state">
                <div class="empty-state-icon"><i class="ri-team-line"></i></div>
                <h4>No users found</h4>
                <p>
                    @if(request()->hasAny(['search','role','status']))
                        Try adjusting your filters
                    @else
                        Invite your first team member
                    @endif
                </p>
                @if(!request()->hasAny(['search','role','status']))
                    <a href="{{ route($routePrefix . '.users.create') }}" class="btn btn-primary">Invite User</a>
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
                            <th>User</th>
                            <th>Role</th>
                            <th>Teams</th>
                            <th>Status</th>
                            <th>Last Login</th>
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
                                <span class="badge {{ $roleColors[$user->role] ?? 'badge-gray' }}">{{ ucfirst($user->role) }}</span>
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
                                    <span class="badge badge-green">Active</span>
                                @else
                                    <span class="badge badge-gray">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <span style="font-size:.8125rem;color:var(--text-muted)">
                                    {{ $user->last_login_at?->diffForHumans() ?? 'Never' }}
                                </span>
                            </td>
                            <td>
                                <div style="display:flex;gap:.25rem">
                                    <a href="{{ route($routePrefix . '.users.edit', $user) }}" class="action-btn" title="Edit">
                                        <i class="ri-edit-line"></i>
                                    </a>
                                    @if(auth()->id() !== $user->id)
                                    @can('impersonate', $user)
                                    <a href="{{ route($routePrefix . '.users.impersonate', $user) }}" class="action-btn" title="Impersonate"
                                       onclick="return confirm('Impersonate {{ $user->name }}?')">
                                        <i class="ri-user-shared-line"></i>
                                    </a>
                                    @endcan
                                    <button onclick="confirmDelete('{{ route($routePrefix . '.users.destroy', $user) }}', { title: 'Delete {{ addslashes($user->name) }}?', message: 'This will permanently remove the user account.' })"
                                            class="action-btn danger" title="Delete">
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
        <span class="bulk-count">0 selected</span>
        <span class="bulk-sep">|</span>
        <div class="bulk-actions">
            <form method="POST" action="{{ route($routePrefix . '.users.bulk') }}" id="bulkForm" style="display:flex;gap:.5rem;align-items:center;">
                @csrf
                <input type="hidden" name="ids" id="bulkIds">
                <select name="action" class="form-control" style="min-width:170px;">
                    <option value="activate">Activate</option>
                    <option value="deactivate">Deactivate</option>
                    <option value="delete">Delete</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">
                    Apply
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
            bulkCount.textContent = count + ' selected';
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

            if (bulkAction && bulkAction.value === 'delete' && !window.confirm('Delete the selected users? This cannot be undone.')) {
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
