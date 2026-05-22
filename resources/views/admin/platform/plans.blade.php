@extends('layouts.admin')

@section('title', __('ui.platform_plans_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.platform_plans_page.breadcrumb_root') }}</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ __('ui.platform_plans_page.breadcrumb') }}</span>
@endsection

@section('content')
<div x-data="plansPage()" x-init="init()" @pageshow.window="onPageShow($event)">

    {{-- Header --}}
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

    {{-- Stat cards --}}
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-price-tag-3-line"></i></div>
            <div class="stat-card-value" x-text="stats.total ?? '—'"></div>
            <div class="stat-card-label">{{ __('ui.platform_plans_page.total_plans') }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-checkbox-circle-line"></i></div>
            <div class="stat-card-value" x-text="stats.active ?? '—'"></div>
            <div class="stat-card-label">{{ __('ui.platform_plans_page.active_plans') }}</div>
        </div>
        <div class="stat-card red">
            <div class="stat-card-icon"><i class="ri-close-circle-line"></i></div>
            <div class="stat-card-value" x-text="stats.inactive ?? '—'"></div>
            <div class="stat-card-label">{{ __('ui.platform_plans_page.disabled_plans') }}</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-card-icon"><i class="ri-building-2-line"></i></div>
            <div class="stat-card-value" x-text="stats.assigned ?? '—'"></div>
            <div class="stat-card-label">{{ __('ui.platform_plans_page.plans_in_use') }}</div>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="table-toolbar" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-lg);margin-bottom:1rem">
        <div class="filter-input-wrap">
            <i class="ri-search-line"></i>
            <input type="text" x-model="filters.search"
                   @input.debounce.400ms="reload()"
                   placeholder="{{ __('ui.platform_plans_page.search_placeholder') }}"
                   class="filter-input">
        </div>
        <select x-model="filters.is_active" @change="reload()" class="toolbar-select">
            <option value="">{{ __('ui.platform_plans_page.active_disabled') }}</option>
            <option value="1">{{ __('ui.platform_plans_page.active_only') }}</option>
            <option value="0">{{ __('ui.platform_plans_page.disabled_only') }}</option>
        </select>
        <button type="button" @click="clearFilters()" x-show="hasFilters()" class="btn btn-ghost btn-sm">
            {{ __('ui.platform_plans_page.clear') }}
        </button>
    </div>

    {{-- Table card --}}
    <div class="card" style="padding:0;">
        {{-- Loading --}}
        <div x-show="loading" style="padding:3rem;text-align:center;color:var(--text-muted);">
            <i class="ri-loader-4-line" style="font-size:1.5rem;animation:spin 1s linear infinite;"></i>
        </div>

        {{-- Empty state --}}
        <div x-show="!loading && rows.length === 0" class="empty-state">
            <div class="empty-state-icon"><i class="ri-price-tag-3-line"></i></div>
            <h4>{{ __('ui.platform_plans_page.no_plans_found') }}</h4>
            <p x-text="hasFilters() ? @js(__('ui.platform_plans_page.try_adjusting')) : @js(__('ui.platform_plans_page.no_plans_found'))"></p>
        </div>

        {{-- Table --}}
        <div x-show="!loading && rows.length > 0" class="table-wrap" style="border:none;border-radius:0;box-shadow:none;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:2.5rem">
                            <input type="checkbox" class="header-cb"
                                   :checked="isAllSelected()"
                                   :indeterminate.prop="isIndeterminate()"
                                   @change="toggleAll($event.target.checked)">
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
                    <template x-for="plan in rows" :key="plan.id">
                        <tr>
                            <td>
                                <input type="checkbox" class="row-cb"
                                       :value="plan.id"
                                       :checked="selected.includes(plan.id)"
                                       @change="toggleRow(plan.id)">
                            </td>
                            <td>
                                <div style="display:flex;flex-direction:column;gap:.125rem;">
                                    <span style="font-weight:600;color:var(--text-primary);" x-text="plan.name"></span>
                                    <span style="font-size:.75rem;color:var(--text-muted);"
                                          x-text="plan.ai_included ? @js(__('ui.platform_plans_page.ai_included')) : @js(__('ui.platform_plans_page.no_ai_included'))"></span>
                                </div>
                            </td>
                            <td>
                                <div style="display:flex;flex-direction:column;gap:.125rem;">
                                    <span x-text="monthPrice(plan)"></span>
                                    <span style="font-size:.75rem;color:var(--text-muted);" x-text="yearPrice(plan)"></span>
                                </div>
                            </td>
                            <td>
                                <div style="display:flex;flex-wrap:wrap;gap:.25rem;">
                                    <span class="badge badge-gray" x-text="usersLimit(plan)"></span>
                                    <span class="badge badge-gray" x-text="instancesLimit(plan)"></span>
                                    <span class="badge badge-gray" x-text="convLimit(plan)"></span>
                                </div>
                            </td>
                            <td x-text="plan.tenants_count ?? 0"></td>
                            <td>
                                <span :class="plan.is_active ? 'badge badge-green' : 'badge badge-red'">
                                    <i :class="plan.is_active ? 'ri-checkbox-circle-line' : 'ri-close-circle-line'"></i>
                                    <span x-text="plan.is_active ? @js(__('ui.platform_plans_page.active')) : @js(__('ui.platform_plans_page.disabled'))"></span>
                                </span>
                            </td>
                            <td>
                                <div style="display:flex;gap:.25rem;justify-content:flex-end;">
                                    <a :href="showUrl(plan.id)" class="action-btn" title="{{ __('ui.platform_plans_page.view') }}">
                                        <i class="ri-eye-line"></i>
                                    </a>
                                    <a :href="editUrl(plan.id)" class="action-btn" title="{{ __('ui.platform_plans_page.edit') }}">
                                        <i class="ri-pencil-line"></i>
                                    </a>
                                    <button type="button"
                                            :class="plan.is_active ? 'action-btn danger' : 'action-btn'"
                                            :title="plan.is_active ? @js(__('ui.platform_plans_page.disable')) : @js(__('ui.platform_plans_page.enable'))"
                                            @click="openToggleConfirm(plan)">
                                        <i :class="plan.is_active ? 'ri-pause-circle-line' : 'ri-play-circle-line'"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div x-show="!loading && pagination.last_page > 1"
             style="padding:1rem 1.25rem;border-top:1px solid var(--card-border);display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
            <button class="btn btn-outline btn-sm" :disabled="pagination.current_page <= 1" @click="goToPage(pagination.current_page - 1)">
                <i class="ri-arrow-left-s-line"></i>
            </button>
            <template x-for="p in pageRange()" :key="p">
                <button :class="p === pagination.current_page ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm'"
                        @click="goToPage(p)" x-text="p"></button>
            </template>
            <button class="btn btn-outline btn-sm" :disabled="pagination.current_page >= pagination.last_page" @click="goToPage(pagination.current_page + 1)">
                <i class="ri-arrow-right-s-line"></i>
            </button>
            <span style="font-size:.8rem;color:var(--text-muted);margin-left:.5rem;"
                  x-text="`${pagination.from ?? 0}–${pagination.to ?? 0} / ${pagination.total ?? 0}`"></span>
        </div>
    </div>

    {{-- Bulk bar --}}
    <div class="bulk-bar" :class="selected.length > 0 ? 'visible' : ''">
        <span class="bulk-count" x-text="`${selected.length} @js(__('ui.selected_items'))`"></span>
        <span class="bulk-sep">|</span>
        <div class="bulk-actions" style="display:flex;gap:.5rem;align-items:center;">
            <select x-model="bulkAction" class="form-control" style="min-width:170px;">
                <option value="enable">{{ __('ui.platform_plans_page.bulk_enable') }}</option>
                <option value="disable">{{ __('ui.platform_plans_page.bulk_disable') }}</option>
            </select>
            <button type="button" class="btn btn-primary btn-sm" @click="submitBulk()" :disabled="bulkSaving">
                <span x-show="!bulkSaving">{{ __('ui.platform_plans_page.apply') }}</span>
                <span x-show="bulkSaving">{{ __('ui.deleting') }}</span>
            </button>
        </div>
        <button type="button" class="bulk-close" @click="selected = []"><i class="ri-close-line"></i></button>
    </div>

</div>

<script>
function plansPage() {
    const dataUrl       = @json(route('super_admin.platform.plans'));
    const bulkUrl       = @json(route('super_admin.platform.plans.bulk'));
    const statusUrlTpl  = @json(route('super_admin.platform.plans.status', '__ID__'));
    const showBase      = @json(route('super_admin.platform.plans.show', '__ID__'));
    const editBase      = @json(route('super_admin.platform.plans.edit', '__ID__'));
    const csrfToken  = @json(csrf_token());

    return {
        loading: true,
        rows: [],
        stats: {},
        pagination: {},
        selected: [],
        bulkAction: 'enable',
        bulkSaving: false,
        filters: { search: '', is_active: '' },

        init() { this.reload(); },

        onPageShow(e) { if (e.persisted) this.reload(); },

        showUrl(id)   { return showBase.replace('__ID__', id); },
        editUrl(id)   { return editBase.replace('__ID__', id); },
        statusUrl(id) { return statusUrlTpl.replace('__ID__', id); },

        hasFilters() { return this.filters.search !== '' || this.filters.is_active !== ''; },

        clearFilters() { this.filters.search = ''; this.filters.is_active = ''; this.reload(); },

        async reload(page) {
            this.loading = true;
            this.selected = [];
            const params = new URLSearchParams();
            if (this.filters.search)    params.set('search', this.filters.search);
            if (this.filters.is_active !== '') params.set('is_active', this.filters.is_active);
            if (page)                   params.set('page', page);
            params.set('per_page', 20);
            try {
                const res = await fetch(dataUrl + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const json = await res.json();
                this.rows       = json.data ?? [];
                this.stats      = json.stats ?? {};
                this.pagination = {
                    current_page: json.current_page,
                    last_page:    json.last_page,
                    from:         json.from,
                    to:           json.to,
                    total:        json.total,
                };
            } catch(e) { console.error(e); }
            this.loading = false;
        },

        goToPage(p) { if (p >= 1 && p <= this.pagination.last_page) this.reload(p); },

        pageRange() {
            const cur  = this.pagination.current_page || 1;
            const last = this.pagination.last_page || 1;
            const pages = [];
            for (let i = Math.max(1, cur - 2); i <= Math.min(last, cur + 2); i++) pages.push(i);
            return pages;
        },

        isAllSelected()    { return this.rows.length > 0 && this.rows.every(r => this.selected.includes(r.id)); },
        isIndeterminate()  { return this.selected.length > 0 && !this.isAllSelected(); },
        toggleAll(checked) { this.selected = checked ? this.rows.map(r => r.id) : []; },
        toggleRow(id)      { this.selected.includes(id) ? this.selected.splice(this.selected.indexOf(id), 1) : this.selected.push(id); },

        monthPrice(plan) {
            return @json(__('ui.platform_plans_page.month_price', ['price' => ':price']))
                .replace(':price', parseFloat(plan.price_monthly || 0).toFixed(2));
        },
        yearPrice(plan) {
            return @json(__('ui.platform_plans_page.year_price', ['price' => ':price']))
                .replace(':price', parseFloat(plan.price_annual || 0).toFixed(2));
        },
        usersLimit(plan) {
            return @json(__('ui.platform_plans_page.users_limit', ['count' => ':count']))
                .replace(':count', plan.max_users ?? 0);
        },
        instancesLimit(plan) {
            return @json(__('ui.platform_plans_page.instances_limit', ['count' => ':count']))
                .replace(':count', plan.max_instances ?? 0);
        },
        convLimit(plan) {
            return @json(__('ui.platform_plans_page.conversations_limit', ['count' => ':count']))
                .replace(':count', plan.max_conversations_per_month ?? 0);
        },

        openToggleConfirm(plan) {
            const enabling = !plan.is_active;
            const title   = enabling
                ? @json(__('ui.platform_plans_page.enable_prompt'))
                : @json(__('ui.platform_plans_page.disable_prompt'));
            const message = enabling
                ? @json(__('ui.platform_plans_page.enable_message'))
                : @json(__('ui.platform_plans_page.disable_message'));

            window.confirmSend?.({
                title,
                message,
                callback: () => this.toggleStatus(plan.id, enabling),
            });
        },

        async toggleStatus(id, enabling) {
            try {
                const res = await fetch(this.statusUrl(id), {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ is_active: enabling ? 1 : 0 }),
                });
                if (!res.ok) {
                    const err = await res.json().catch(() => ({}));
                    alert(err.message || 'Erreur');
                    return;
                }
                await this.reload();
            } catch(e) { console.error(e); }
        },

        async submitBulk() {
            if (!this.selected.length) return;
            this.bulkSaving = true;
            try {
                const res = await fetch(bulkUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ action: this.bulkAction, ids: this.selected.join(',') }),
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) { alert(json.message || 'Erreur'); }
                await this.reload();
            } catch(e) { console.error(e); }
            this.bulkSaving = false;
        },
    };
}
</script>
@endsection
