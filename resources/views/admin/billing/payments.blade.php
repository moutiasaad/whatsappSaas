@extends('layouts.admin')

@section('title', __('ui.payments_page.title'))

@section('breadcrumb')
    <a href="{{ route('super_admin.billing.index') }}" style="color:var(--text-secondary);text-decoration:none">{{ __('ui.sidebar.billing') }}</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ __('ui.payments_page.title') }}</span>
@endsection

@section('content')
@php
$i18n = [
    'status_completed' => __('ui.payments_page.status_completed'),
    'status_pending'   => __('ui.payments_page.status_pending'),
    'status_failed'    => __('ui.payments_page.status_failed'),
    'no_payments'      => __('ui.payments_page.no_payments'),
    'no_payments_desc' => __('ui.payments_page.no_payments_desc'),
    'loading'          => __('ui.conversations_page.loading'),
];
@endphp

<div x-data="paymentsPage()" x-init="init()" x-cloak>

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.payments_page.title') }}</div>
            <div class="page-subtitle">{{ __('ui.payments_page.subtitle') }}</div>
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="stats-grid" style="margin-bottom:1.5rem;grid-template-columns:repeat(4,1fr)">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-money-dollar-circle-line"></i></div>
            <div class="stat-card-value" x-text="'USD ' + fmtAmount(stats.total_revenue)"></div>
            <div class="stat-card-label">{{ __('ui.payments_page.total_revenue') }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-checkbox-circle-line"></i></div>
            <div class="stat-card-value" x-text="stats.total ?? '-'"></div>
            <div class="stat-card-label">{{ __('ui.payments_page.total_transactions') }}</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-card-icon"><i class="ri-time-line"></i></div>
            <div class="stat-card-value" x-text="stats.pending ?? '-'"></div>
            <div class="stat-card-label">{{ __('ui.payments_page.pending') }}</div>
        </div>
        <div class="stat-card" style="--stat-accent:#ef4444">
            <div class="stat-card-icon" style="color:#ef4444"><i class="ri-close-circle-line"></i></div>
            <div class="stat-card-value" x-text="stats.failed ?? '-'"></div>
            <div class="stat-card-label">{{ __('ui.payments_page.failed') }}</div>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="table-toolbar" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-lg);margin-bottom:1rem">
        <div class="filter-input-wrap">
            <i class="ri-search-line"></i>
            <input type="text" x-model="search" @input.debounce.400ms="reload()"
                   placeholder="{{ __('ui.payments_page.search_placeholder') }}"
                   class="filter-input">
        </div>
        <select x-model="filters.status" @change="reload()" class="toolbar-select">
            <option value="">{{ __('ui.payments_page.all_statuses') }}</option>
            <option value="completed">{{ __('ui.payments_page.status_completed') }}</option>
            <option value="pending">{{ __('ui.payments_page.status_pending') }}</option>
            <option value="failed">{{ __('ui.payments_page.status_failed') }}</option>
        </select>
        <button type="button" x-show="search || filters.status" @click="clearFilters()" class="btn btn-ghost btn-sm">
            <i class="ri-close-line"></i> {{ __('ui.clear') }}
        </button>
    </div>

    {{-- Loading --}}
    <div x-show="loading" class="spinner-wrap" style="min-height:200px">
        <div>
            <div class="spinner" style="margin:0 auto 1rem"></div>
            <div style="color:var(--text-muted);font-size:.875rem;text-align:center" x-text="i18n.loading"></div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card" style="padding:0" x-show="!loading">

        <div x-show="rows.length === 0" class="empty-state" style="padding:3rem 1rem;">
            <div class="empty-state-icon"><i class="ri-receipt-line"></i></div>
            <h4 x-text="i18n.no_payments"></h4>
            <p x-text="i18n.no_payments_desc"></p>
        </div>

        <div x-show="rows.length > 0">
            <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('ui.payments_page.col_tenant') }}</th>
                            <th>{{ __('ui.payments_page.col_plan') }}</th>
                            <th style="text-align:right;">{{ __('ui.payments_page.col_amount') }}</th>
                            <th style="text-align:center;">{{ __('ui.payments_page.col_status') }}</th>
                            <th>{{ __('ui.payments_page.col_date') }}</th>
                            <th>{{ __('ui.payments_page.col_session') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in rows" :key="row.id">
                            <tr>
                                <td style="color:var(--text-muted);font-size:.8125rem;" x-text="row.id"></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:.625rem;">
                                        <div style="width:2rem;height:2rem;border-radius:.5rem;background:linear-gradient(135deg,rgba(16,185,129,.15),rgba(5,150,105,.25));display:flex;align-items:center;justify-content:center;color:var(--brand);font-size:.75rem;font-weight:700;flex-shrink:0;"
                                             x-text="row.tenant_initial"></div>
                                        <div>
                                            <div style="font-weight:600;font-size:.875rem;" x-text="row.tenant_name ?? '—'"></div>
                                            <div style="font-size:.75rem;color:var(--text-muted);" x-text="row.tenant_slug ?? ''"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size:.875rem;font-weight:500;" x-text="row.plan_name"></span>
                                </td>
                                <td style="text-align:right;">
                                    <span style="font-weight:700;font-size:.9375rem;"
                                          :style="row.is_completed ? 'color:var(--brand)' : 'color:var(--text-muted)'"
                                          x-text="row.currency + ' ' + row.amount"></span>
                                </td>
                                <td style="text-align:center;">
                                    <span :class="'badge ' + statusBadge(row.status).cls">
                                        <i :class="statusBadge(row.status).icon"></i>
                                        <span x-text="statusBadge(row.status).label"></span>
                                    </span>
                                </td>
                                <td style="font-size:.8125rem;color:var(--text-secondary);white-space:nowrap;">
                                    <template x-if="row.paid_at_date">
                                        <div>
                                            <div x-text="row.paid_at_date"></div>
                                            <div style="color:var(--text-muted);font-size:.75rem;" x-text="row.paid_at_time"></div>
                                        </div>
                                    </template>
                                    <template x-if="!row.paid_at_date && row.created_at_date">
                                        <div style="color:var(--text-muted);" x-text="row.created_at_date"></div>
                                    </template>
                                    <template x-if="!row.paid_at_date && !row.created_at_date">
                                        <span>—</span>
                                    </template>
                                </td>
                                <td>
                                    <template x-if="row.stripe_session_id">
                                        <span style="font-family:monospace;font-size:.75rem;color:var(--text-muted);background:var(--page-bg);padding:.15rem .4rem;border-radius:.375rem;border:1px solid var(--card-border);"
                                              x-text="row.stripe_session_id"></span>
                                    </template>
                                    <template x-if="!row.stripe_session_id">
                                        <span style="color:var(--text-muted);">—</span>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div x-show="pagination.last_page > 1"
                 style="padding:.875rem 1.25rem;border-top:1px solid var(--card-border);display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap;">
                <div style="font-size:.8125rem;color:var(--text-muted);">
                    {{ __('ui.page') }} <span x-text="pagination.current_page"></span> / <span x-text="pagination.last_page"></span>
                </div>
                <div style="display:flex;gap:.375rem;flex-wrap:wrap;">
                    <button type="button" @click="goToPage(pagination.current_page - 1)"
                            :disabled="pagination.current_page <= 1" class="btn btn-outline btn-sm">
                        <i class="ri-arrow-left-s-line"></i>
                    </button>
                    <template x-for="p in pageRange()" :key="p">
                        <button type="button" @click="p !== '…' && goToPage(p)"
                                :disabled="p === '…'"
                                :class="p === pagination.current_page ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm'"
                                x-text="p"></button>
                    </template>
                    <button type="button" @click="goToPage(pagination.current_page + 1)"
                            :disabled="pagination.current_page >= pagination.last_page" class="btn btn-outline btn-sm">
                        <i class="ri-arrow-right-s-line"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function paymentsPage() {
    return {
        i18n:     @json($i18n),
        indexUrl: @json(route('super_admin.billing.payments')),

        search:     '',
        filters:    { status: '' },
        loading:    true,
        rows:       [],
        stats:      { total_revenue: 0, total: 0, completed: 0, pending: 0, failed: 0 },
        pagination: { current_page: 1, last_page: 1, total: 0 },
        _page:      1,

        init() {
            this.loadData();
            window.addEventListener('pageshow', e => { if (e.persisted) this.loadData(); });
        },

        reload() {
            this._page = 1;
            this.loadData();
        },

        clearFilters() {
            this.search        = '';
            this.filters.status = '';
            document.querySelectorAll('.table-toolbar .ss-wrap').forEach(wrap => {
                const inp = wrap.querySelector('.ss-input');
                if (inp) inp.value = '';
                wrap.querySelectorAll('.ss-item.ss-selected').forEach(el => el.classList.remove('ss-selected'));
                wrap.classList.remove('open');
            });
            this.reload();
        },

        buildParams() {
            const p = new URLSearchParams();
            p.set('page', String(this._page));
            if (this.search.trim())   p.set('search', this.search.trim());
            if (this.filters.status)  p.set('status', this.filters.status);
            return p;
        },

        async loadData() {
            this.loading = true;
            try {
                const res = await fetch(`${this.indexUrl}?${this.buildParams()}`, {
                    headers: {
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    credentials: 'same-origin',
                });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data    = await res.json();
                this.rows       = data.data || [];
                this.stats      = data.stats  || this.stats;
                this.pagination = { current_page: data.current_page, last_page: data.last_page, total: data.total };
            } catch (e) {
                console.error('Payments fetch failed:', e);
                this.rows = [];
            } finally {
                this.loading = false;
            }
        },

        goToPage(page) {
            if (page < 1 || page > this.pagination.last_page) return;
            this._page = page;
            this.loadData();
        },

        pageRange() {
            const cur = this.pagination.current_page, last = this.pagination.last_page;
            if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);
            const pages  = new Set([1, last, cur, cur - 1, cur + 1].filter(p => p >= 1 && p <= last));
            const sorted = [...pages].sort((a, b) => a - b);
            const result = [];
            sorted.forEach((p, i) => {
                if (i > 0 && p - sorted[i - 1] > 1) result.push('…');
                result.push(p);
            });
            return result;
        },

        fmtAmount(n) {
            return parseFloat(n || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },

        statusBadge(status) {
            const map = {
                completed: { cls: 'badge-green',  icon: 'ri-checkbox-circle-line', label: this.i18n.status_completed },
                pending:   { cls: 'badge-orange', icon: 'ri-time-line',            label: this.i18n.status_pending },
            };
            return map[status] || { cls: 'badge-red', icon: 'ri-close-circle-line', label: this.i18n.status_failed };
        },
    };
}
</script>
@endsection
