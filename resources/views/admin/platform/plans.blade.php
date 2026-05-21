@extends('layouts.admin')

@section('title', __('ui.platform_plans_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.platform_plans_page.breadcrumb_root') }}</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ __('ui.platform_plans_page.breadcrumb') }}</span>
@endsection

@section('content')
<div>
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.platform_plans_page.page_title') }}</div>
            <div class="page-subtitle">{{ __('ui.platform_plans_page.subtitle') }}</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('super_admin.platform.plans.create') }}" class="btn btn-primary">
                <i class="ri-add-line"></i> {{ __('ui.platform_plans_page.add_plan') }}
            </a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-price-tag-3-line"></i></div>
            <div class="stat-card-value">{{ number_format($stats['total']) }}</div>
            <div class="stat-card-label">{{ __('ui.platform_plans_page.total_plans') }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-checkbox-circle-line"></i></div>
            <div class="stat-card-value">{{ number_format($stats['active']) }}</div>
            <div class="stat-card-label">{{ __('ui.platform_plans_page.active_plans') }}</div>
        </div>
        <div class="stat-card red">
            <div class="stat-card-icon"><i class="ri-close-circle-line"></i></div>
            <div class="stat-card-value">{{ number_format($stats['inactive']) }}</div>
            <div class="stat-card-label">{{ __('ui.platform_plans_page.disabled_plans') }}</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-card-icon"><i class="ri-building-2-line"></i></div>
            <div class="stat-card-value">{{ number_format($stats['assigned']) }}</div>
            <div class="stat-card-label">{{ __('ui.platform_plans_page.plans_in_use') }}</div>
        </div>
    </div>

    <form method="GET">
        <div class="table-toolbar" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-lg);margin-bottom:1rem">
            <div class="filter-input-wrap">
                <i class="ri-search-line"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('ui.platform_plans_page.search_placeholder') }}" class="filter-input">
            </div>
            <select name="is_active" class="toolbar-select" onchange="this.form.submit()">
                <option value="">{{ __('ui.platform_plans_page.active_disabled') }}</option>
                <option value="1" @selected(request('is_active') === '1')>{{ __('ui.platform_plans_page.active_only') }}</option>
                <option value="0" @selected(request('is_active') === '0')>{{ __('ui.platform_plans_page.disabled_only') }}</option>
            </select>
            <button type="submit" class="btn btn-outline btn-sm">{{ __('ui.platform_plans_page.filter') }}</button>
            @if(request()->hasAny(['search','is_active']))
                <a href="{{ route('super_admin.platform.plans') }}" class="btn btn-ghost btn-sm">{{ __('ui.platform_plans_page.clear') }}</a>
            @endif
        </div>
    </form>

    <div class="card" style="padding:0;">
        @if($plans->isEmpty())
            <div class="empty-state">
                <div class="empty-state-icon"><i class="ri-price-tag-3-line"></i></div>
                <h4>{{ __('ui.platform_plans_page.no_plans_found') }}</h4>
                <p>{{ __('ui.platform_plans_page.try_adjusting') }}</p>
            </div>
        @else
            <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:2.5rem">
                                <input type="checkbox" class="header-cb" style="cursor:pointer;">
                            </th>
                            <th>{{ __('ui.platform_plans_page.plan') }}</th>
                            <th>{{ __('ui.platform_plans_page.pricing') }}</th>
                            <th>{{ __('ui.platform_plans_page.limits') }}</th>
                            <th>{{ __('ui.platform_plans_page.tenants') }}</th>
                            <th>{{ __('ui.platform_plans_page.status') }}</th>
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
                                            {{ $plan->ai_included ? __('ui.platform_plans_page.ai_included') : __('ui.platform_plans_page.no_ai_included') }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex;flex-direction:column;gap:.125rem;">
                                        <span>{{ __('ui.platform_plans_page.month_price', ['price' => number_format((float) $plan->price_monthly, 2)]) }}</span>
                                        <span style="font-size:.75rem;color:var(--text-muted);">{{ __('ui.platform_plans_page.year_price', ['price' => number_format((float) $plan->price_annual, 2)]) }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex;flex-wrap:wrap;gap:.25rem;">
                                        <span class="badge badge-gray">{{ __('ui.platform_plans_page.users_limit', ['count' => number_format($plan->max_users)]) }}</span>
                                        <span class="badge badge-gray">{{ __('ui.platform_plans_page.instances_limit', ['count' => number_format($plan->max_instances)]) }}</span>
                                        <span class="badge badge-gray">{{ __('ui.platform_plans_page.conversations_limit', ['count' => number_format($plan->max_conversations_per_month)]) }}</span>
                                    </div>
                                </td>
                                <td>{{ number_format($plan->tenants_count) }}</td>
                                <td>
                                    @if($plan->is_active)
                                        <span class="badge badge-green"><i class="ri-checkbox-circle-line"></i> {{ __('ui.platform_plans_page.active') }}</span>
                                    @else
                                        <span class="badge badge-red"><i class="ri-close-circle-line"></i> {{ __('ui.platform_plans_page.disabled') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div style="display:flex;gap:.25rem;justify-content:flex-end;">
                                        <a href="{{ route('super_admin.platform.plans.show', $plan) }}" class="action-btn" title="{{ __('ui.platform_plans_page.view') }}">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                        <a href="{{ route('super_admin.platform.plans.edit', $plan) }}" class="action-btn" title="{{ __('ui.platform_plans_page.edit') }}">
                                            <i class="ri-pencil-line"></i>
                                        </a>
                                        <form id="toggle-plan-{{ $plan->id }}" method="POST" action="{{ route('super_admin.platform.plans.status', $plan) }}" style="display:none;">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="is_active" value="{{ $plan->is_active ? 0 : 1 }}">
                                        </form>
                                        <button type="button" class="action-btn {{ $plan->is_active ? 'danger' : '' }}"
                                                title="{{ $plan->is_active ? __('ui.platform_plans_page.disable') : __('ui.platform_plans_page.enable') }}"
                                                onclick="confirmSend({ title: '{{ $plan->is_active ? __('ui.platform_plans_page.disable_prompt') : __('ui.platform_plans_page.enable_prompt') }}', message: '{{ $plan->is_active ? __('ui.platform_plans_page.disable_message') : __('ui.platform_plans_page.enable_message') }}', callback: function(){ document.getElementById('toggle-plan-{{ $plan->id }}').submit(); } })">
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
            <span class="bulk-count">{{ __('ui.bulk_selected_zero') }}</span>
            <span class="bulk-sep">|</span>
            <div class="bulk-actions">
                <form method="POST" action="{{ route('super_admin.platform.plans.bulk') }}" id="planBulkForm" style="display:flex;gap:.5rem;align-items:center;">
                    @csrf
                    <input type="hidden" name="ids" id="planBulkIds">
                    <select name="action" class="form-control" style="min-width:170px;">
                        <option value="enable">{{ __('ui.platform_plans_page.bulk_enable') }}</option>
                        <option value="disable">{{ __('ui.platform_plans_page.bulk_disable') }}</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('ui.platform_plans_page.apply') }}</button>
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
