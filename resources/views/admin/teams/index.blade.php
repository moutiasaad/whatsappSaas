@extends('layouts.admin')

@section('title', 'Teams')

@section('breadcrumb')
    <span>Teams</span>
@endsection

@section('content')
    @php
        $panelPrefix = auth()->user()->routeNamePrefix();
        $canManageTeams = auth()->user()->hasAnyRole(['admin', 'super_admin']);
        $isSupervisor = auth()->user()->isSupervisor();
    @endphp

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">Teams</div>
            <div class="page-subtitle">
                @if($isSupervisor)
                    Manages one or more support teams
                @else
                    Organize agents into teams for conversation routing
                @endif
            </div>
        </div>
        @if($canManageTeams)
            <div class="page-header-actions">
                <a href="{{ route($panelPrefix . '.teams.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> New Team
                </a>
            </div>
        @endif
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-team-line"></i></div>
            <div class="stat-card-value">{{ number_format($stats['total'] ?? 0) }}</div>
            <div class="stat-card-label">Total Teams</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-checkbox-circle-line"></i></div>
            <div class="stat-card-value">{{ number_format($stats['active'] ?? 0) }}</div>
            <div class="stat-card-label">Active Teams</div>
        </div>
        <div class="stat-card red">
            <div class="stat-card-icon"><i class="ri-pause-circle-line"></i></div>
            <div class="stat-card-value">{{ number_format($stats['inactive'] ?? 0) }}</div>
            <div class="stat-card-label">Inactive Teams</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-card-icon"><i class="ri-inbox-line"></i></div>
            <div class="stat-card-value">{{ number_format($stats['pool'] ?? 0) }}</div>
            <div class="stat-card-label">Pool Conversations</div>
        </div>
    </div>

    <form method="GET">
        <div class="table-toolbar" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-lg);margin-bottom:1rem">
            <div class="filter-input-wrap">
                <i class="ri-search-line"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search team name or description..." class="filter-input">
            </div>

            <select name="is_active" class="toolbar-select" onchange="this.form.submit()">
                <option value="">Active + Inactive</option>
                <option value="1" @selected(request('is_active') === '1')>Active only</option>
                <option value="0" @selected(request('is_active') === '0')>Inactive only</option>
            </select>

            <select name="sort" class="toolbar-select" onchange="this.form.submit()">
                <option value="name_asc" @selected(request('sort', 'name_asc') === 'name_asc')>Name A-Z</option>
                <option value="name_desc" @selected(request('sort') === 'name_desc')>Name Z-A</option>
                <option value="activity_desc" @selected(request('sort') === 'activity_desc')>Most Active</option>
                <option value="pool_desc" @selected(request('sort') === 'pool_desc')>Most In Pool</option>
            </select>

            <button type="submit" class="btn btn-outline btn-sm">Filter</button>

            @if(request()->hasAny(['search', 'is_active', 'sort']))
                <a href="{{ route($panelPrefix . '.teams.index') }}" class="btn btn-ghost btn-sm">Clear</a>
            @endif
        </div>
    </form>

    <div class="card" style="padding:0">
        @if($teams->isEmpty())
            <div class="empty-state" style="padding:3rem">
                <div class="empty-state-icon"><i class="ri-team-line"></i></div>
                <h4>No teams found</h4>
                <p>Try adjusting your filters.</p>
                @if($canManageTeams)
                    <a href="{{ route($panelPrefix . '.teams.create') }}" class="btn btn-primary">Create Team</a>
                @endif
            </div>
        @else
            <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none">
                <table class="data-table">
                    <thead>
                        <tr>
                            @if($canManageTeams)
                                <th style="width:2.5rem">
                                    <input type="checkbox" class="header-cb" style="cursor:pointer;">
                                </th>
                            @endif
                            <th>Team</th>
                            <th>Status</th>
                            <th>Members</th>
                            <th>Active Conversations</th>
                            <th>Pool</th>
                            <th>Closed</th>
                            <th style="width:120px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($teams as $team)
                            <tr>
                                @if($canManageTeams)
                                    <td>
                                        <input type="checkbox" class="row-cb" value="{{ $team->id }}" style="cursor:pointer;">
                                    </td>
                                @endif
                                <td>
                                    <div style="display:flex;align-items:center;gap:.75rem;min-width:0;">
                                        <div style="width:2rem;height:2rem;border-radius:.625rem;background:linear-gradient(135deg,rgba(16,185,129,.15),rgba(5,150,105,.25));display:flex;align-items:center;justify-content:center;color:var(--brand);flex-shrink:0;">
                                            <i class="ri-team-line"></i>
                                        </div>
                                        <div style="display:flex;flex-direction:column;gap:.125rem;min-width:0;">
                                            <span style="font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $team->name }}</span>
                                            <span style="font-size:.75rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $team->description ?: 'No description' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge {{ $team->is_active ? 'badge-green' : 'badge-gray' }}">
                                        <i class="{{ $team->is_active ? 'ri-checkbox-circle-line' : 'ri-pause-circle-line' }}"></i>
                                        {{ $team->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-purple">
                                        <i class="ri-user-line"></i>
                                        {{ number_format($team->users_count ?? $team->users->count()) }}
                                    </span>
                                </td>
                                <td>{{ number_format($team->active_conversations_count ?? 0) }}</td>
                                <td>{{ number_format($team->pool_count ?? 0) }}</td>
                                <td>{{ number_format($team->closed_count ?? 0) }}</td>
                                <td>
                                    <div style="display:flex;gap:.25rem;justify-content:flex-end;">
                                        @if($canManageTeams)
                                            <a href="{{ route($panelPrefix . '.teams.edit', $team) }}" class="action-btn" title="Manage">
                                                <i class="ri-pencil-line"></i>
                                            </a>
                                            <a href="{{ route($panelPrefix . '.conversations.index', ['team_id' => $team->id]) }}" class="action-btn" title="View Conversations">
                                                <i class="ri-message-3-line"></i>
                                            </a>
                                            <button type="button"
                                                    class="action-btn danger"
                                                    title="Delete"
                                                    onclick="confirmDelete('{{ route($panelPrefix . '.teams.destroy', $team) }}', { title: 'Delete {{ addslashes($team->name) }}?', message: 'Existing conversations will become unassigned.' })">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        @else
                                            <a href="{{ route($panelPrefix . '.teams.edit', $team) }}" class="action-btn" title="Manage">
                                                <i class="ri-pencil-line"></i>
                                            </a>
                                            <a href="{{ route($panelPrefix . '.conversations.index', ['team_id' => $team->id]) }}" class="action-btn" title="View Conversations">
                                                <i class="ri-message-3-line"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($teams->hasPages())
                <div style="padding:1rem 1.25rem;border-top:1px solid var(--card-border)">
                    {{ $teams->links('admin.partials.pagination') }}
                </div>
            @endif
        @endif
    </div>

    @if($canManageTeams && $teams->isNotEmpty())
        <div class="bulk-bar" id="teamBulkBar">
            <span class="bulk-count">0 selected</span>
            <span class="bulk-sep">|</span>
            <div class="bulk-actions">
                <form method="POST" action="{{ route($panelPrefix . '.teams.bulk') }}" id="teamBulkForm" style="display:flex;gap:.5rem;align-items:center;">
                    @csrf
                    <input type="hidden" name="ids" id="teamBulkIds">
                    <select name="action" class="form-control" style="min-width:170px;">
                        <option value="enable">Enable</option>
                        <option value="disable">Disable</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                </form>
            </div>
            <button type="button" class="bulk-close" onclick="teamBulk.clear()"><i class="ri-close-line"></i></button>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var headerCheckbox = document.querySelector('.header-cb');
            var rowCheckboxes = Array.from(document.querySelectorAll('.row-cb'));
            var bulkBar = document.getElementById('teamBulkBar');
            var bulkCount = bulkBar ? bulkBar.querySelector('.bulk-count') : null;
            var bulkForm = document.getElementById('teamBulkForm');
            var bulkIds = document.getElementById('teamBulkIds');
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

            window.teamBulk = {
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

                    if (bulkAction && bulkAction.value === 'delete') {
                        event.preventDefault();

                        confirmDelete(null, {
                            title: 'Delete selected teams?',
                            message: 'Existing conversations will become unassigned.',
                            callback: function () {
                                bulkForm.submit();
                            }
                        });
                    }
                });
            }

            if (window.initSS && bulkForm) {
                window.initSS(bulkForm);
            }

            syncBulkUi();
        });
        </script>
    @endif
@endsection
