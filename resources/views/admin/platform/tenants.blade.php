@extends('layouts.admin')

@section('title', __('ui.platform_tenants_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.platform_tenants_page.breadcrumb_root') }}</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ __('ui.platform_tenants_page.breadcrumb') }}</span>
@endsection

@section('content')
<div>
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.platform_tenants_page.page_title') }}</div>
            <div class="page-subtitle">{{ __('ui.platform_tenants_page.subtitle') }}</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('super_admin.platform.tenants.create') }}" class="btn btn-primary">
                <i class="ri-add-line"></i> {{ __('ui.platform_tenants_page.add_tenant') }}
            </a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-building-2-line"></i></div>
            <div class="stat-card-value">{{ number_format($stats['total']) }}</div>
            <div class="stat-card-label">{{ __('ui.platform_tenants_page.total_tenants') }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-checkbox-circle-line"></i></div>
            <div class="stat-card-value">{{ number_format($stats['active']) }}</div>
            <div class="stat-card-label">{{ __('ui.platform_tenants_page.active_subscriptions') }}</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-card-icon"><i class="ri-time-line"></i></div>
            <div class="stat-card-value">{{ number_format($stats['trial']) }}</div>
            <div class="stat-card-label">{{ __('ui.platform_tenants_page.trialing') }}</div>
        </div>
        <div class="stat-card red">
            <div class="stat-card-icon"><i class="ri-pause-circle-line"></i></div>
            <div class="stat-card-value">{{ number_format($stats['inactive']) }}</div>
            <div class="stat-card-label">{{ __('ui.platform_tenants_page.inactive_tenants') }}</div>
        </div>
    </div>

    <form method="GET">
        <div class="table-toolbar" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-lg);margin-bottom:1rem">
            <div class="filter-input-wrap">
                <i class="ri-search-line"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('ui.platform_tenants_page.search_placeholder') }}" class="filter-input">
            </div>
            <select name="plan_id" class="toolbar-select" onchange="this.form.submit()">
                <option value="">{{ __('ui.platform_tenants_page.all_plans') }}</option>
                @foreach($plans as $plan)
                    <option value="{{ $plan->id }}" @selected((string) request('plan_id') === (string) $plan->id)>{{ $plan->name }}</option>
                @endforeach
            </select>
            <select name="status" class="toolbar-select" onchange="this.form.submit()">
                <option value="">{{ __('ui.platform_tenants_page.all_status') }}</option>
                @foreach(['trial','active','suspended','cancelled'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ __('ui.platform_tenants_page.statuses.' . $status) }}</option>
                @endforeach
            </select>
            <select name="is_active" class="toolbar-select" onchange="this.form.submit()">
                <option value="">{{ __('ui.platform_tenants_page.active_inactive') }}</option>
                <option value="1" @selected(request('is_active') === '1')>{{ __('ui.platform_tenants_page.active_only') }}</option>
                <option value="0" @selected(request('is_active') === '0')>{{ __('ui.platform_tenants_page.inactive_only') }}</option>
            </select>
            <button type="submit" class="btn btn-outline btn-sm">{{ __('ui.platform_tenants_page.filter') }}</button>
            @if(request()->hasAny(['search','plan_id','status','is_active']))
                <a href="{{ route('super_admin.platform.tenants') }}" class="btn btn-ghost btn-sm">{{ __('ui.platform_tenants_page.clear') }}</a>
            @endif
        </div>
    </form>

    <div class="card" style="padding:0">
        @if($tenants->isEmpty())
            <div class="empty-state">
                <div class="empty-state-icon"><i class="ri-building-2-line"></i></div>
                <h4>{{ __('ui.platform_tenants_page.no_tenants_found') }}</h4>
                <p>{{ __('ui.platform_tenants_page.try_adjusting') }}</p>
                <a href="{{ route('super_admin.platform.tenants.create') }}" class="btn btn-primary">{{ __('ui.platform_tenants_page.add_tenant') }}</a>
            </div>
        @else
            <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:2.5rem">
                                <input type="checkbox" class="header-cb" style="cursor:pointer;">
                            </th>
                            <th>{{ __('ui.platform_tenants_page.tenant') }}</th>
                            <th>{{ __('ui.platform_tenants_page.plan') }}</th>
                            <th>{{ __('ui.platform_tenants_page.status') }}</th>
                            <th>{{ __('ui.platform_tenants_page.users') }}</th>
                            <th>{{ __('ui.platform_tenants_page.teams') }}</th>
                            <th>{{ __('ui.platform_tenants_page.instances') }}</th>
                            <th>{{ __('ui.platform_tenants_page.created') }}</th>
                            <th style="width:120px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tenants as $tenant)
                            <tr>
                                <td>
                                    <input type="checkbox" class="row-cb" value="{{ $tenant->id }}" style="cursor:pointer;">
                                </td>
                                <td>
                                    <div style="display:flex;flex-direction:column;gap:.125rem">
                                        <span style="font-weight:600;color:var(--text-primary)">{{ $tenant->name }}</span>
                                        <span style="font-size:.75rem;color:var(--text-muted)">{{ $tenant->slug }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-gray">{{ $tenant->plan?->name ?? __('ui.platform_tenants_page.no_plan') }}</span>
                                </td>
                                <td>
                                    @php
                                        $statusBadge = match($tenant->subscription_status) {
                                            'active' => 'badge-green',
                                            'trial' => 'badge-orange',
                                            'suspended' => 'badge-red',
                                            default => 'badge-gray',
                                        };
                                    @endphp
                                    <span class="badge {{ $statusBadge }}">
                                        @if($tenant->subscription_status === 'active')
                                            <i class="ri-checkbox-circle-line"></i>
                                        @elseif($tenant->subscription_status === 'trial')
                                            <i class="ri-time-line"></i>
                                        @elseif($tenant->subscription_status === 'suspended')
                                            <i class="ri-close-circle-line"></i>
                                        @else
                                            <i class="ri-stop-circle-line"></i>
                                        @endif
                                        {{ __('ui.platform_tenants_page.statuses.' . $tenant->subscription_status) }}
                                    </span>
                                    @if(!$tenant->is_active)
                                        <span class="badge badge-gray">{{ __('ui.platform_tenants_page.inactive') }}</span>
                                    @endif
                                </td>
                                <td>{{ number_format($tenant->users_count) }}</td>
                                <td>{{ number_format($tenant->teams_count) }}</td>
                                <td>{{ number_format($tenant->instances_count) }}</td>
                                <td>
                                    <span style="font-size:.8125rem;color:var(--text-muted)">{{ $tenant->created_at?->format('M j, Y') }}</span>
                                </td>
                                <td>
                                    <div style="display:flex;gap:.25rem;justify-content:flex-end">
                                        <a href="{{ route('super_admin.platform.tenants.show', $tenant) }}" class="action-btn" title="{{ __('ui.platform_tenants_page.view') }}">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                        <a href="{{ route('super_admin.platform.tenants.edit', $tenant) }}" class="action-btn" title="{{ __('ui.platform_tenants_page.edit') }}">
                                            <i class="ri-pencil-line"></i>
                                        </a>
                                        <button type="button" class="action-btn danger" title="{{ __('ui.platform_tenants_page.delete') }}"
                                                onclick="confirmDelete('{{ route('super_admin.platform.tenants.destroy', $tenant) }}', { title: @js(__('ui.platform_tenants_page.delete_prompt', ['name' => $tenant->name])), message: @js(__('ui.platform_tenants_page.delete_message')) })">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($tenants->hasPages())
                <div style="padding:1rem 1.25rem;border-top:1px solid var(--card-border)">
                    {{ $tenants->links('admin.partials.pagination') }}
                </div>
            @endif
        @endif
    </div>

    @if($tenants->isNotEmpty())
        <div class="bulk-bar" id="tenantBulkBar">
            <span class="bulk-count">{{ __('ui.bulk_selected_zero') }}</span>
            <span class="bulk-sep">|</span>
            <div class="bulk-actions">
                <form method="POST" action="{{ route('super_admin.platform.tenants.bulk') }}" id="tenantBulkForm" style="display:flex;gap:.5rem;align-items:center;">
                    @csrf
                    <input type="hidden" name="ids" id="tenantBulkIds">
                    <select name="action" class="form-control" style="min-width:170px;">
                        <option value="enable">{{ __('ui.platform_tenants_page.bulk_enable') }}</option>
                        <option value="disable">{{ __('ui.platform_tenants_page.bulk_disable') }}</option>
                        <option value="delete">{{ __('ui.platform_tenants_page.bulk_delete') }}</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('ui.platform_tenants_page.apply') }}</button>
                </form>
            </div>
            <button type="button" class="bulk-close" onclick="tenantBulk.clear()"><i class="ri-close-line"></i></button>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var headerCheckbox = document.querySelector('.header-cb');
            var rowCheckboxes = Array.from(document.querySelectorAll('.row-cb'));
            var bulkBar = document.getElementById('tenantBulkBar');
            var bulkCount = bulkBar ? bulkBar.querySelector('.bulk-count') : null;
            var bulkForm = document.getElementById('tenantBulkForm');
            var bulkIds = document.getElementById('tenantBulkIds');
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

            window.tenantBulk = {
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

            if (bulkAction) {
                bulkAction.addEventListener('change', function () {
                    if (bulkAction.value === 'delete' && !window.confirm(@js(__('ui.platform_tenants_page.delete_selected_confirm')))) {
                        bulkAction.value = 'enable';
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
</div>
@endsection
