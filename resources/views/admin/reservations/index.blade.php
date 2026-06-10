@extends('layouts.admin')

@section('title', __('ui.reservations.title'))

@section('content')
@php
    $routeBase = url(auth()->user()->routeNamePrefix() . '/reservations');
    $panelPrefix = auth()->user()->routeNamePrefix();
@endphp
<div x-data="reservationsPage()" x-init="init()" x-cloak>
<div class="content-area">

    {{-- Page header --}}
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">{{ __('ui.reservations.title') }}</h1>
            <p class="page-subtitle">{{ __('ui.reservations.subtitle') }}</p>
        </div>
        <div class="page-header-actions">
            <a href="{{ route($panelPrefix.'.reservations.slots') }}" class="btn btn-secondary">
                <i class="ri-time-line"></i> {{ __('ui.reservations.manage_slots') }}
            </a>
            <a href="{{ route($panelPrefix.'.reservations.settings') }}" class="btn btn-secondary">
                <i class="ri-settings-3-line"></i> {{ __('ui.reservations.settings') }}
            </a>
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-calendar-check-line"></i></div>
            <div class="stat-card-value" x-text="stats.upcoming ?? 0"></div>
            <div class="stat-card-label">{{ __('ui.reservations.upcoming') }}</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-card-icon"><i class="ri-calendar-2-line"></i></div>
            <div class="stat-card-value" x-text="stats.today ?? 0"></div>
            <div class="stat-card-label">{{ __('ui.reservations.today') }}</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-card-icon"><i class="ri-list-check-2"></i></div>
            <div class="stat-card-value" x-text="stats.total ?? 0"></div>
            <div class="stat-card-label">{{ __('ui.reservations.total') }}</div>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="table-toolbar" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-lg);margin-bottom:1rem">
        <div class="filter-input-wrap">
            <i class="ri-search-line"></i>
            <input type="text" x-model="filters.search" @input.debounce.400ms="reload(1)"
                   placeholder="{{ __('ui.reservations.search_placeholder') }}" class="filter-input">
        </div>

        <select class="toolbar-select" x-model="filters.status" @change="reload(1)">
            <option value="">{{ __('ui.reservations.all_statuses') }}</option>
            <option value="confirmed">{{ __('ui.reservations.status_confirmed') }}</option>
            <option value="pending">{{ __('ui.reservations.status_pending') }}</option>
            <option value="cancelled">{{ __('ui.reservations.status_cancelled') }}</option>
            <option value="completed">{{ __('ui.reservations.status_completed') }}</option>
        </select>

        <input type="date" class="toolbar-select" x-model="filters.date_from" @change="reload(1)"
               title="{{ __('ui.reservations.from_date') }}">
        <input type="date" class="toolbar-select" x-model="filters.date_to" @change="reload(1)"
               title="{{ __('ui.reservations.to_date') }}">
    </div>

    {{-- Table --}}
    <div class="table-card">
        <div x-show="loading" class="table-loading"><div class="spinner"></div></div>

        <table class="data-table" x-show="!loading">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('ui.reservations.col_date') }}</th>
                    <th>{{ __('ui.reservations.col_time') }}</th>
                    <th>{{ __('ui.reservations.col_customer') }}</th>
                    <th>{{ __('ui.reservations.col_phone') }}</th>
                    <th>{{ __('ui.reservations.col_notes') }}</th>
                    <th>{{ __('ui.reservations.col_status') }}</th>
                    <th>{{ __('ui.reservations.col_actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="row in rows" :key="row.id">
                    <tr style="cursor:pointer" @click="window.location = showUrl(row.id)">
                        <td x-text="row.id" class="text-muted small"></td>
                        <td x-text="row.reservation_date" class="font-medium"></td>
                        <td x-text="row.start_time.slice(0,5) + ' – ' + row.end_time.slice(0,5)"></td>
                        <td x-text="row.customer_name || '—'"></td>
                        <td x-text="row.customer_phone" class="font-mono small"></td>
                        <td>
                            <span x-text="row.customer_notes ? row.customer_notes.slice(0,40) + (row.customer_notes.length>40?'…':'') : '—'"
                                  class="text-muted small"></span>
                        </td>
                        <td>
                            <span class="badge"
                                  :class="{
                                      'badge-green':  row.status === 'confirmed',
                                      'badge-orange': row.status === 'pending',
                                      'badge-red':    row.status === 'cancelled',
                                      'badge-gray':   row.status === 'completed'
                                  }"
                                  x-text="statusLabel(row.status)"></span>
                        </td>
                        <td @click.stop>
                            <div style="display:flex;gap:4px;align-items:center">
                                <a class="action-btn" :href="showUrl(row.id)" title="{{ __('ui.reservations.view_conversation') }}">
                                    <i class="ri-eye-line" style="color:var(--brand)"></i>
                                </a>
                                <template x-if="row.status === 'confirmed'">
                                    <button class="action-btn" title="{{ __('ui.reservations.mark_completed') }}"
                                            @click="setStatus(row, 'completed')">
                                        <i class="ri-checkbox-circle-line" style="color:var(--brand)"></i>
                                    </button>
                                </template>
                                <template x-if="row.status !== 'cancelled' && row.status !== 'completed'">
                                    <button class="action-btn" title="{{ __('ui.reservations.cancel_booking') }}"
                                            @click="setStatus(row, 'cancelled')">
                                        <i class="ri-close-circle-line" style="color:var(--orange)"></i>
                                    </button>
                                </template>
                                <button class="action-btn danger" title="{{ __('ui.delete') }}"
                                        @click="deleteModal = {show:true, id:row.id, name:row.customer_name || row.customer_phone, saving:false}">
                                    <i class="ri-delete-bin-6-line"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
                <tr x-show="!loading && rows.length === 0">
                    <td colspan="8" class="empty-state">
                        <i class="ri-calendar-line"></i>
                        <p>{{ __('ui.reservations.no_reservations') }}</p>
                    </td>
                </tr>
            </tbody>
        </table>

        {{-- Pagination --}}
        <div class="table-footer" x-show="pagination && pagination.last_page > 1">
            <span class="pagination-info"
                  x-text="`${pagination.from ?? 0}–${pagination.to ?? 0} / ${pagination.total ?? 0}`"></span>
            <div class="pagination-btns">
                <button class="btn-page" :disabled="pagination.current_page <= 1"
                        @click="reload(pagination.current_page - 1)">
                    <i class="ri-arrow-left-s-line"></i>
                </button>
                <template x-for="p in pageRange()" :key="p">
                    <button class="btn-page" :class="p === pagination.current_page ? 'active' : ''"
                            @click="reload(p)" x-text="p"></button>
                </template>
                <button class="btn-page" :disabled="pagination.current_page >= pagination.last_page"
                        @click="reload(pagination.current_page + 1)">
                    <i class="ri-arrow-right-s-line"></i>
                </button>
            </div>
        </div>
    </div>

    {{-- Delete modal --}}
    <div class="modal-overlay" :class="deleteModal.show ? 'show' : ''" @click.self="deleteModal.show=false">
        <div class="modal-card">
            <div class="modal-header">
                <h3>{{ __('ui.reservations.delete_confirm_title') }}</h3>
                <button class="modal-close" @click="deleteModal.show=false"><i class="ri-close-line"></i></button>
            </div>
            <div class="modal-body">
                <p>{{ __('ui.reservations.delete_confirm_body') }} <strong x-text="deleteModal.name"></strong>?</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" @click="deleteModal.show=false">{{ __('ui.cancel') }}</button>
                <button class="btn btn-danger" :disabled="deleteModal.saving" @click="confirmDelete()">
                    <span x-show="deleteModal.saving" class="spinner-sm"></span>
                    {{ __('ui.delete') }}
                </button>
            </div>
        </div>
    </div>
</div>{{-- end .content-area --}}
</div>{{-- end x-data --}}

<script>
function reservationsPage() {
    const csrf     = () => document.querySelector('meta[name=csrf-token]').content;
    const routeBase = '{{ $routeBase }}';

    return {
        loading: false,
        rows: [],
        stats: @json($stats),
        pagination: null,
        filters: { search: '', status: '', date_from: '', date_to: '' },
        deleteModal: { show: false, id: null, name: '', saving: false },

        showUrl(id) { return `${routeBase}/${id}`; },

        init() { this.reload(1); },

        async reload(page = 1) {
            this.loading = true;
            try {
                const params = new URLSearchParams({ page, per_page: 25 });
                Object.entries(this.filters).forEach(([k, v]) => { if (v) params.set(k, v); });
                const r = await fetch('?' + params, { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                this.rows       = d.data ?? [];
                this.stats      = d.stats ?? this.stats;
                this.pagination = { current_page: d.current_page, last_page: d.last_page, from: d.from, to: d.to, total: d.total };
            } finally {
                this.loading = false;
            }
        },

        async setStatus(row, status) {
            const r = await fetch(`${routeBase}/${row.id}/status`, {
                method: 'PATCH',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ status }),
            });
            if (r.ok) { row.status = status; this.reload(this.pagination?.current_page ?? 1); }
        },

        async confirmDelete() {
            this.deleteModal.saving = true;
            const r = await fetch(`${routeBase}/${this.deleteModal.id}`, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
            });
            if (r.ok) { this.deleteModal.show = false; this.reload(1); }
            this.deleteModal.saving = false;
        },

        statusLabel(s) {
            const map = {
                confirmed: '{{ __("ui.reservations.status_confirmed") }}',
                pending:   '{{ __("ui.reservations.status_pending") }}',
                cancelled: '{{ __("ui.reservations.status_cancelled") }}',
                completed: '{{ __("ui.reservations.status_completed") }}',
            };
            return map[s] ?? s;
        },

        pageRange() {
            if (!this.pagination) return [];
            const { current_page: c, last_page: l } = this.pagination;
            const start = Math.max(1, c - 2), end = Math.min(l, c + 2);
            return Array.from({ length: end - start + 1 }, (_, i) => start + i);
        },
    };
}
</script>
@endsection
