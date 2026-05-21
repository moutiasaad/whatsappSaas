@extends('layouts.admin')

@section('title', 'Subscription Plans')

@section('breadcrumb')
    <span>Platform</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>Subscription Plans</span>
@endsection

@section('content')
<div>
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">Subscription Plans</div>
            <div class="page-subtitle">Global plan catalog and tenant assignment rules</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('super_admin.platform.plans.create') }}" class="btn btn-primary">
                <i class="ri-add-line"></i> Add Plan
            </a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-price-tag-3-line"></i></div>
            <div class="stat-card-value">{{ number_format($stats['total']) }}</div>
            <div class="stat-card-label">Total Plans</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-checkbox-circle-line"></i></div>
            <div class="stat-card-value">{{ number_format($stats['active']) }}</div>
            <div class="stat-card-label">Active Plans</div>
        </div>
        <div class="stat-card red">
            <div class="stat-card-icon"><i class="ri-close-circle-line"></i></div>
            <div class="stat-card-value">{{ number_format($stats['inactive']) }}</div>
            <div class="stat-card-label">Disabled Plans</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-card-icon"><i class="ri-building-2-line"></i></div>
            <div class="stat-card-value">{{ number_format($stats['assigned']) }}</div>
            <div class="stat-card-label">Plans in Use</div>
        </div>
    </div>

    <form method="GET">
        <div class="table-toolbar" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-lg);margin-bottom:1rem">
            <div class="filter-input-wrap">
                <i class="ri-search-line"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search plans..." class="filter-input">
            </div>
            <select name="is_active" class="toolbar-select" onchange="this.form.submit()">
                <option value="">Active + Disabled</option>
                <option value="1" @selected(request('is_active') === '1')>Active only</option>
                <option value="0" @selected(request('is_active') === '0')>Disabled only</option>
            </select>
            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
            @if(request()->hasAny(['search','is_active']))
                <a href="{{ route('super_admin.platform.plans') }}" class="btn btn-ghost btn-sm">Clear</a>
            @endif
        </div>
    </form>

    <div class="card" style="padding:0;">
        @if($plans->isEmpty())
            <div class="empty-state">
                <div class="empty-state-icon"><i class="ri-price-tag-3-line"></i></div>
                <h4>No plans found</h4>
                <p>Try adjusting your filters.</p>
            </div>
        @else
            <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:2.5rem">
                                <input type="checkbox" class="header-cb" style="cursor:pointer;">
                            </th>
                            <th>Plan</th>
                            <th>Pricing</th>
                            <th>Limits</th>
                            <th>Tenants</th>
                            <th>Status</th>
                            <th style="width:120px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($plans as $plan)
                            <tr>
                                <td>
                                    <input type="checkbox" class="row-cb" value="{{ $plan->id }}" style="cursor:pointer;">
                                </td>
                                <td>
                                    <div style="display:flex;flex-direction:column;gap:.125rem;">
                                        <span style="font-weight:600;color:var(--text-primary);">{{ $plan->name }}</span>
                                        <span style="font-size:.75rem;color:var(--text-muted);">
                                            {{ $plan->ai_included ? 'AI included' : 'No AI included' }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex;flex-direction:column;gap:.125rem;">
                                        <span>${{ number_format((float) $plan->price_monthly, 2) }} / month</span>
                                        <span style="font-size:.75rem;color:var(--text-muted);">${{ number_format((float) $plan->price_annual, 2) }} / year</span>
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex;flex-wrap:wrap;gap:.25rem;">
                                        <span class="badge badge-gray">{{ number_format($plan->max_users) }} users</span>
                                        <span class="badge badge-gray">{{ number_format($plan->max_instances) }} instances</span>
                                        <span class="badge badge-gray">{{ number_format($plan->max_conversations_per_month) }} conv/mo</span>
                                    </div>
                                </td>
                                <td>{{ number_format($plan->tenants_count) }}</td>
                                <td>
                                    @if($plan->is_active)
                                        <span class="badge badge-green"><i class="ri-checkbox-circle-line"></i> Active</span>
                                    @else
                                        <span class="badge badge-red"><i class="ri-close-circle-line"></i> Disabled</span>
                                    @endif
                                </td>
                                <td>
                                    <div style="display:flex;gap:.25rem;justify-content:flex-end;">
                                        <a href="{{ route('super_admin.platform.plans.show', $plan) }}" class="action-btn" title="View">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                        <a href="{{ route('super_admin.platform.plans.edit', $plan) }}" class="action-btn" title="Edit">
                                            <i class="ri-pencil-line"></i>
                                        </a>
                                        <form id="toggle-plan-{{ $plan->id }}" method="POST" action="{{ route('super_admin.platform.plans.status', $plan) }}" style="display:none;">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="is_active" value="{{ $plan->is_active ? 0 : 1 }}">
                                        </form>
                                        <button type="button"
                                                class="action-btn {{ $plan->is_active ? 'danger' : '' }}"
                                                title="{{ $plan->is_active ? 'Disable' : 'Enable' }}"
                                                onclick="confirmSend({ title: '{{ $plan->is_active ? 'Disable plan?' : 'Enable plan?' }}', message: '{{ $plan->is_active ? 'New tenants will no longer be assigned to this plan.' : 'This plan will be available for tenant assignment again.' }}', callback: function(){ document.getElementById('toggle-plan-{{ $plan->id }}').submit(); } })">
                                            <i class="{{ $plan->is_active ? 'ri-pause-circle-line' : 'ri-play-circle-line' }}"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($plans->hasPages())
                <div style="padding:1rem 1.25rem;border-top:1px solid var(--card-border);">
                    {{ $plans->links('admin.partials.pagination') }}
                </div>
            @endif
        @endif
    </div>

    @if($plans->isNotEmpty())
        <div class="bulk-bar" id="planBulkBar">
            <span class="bulk-count">0 selected</span>
            <span class="bulk-sep">|</span>
            <div class="bulk-actions">
                <form method="POST" action="{{ route('super_admin.platform.plans.bulk') }}" id="planBulkForm" style="display:flex;gap:.5rem;align-items:center;">
                    @csrf
                    <input type="hidden" name="ids" id="planBulkIds">
                    <select name="action" class="form-control" style="min-width:170px;">
                        <option value="enable">Enable</option>
                        <option value="disable">Disable</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                </form>
            </div>
            <button type="button" class="bulk-close" onclick="planBulk.clear()"><i class="ri-close-line"></i></button>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var headerCheckbox = document.querySelector('.header-cb');
            var rowCheckboxes = Array.from(document.querySelectorAll('.row-cb'));
            var bulkBar = document.getElementById('planBulkBar');
            var bulkCount = bulkBar ? bulkBar.querySelector('.bulk-count') : null;
            var bulkForm = document.getElementById('planBulkForm');
            var bulkIds = document.getElementById('planBulkIds');

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

            window.planBulk = {
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
                bulkForm.addEventListener('submit', function () {
                    syncBulkUi();
                });
            }

            if (window.initSS && bulkForm) {
                window.initSS(bulkForm);
            }

            syncBulkUi();
        });
        </script>
    @endif
</div>
@endsection
