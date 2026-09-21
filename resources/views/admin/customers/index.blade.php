@extends('layouts.admin')

@section('title', __('ui.customers_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.customers_page.breadcrumb') }}</span>
@endsection

@section('content')
@php
    $panelPrefix = auth()->user()->routeNamePrefix();
    $tenantsJs   = ($tenants   ?? collect())->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values();
    $instancesJs = ($instances ?? collect())->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'tenant_id' => $i->tenant_id])->values();
    $i18n = [
        'active'             => __('ui.customers_page.active'),
        'stale'              => __('ui.customers_page.stale'),
        'dormant'            => __('ui.customers_page.dormant'),
        'unknown'            => __('ui.customers_page.unknown'),
        'n_a'                => __('ui.customers_page.n_a'),
        'no_customers_found' => __('ui.customers_page.no_customers_found'),
        'try_adjusting'      => __('ui.customers_page.try_adjusting'),
        'just_now'           => __('ui.conversations_page.just_now'),
        'minutes_ago'        => __('ui.conversations_page.minutes_ago'),
        'hours_ago'          => __('ui.conversations_page.hours_ago'),
        'days_ago'           => __('ui.conversations_page.days_ago'),
    ];
@endphp

<div x-data="customersPage()" x-init="init()" x-cloak>

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.customers_page.title') }}</div>
            <div class="page-subtitle">
                @if($isSuperAdmin ?? false)
                    {{ __('ui.customers_page.subtitle_superadmin') }}
                @else
                    {{ __('ui.customers_page.subtitle_default') }}
                @endif
            </div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route($panelPrefix . '.conversations.index') }}" class="btn btn-outline btn-sm">
                <i class="ri-message-3-line"></i> {{ __('ui.customers_page.open_conversations') }}
            </a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-contacts-line"></i></div>
            <div class="stat-card-value" x-text="stats.total ?? '-'"></div>
            <div class="stat-card-label">{{ __('ui.customers_page.total_customers') }}</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-card-icon"><i class="ri-message-2-line"></i></div>
            <div class="stat-card-value" x-text="stats.with_conversations ?? '-'"></div>
            <div class="stat-card-label">{{ __('ui.customers_page.with_conversations') }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-pulse-line"></i></div>
            <div class="stat-card-value" x-text="stats.active_7d ?? '-'"></div>
            <div class="stat-card-label">{{ __('ui.customers_page.active_7d') }}</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-card-icon"><i class="ri-time-line"></i></div>
            <div class="stat-card-value" x-text="stats.dormant_30d ?? '-'"></div>
            <div class="stat-card-label">{{ __('ui.customers_page.dormant_30d') }}</div>
        </div>
    </div>

    <div class="table-toolbar" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-lg);margin-bottom:1rem">
        <div class="filter-input-wrap">
            <i class="ri-search-line"></i>
            <input type="text" x-model="search" @input.debounce.350ms="reload()"
                   placeholder="{{ __('ui.customers_page.search_placeholder') }}" class="filter-input">
        </div>

        @if($isSuperAdmin ?? false)
            <select x-model="filters.tenant_id" @change="onTenantChange()" class="toolbar-select">
                <option value="">{{ __('ui.customers_page.all_tenants') }}</option>
                <template x-for="tenant in tenants" :key="tenant.id">
                    <option :value="String(tenant.id)" x-text="tenant.name"></option>
                </template>
            </select>
        @endif

        <select x-model="filters.instance_id" @change="reload()" class="toolbar-select">
            <option value="">{{ __('ui.customers_page.all_instances') }}</option>
            <template x-for="inst in filteredInstances()" :key="inst.id">
                <option :value="String(inst.id)" x-text="inst.name"></option>
            </template>
        </select>

        <select x-model="filters.has_conversations" @change="reload()" class="toolbar-select">
            <option value="">{{ __('ui.customers_page.any_conversation_state') }}</option>
            <option value="1">{{ __('ui.customers_page.with_conversations_filter') }}</option>
            <option value="0">{{ __('ui.customers_page.without_conversations_filter') }}</option>
        </select>

        <input type="text" x-model="filters.date_from" @change="reload()" class="toolbar-select"
               placeholder="{{ __('ui.date_placeholder_from') }}" aria-label="{{ __('ui.date_from') }}" title="{{ __('ui.date_from') }}">
        <input type="text" x-model="filters.date_to" @change="reload()" class="toolbar-select"
               placeholder="{{ __('ui.date_placeholder_to') }}" aria-label="{{ __('ui.date_to') }}" title="{{ __('ui.date_to') }}">

        <select x-model="filters.sort" @change="reload()" class="toolbar-select">
            <option value="activity_desc">{{ __('ui.customers_page.recent_activity') }}</option>
            <option value="activity_asc">{{ __('ui.customers_page.oldest_activity') }}</option>
            <option value="name_asc">{{ __('ui.customers_page.name_az') }}</option>
            <option value="name_desc">{{ __('ui.customers_page.name_za') }}</option>
            <option value="conversations_desc">{{ __('ui.customers_page.most_conversations') }}</option>
            <option value="conversations_asc">{{ __('ui.customers_page.fewest_conversations') }}</option>
        </select>

        <button type="button" @click="clearFilters()" class="btn btn-ghost btn-sm">{{ __('ui.customers_page.clear') }}</button>
    </div>

    <div class="card" style="padding:0">
        <div x-show="loading" class="spinner-wrap" style="min-height:200px">
            <div>
                <div class="spinner" style="margin:0 auto 1rem"></div>
                <div style="color:var(--text-muted);font-size:.875rem;text-align:center">{{ __('ui.conversations_page.loading') }}</div>
            </div>
        </div>

        <div x-show="!loading && customers.length === 0" class="empty-state" style="padding:3rem">
            <div class="empty-state-icon"><i class="ri-contacts-line"></i></div>
            <h4 x-text="i18n.no_customers_found"></h4>
            <p x-text="i18n.try_adjusting"></p>
        </div>

        <div x-show="!loading && customers.length > 0">
            <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.customers_page.customer') }}</th>
                            @if($isSuperAdmin ?? false)
                                <th>{{ __('ui.customers_page.tenant') }}</th>
                            @endif
                            <th>{{ __('ui.customers_page.phone') }}</th>
                            <th>{{ __('ui.customers_page.conversations') }}</th>
                            <th>{{ __('ui.customers_page.engagement') }}</th>
                            <th>{{ __('ui.customers_page.last_message') }}</th>
                            <th>{{ __('ui.customers_page.last_activity') }}</th>
                            <th style="width:96px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="customer in customers" :key="customer.id">
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:.75rem">
                                        <div style="width:2rem;height:2rem;border-radius:50%;background:linear-gradient(135deg,var(--brand),#059669);color:#fff;font-size:.6875rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;text-transform:uppercase"
                                             x-text="initials(customer)"></div>
                                        <div style="display:flex;flex-direction:column;gap:.125rem;min-width:0">
                                            <span style="font-weight:600;font-size:.875rem;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"
                                                  x-text="customer.display_name || i18n.unknown"></span>
                                            <span style="font-size:.75rem;color:var(--text-muted)" x-text="'ID #' + customer.id"></span>
                                        </div>
                                    </div>
                                </td>
                                @if($isSuperAdmin ?? false)
                                    <td>
                                        <span class="badge badge-purple">
                                            <i class="ri-building-2-line"></i>
                                            <span x-text="customer.tenant?.name || i18n.n_a"></span>
                                        </span>
                                    </td>
                                @endif
                                <td>
                                    <template x-if="customer.phone_kind === 'phone'">
                                        <span style="font-size:.875rem;font-family:monospace;color:var(--text-secondary)"
                                              x-text="customer.display_phone"></span>
                                    </template>
                                    <template x-if="customer.phone_kind === 'lid'">
                                        <span class="badge badge-gray" style="font-family:inherit;"
                                              :title="customer.phone_e164">
                                            <i class="ri-eye-off-line"></i>
                                            {{ __('ui.customers_page.phone_hidden') }}
                                        </span>
                                    </template>
                                    <template x-if="customer.phone_kind === 'empty'">
                                        <span style="color:var(--text-muted);">—</span>
                                    </template>
                                </td>
                                <td>
                                    <span class="badge badge-blue">
                                        <i class="ri-message-2-line"></i>
                                        <span x-text="customer.conversations_count ?? 0"></span>
                                    </span>
                                </td>
                                <td>
                                    <span :class="'badge ' + engagement(customer).cls">
                                        <i :class="engagement(customer).icon"></i>
                                        <span x-text="engagement(customer).label"></span>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:.8125rem;color:var(--text-muted)"
                                          x-text="timeAgo(customer.conversations_max_last_message_at)"></span>
                                </td>
                                <td>
                                    <span style="font-size:.8125rem;color:var(--text-muted)"
                                          x-text="timeAgo(customer.updated_at)"></span>
                                </td>
                                <td>
                                    <div style="display:flex;gap:.25rem;justify-content:flex-end">
                                        <a :href="customerUrl(customer.id)" class="action-btn" title="{{ __('ui.customers_page.view') }}">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                        <a :href="conversationsUrl + '?customer_id=' + customer.id + '&tab=all'" class="action-btn" title="{{ __('ui.customers_page.open_conversations') }}">
                                            <i class="ri-message-3-line"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div style="padding:12px 16px;display:flex;align-items:center;justify-content:flex-end;border-top:1px solid var(--card-border);flex-wrap:wrap;gap:8px;">
                <div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;">
                    <button @click="goToPage(page - 1)" :disabled="page <= 1 || loading" class="btn btn-outline btn-sm" style="padding:4px 10px;">‹</button>
                    <template x-for="n in pageRange()" :key="n">
                        <button x-text="n === '...' ? '…' : n"
                                @click="n !== '...' && goToPage(n)"
                                :disabled="loading"
                                :class="n === page ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm'"
                                style="padding:4px 10px;min-width:34px;"></button>
                    </template>
                    <button @click="goToPage(page + 1)" :disabled="page >= lastPage || loading" class="btn btn-outline btn-sm" style="padding:4px 10px;">›</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function customersPage() {
    return {
        i18n:             @json($i18n),
        isSuperAdmin:     @json($isSuperAdmin ?? false),
        tenants:          @json($tenantsJs),
        instances:        @json($instancesJs),
        indexUrl:         @json(route($panelPrefix . '.customers.index')),
        showUrlTpl:       @json(route($panelPrefix . '.customers.show', ['customer' => '__ID__'])),
        conversationsUrl: @json(route($panelPrefix . '.conversations.index')),

        search: '',
        filters: {
            tenant_id:         '',
            instance_id:       '',
            has_conversations: '',
            date_from:         '',
            date_to:           '',
            sort:              'activity_desc',
        },

        customers:   [],
        stats:       { total: null, with_conversations: null, active_7d: null, dormant_30d: null },
        loading:     true,
        page:        1,
        lastPage:    1,
        total:       0,

        init() {
            this.loadData();
            window.addEventListener('pageshow', (e) => {
                if (e.persisted) this.loadData();
            });
        },

        reload() {
            this.page = 1;
            this.loadData();
        },

        buildParams() {
            const p = new URLSearchParams();
            p.set('per_page', '30');
            p.set('page', String(this.page));
            if (this.search.trim()) p.set('search', this.search.trim());
            Object.entries(this.filters).forEach(([k, v]) => {
                if (v !== '' && v !== null && v !== undefined) p.set(k, v);
            });
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
                const data = await res.json();
                this.customers = data.data || [];
                this.page     = data.current_page ?? 1;
                this.lastPage = data.last_page ?? 1;
                this.total    = data.total ?? 0;
                if (data.stats) this.stats = data.stats;
            } catch (e) {
                console.error('Customers fetch failed:', e);
                this.customers = [];
            } finally {
                this.loading = false;
            }
        },

        goToPage(n) {
            if (n < 1 || n > this.lastPage) return;
            this.page = n;
            this.loadData();
        },

        pageRange() {
            const pages = [];
            const delta = 2;
            const left = this.page - delta;
            const right = this.page + delta;
            let last = 0;
            for (let i = 1; i <= this.lastPage; i++) {
                if (i === 1 || i === this.lastPage || (i >= left && i <= right)) {
                    if (last && i - last > 1) pages.push('...');
                    pages.push(i);
                    last = i;
                }
            }
            return pages;
        },

        onTenantChange() {
            this.filters.instance_id = '';
            this.reload();
        },

        clearFilters() {
            this.search  = '';
            this.filters = { tenant_id: '', instance_id: '', has_conversations: '', date_from: '', date_to: '', sort: 'activity_desc' };
            this.reload();
        },

        filteredInstances() {
            if (!this.isSuperAdmin || !this.filters.tenant_id) return this.instances;
            return this.instances.filter(i => String(i.tenant_id) === String(this.filters.tenant_id));
        },

        engagement(customer) {
            if (!customer.updated_at) return { cls: 'badge-gray', label: this.i18n.unknown, icon: 'ri-question-line' };
            const days = (Date.now() - new Date(customer.updated_at)) / 86400000;
            if (days <= 7)  return { cls: 'badge-green',  label: this.i18n.active,  icon: 'ri-pulse-line' };
            if (days <= 30) return { cls: 'badge-orange', label: this.i18n.stale,   icon: 'ri-time-line' };
            return              { cls: 'badge-gray',   label: this.i18n.dormant, icon: 'ri-moon-clear-line' };
        },

        initials(customer) {
            const s = customer.display_name || customer.display_phone || '?';
            return s.slice(0, 2).toUpperCase();
        },

        timeAgo(ts) {
            if (!ts) return '-';
            const diff = (Date.now() - new Date(ts)) / 1000;
            if (diff < 60)    return this.i18n.just_now;
            if (diff < 3600)  return `${Math.floor(diff / 60)}${this.i18n.minutes_ago}`;
            if (diff < 86400) return `${Math.floor(diff / 3600)}${this.i18n.hours_ago}`;
            return `${Math.floor(diff / 86400)}${this.i18n.days_ago}`;
        },

        customerUrl(id) {
            return this.showUrlTpl.replace('__ID__', String(id));
        },
    };
}
</script>
@endsection
