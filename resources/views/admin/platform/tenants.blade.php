@extends('layouts.admin')

@section('title', __('ui.platform_tenants_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.platform_tenants_page.breadcrumb_root') }}</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ __('ui.platform_tenants_page.breadcrumb') }}</span>
@endsection

@section('content')
@php
    $i18n = [
        'no_tenants_found'       => __('ui.platform_tenants_page.no_tenants_found'),
        'try_adjusting'          => __('ui.platform_tenants_page.try_adjusting'),
        'no_plan'                => __('ui.platform_tenants_page.no_plan'),
        'inactive'               => __('ui.platform_tenants_page.inactive'),
        'archive_selected_confirm'=> __('ui.platform_tenants_page.archive_selected_confirm'),
        'impersonate_prompt'     => __('ui.platform_tenants_page.impersonate_prompt'),
        'impersonate_confirm'    => __('ui.platform_tenants_page.impersonate_confirm'),
        'selected_items'         => __('ui.selected_items'),
        'loading'                => __('ui.conversations_page.loading'),
        'statuses'               => __('ui.platform_tenants_page.statuses'),
    ];
@endphp

<div x-data="tenantsPage()" x-init="init()" x-cloak>

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
            <div class="stat-card-value" x-text="stats.total ?? '-'"></div>
            <div class="stat-card-label">{{ __('ui.platform_tenants_page.total_tenants') }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-checkbox-circle-line"></i></div>
            <div class="stat-card-value" x-text="stats.active ?? '-'"></div>
            <div class="stat-card-label">{{ __('ui.platform_tenants_page.active_subscriptions') }}</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-card-icon"><i class="ri-time-line"></i></div>
            <div class="stat-card-value" x-text="stats.trial ?? '-'"></div>
            <div class="stat-card-label">{{ __('ui.platform_tenants_page.trialing') }}</div>
        </div>
        <div class="stat-card red">
            <div class="stat-card-icon"><i class="ri-pause-circle-line"></i></div>
            <div class="stat-card-value" x-text="stats.inactive ?? '-'"></div>
            <div class="stat-card-label">{{ __('ui.platform_tenants_page.inactive_tenants') }}</div>
        </div>
    </div>

    <div class="table-toolbar" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-lg);margin-bottom:1rem">
        <div class="filter-input-wrap">
            <i class="ri-search-line"></i>
            <input type="text" x-model="search" @input.debounce.350ms="reload()"
                   placeholder="{{ __('ui.platform_tenants_page.search_placeholder') }}" class="filter-input">
        </div>

        <select x-model="filters.plan_id" @change="reload()" class="toolbar-select">
            <option value="">{{ __('ui.platform_tenants_page.all_plans') }}</option>
            <template x-for="plan in plans" :key="plan.id">
                <option :value="String(plan.id)" x-text="plan.name"></option>
            </template>
        </select>

        <select x-model="filters.status" @change="reload()" class="toolbar-select">
            <option value="">{{ __('ui.platform_tenants_page.all_status') }}</option>
            @foreach(['trial','active','suspended','cancelled'] as $s)
                <option value="{{ $s }}">{{ __('ui.platform_tenants_page.statuses.' . $s) }}</option>
            @endforeach
        </select>

        <select x-model="filters.is_active" @change="reload()" class="toolbar-select">
            <option value="">{{ __('ui.platform_tenants_page.active_inactive') }}</option>
            <option value="1">{{ __('ui.platform_tenants_page.active_only') }}</option>
            <option value="0">{{ __('ui.platform_tenants_page.inactive_only') }}</option>
        </select>

        {{-- Archive filter. Default (blank) hides archived rows from the main list.
             "1" shows only archived. "all" shows both. --}}
        <select x-model="filters.archived" @change="reload()" class="toolbar-select">
            <option value="">{{ __('ui.platform_tenants_page.archive_hide') }}</option>
            <option value="1">{{ __('ui.platform_tenants_page.archive_only') }}</option>
            <option value="all">{{ __('ui.platform_tenants_page.archive_all') }}</option>
        </select>

        <button type="button" @click="clearFilters()" class="btn btn-ghost btn-sm">{{ __('ui.platform_tenants_page.clear') }}</button>
    </div>

    {{-- Second row: date-range filters + one-click presets. Compact styling
         so it doesn't compete visually with the main toolbar above. --}}
    <div style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-lg);margin-bottom:1rem;padding:.625rem .875rem;display:flex;flex-wrap:wrap;align-items:center;gap:.75rem;font-size:.8125rem;">
        <div style="display:flex;align-items:center;gap:.375rem;">
            <label style="color:var(--text-muted);font-weight:500;">{{ __('ui.platform_tenants_page.filter_created') }}</label>
            <input type="date" x-model="filters.created_from" @change="reload()"
                   class="filter-input" style="padding:.375rem .5rem;min-width:9rem;">
            <span style="color:var(--text-muted);">–</span>
            <input type="date" x-model="filters.created_to" @change="reload()"
                   class="filter-input" style="padding:.375rem .5rem;min-width:9rem;">
        </div>

        <div style="display:flex;align-items:center;gap:.375rem;">
            <label style="color:var(--text-muted);font-weight:500;">{{ __('ui.platform_tenants_page.filter_ends') }}</label>
            <input type="date" x-model="filters.ends_from" @change="reload()"
                   class="filter-input" style="padding:.375rem .5rem;min-width:9rem;">
            <span style="color:var(--text-muted);">–</span>
            <input type="date" x-model="filters.ends_to" @change="reload()"
                   class="filter-input" style="padding:.375rem .5rem;min-width:9rem;">
        </div>

        <div style="display:flex;align-items:center;gap:.375rem;margin-left:auto;flex-wrap:wrap;">
            <span style="color:var(--text-muted);font-weight:500;">{{ __('ui.platform_tenants_page.presets_label') }}</span>
            <button type="button" @click="applyPreset('trials_ending_soon')" class="btn btn-outline btn-sm">
                {{ __('ui.platform_tenants_page.preset_trials_ending_soon') }}
            </button>
            <button type="button" @click="applyPreset('renewing_this_month')" class="btn btn-outline btn-sm">
                {{ __('ui.platform_tenants_page.preset_renewing_this_month') }}
            </button>
            <button type="button" @click="applyPreset('new_this_week')" class="btn btn-outline btn-sm">
                {{ __('ui.platform_tenants_page.preset_new_this_week') }}
            </button>
        </div>
    </div>

    {{-- Loading --}}
    <div x-show="loading" class="spinner-wrap" style="min-height:200px">
        <div>
            <div class="spinner" style="margin:0 auto 1rem"></div>
            <div style="color:var(--text-muted);font-size:.875rem;text-align:center" x-text="i18n.loading"></div>
        </div>
    </div>

    <div class="card" style="padding:0" x-show="!loading">

        <div x-show="tenants.length === 0" class="empty-state">
            <div class="empty-state-icon"><i class="ri-building-2-line"></i></div>
            <h4 x-text="i18n.no_tenants_found"></h4>
            <p x-text="i18n.try_adjusting"></p>
            <a href="{{ route('super_admin.platform.tenants.create') }}" class="btn btn-primary">{{ __('ui.platform_tenants_page.add_tenant') }}</a>
        </div>

        <div x-show="tenants.length > 0">
            <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:2.5rem">
                                <input type="checkbox" class="header-cb"
                                       :checked="isAllSelected()"
                                       :indeterminate.prop="isIndeterminate()"
                                       @change="toggleAll()">
                            </th>
                            <th>{{ __('ui.platform_tenants_page.tenant') }}</th>
                            <th>{{ __('ui.platform_tenants_page.plan') }}</th>
                            <th>{{ __('ui.platform_tenants_page.status') }}</th>
                            <th>{{ __('ui.platform_tenants_page.users') }}</th>
                            <th>{{ __('ui.platform_tenants_page.plan_period') }}</th>
                            <th>{{ __('ui.platform_tenants_page.created') }}</th>
                            <th style="width:120px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="tenant in tenants" :key="tenant.id">
                            <tr>
                                <td>
                                    <input type="checkbox" class="row-cb"
                                           :checked="isSelected(tenant.id)"
                                           @change="toggleSelect(tenant.id)">
                                </td>
                                <td>
                                    <div style="display:flex;flex-direction:column;gap:.125rem">
                                        <span style="font-weight:600;color:var(--text-primary)" x-text="tenant.name"></span>
                                        <span style="font-size:.75rem;color:var(--text-muted)" x-text="tenant.slug"></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-gray" x-text="tenant.plan ? tenant.plan.name : i18n.no_plan"></span>
                                </td>
                                <td>
                                    <span :class="statusBadge(tenant.subscription_status)">
                                        <i :class="statusIcon(tenant.subscription_status)"></i>
                                        <span x-text="statusLabel(tenant.subscription_status)"></span>
                                    </span>
                                    <template x-if="!tenant.is_active">
                                        <span class="badge badge-gray" x-text="i18n.inactive"></span>
                                    </template>
                                </td>
                                <td x-text="tenant.users_count ?? 0"></td>
                                {{-- Plan period: for a trial workspace, from tenant creation to
                                     trial_ends_at. For an active/paid workspace, from
                                     subscription_starts_at to subscription_ends_at. Rendered by
                                     planPeriod() so the branching stays out of the template. --}}
                                <td>
                                    <template x-if="planPeriod(tenant)">
                                        <span style="font-size:.8125rem;color:var(--text-secondary);white-space:nowrap;" x-text="planPeriod(tenant)"></span>
                                    </template>
                                    <template x-if="!planPeriod(tenant)">
                                        <span style="color:var(--text-muted)">—</span>
                                    </template>
                                </td>
                                <td>
                                    <span style="font-size:.8125rem;color:var(--text-muted)" x-text="formatDate(tenant.created_at)"></span>
                                </td>
                                <td>
                                    <div style="display:flex;gap:.25rem;justify-content:flex-end">
                                        <a :href="showUrl(tenant.id)" class="action-btn" title="{{ __('ui.platform_tenants_page.view') }}">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                        <a :href="editUrl(tenant.id)" class="action-btn" title="{{ __('ui.platform_tenants_page.edit') }}">
                                            <i class="ri-pencil-line"></i>
                                        </a>
                                        {{-- One-click "log in as this tenant". Opens the styled
                                             modal below rather than a native browser confirm — the
                                             copy has apostrophes that would collide with onclick=""
                                             attribute quotes, and the delete-modal-adjacent look
                                             matches what the rest of the platform panel uses. --}}
                                        <button type="button" @click="openImpersonateModal(tenant.id, tenant.name)"
                                                class="action-btn" title="{{ __('ui.platform_tenants_page.impersonate') }}">
                                            <i class="ri-login-box-line"></i>
                                        </button>
                                        {{-- Block, archive and delete used to live here as row
                                             icons; they now live on the tenant Edit page's Danger
                                             zone so the whole tenant profile has to be open before
                                             an irreversible action is one click away. --}}
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

    {{-- Bulk Bar --}}
    <div class="bulk-bar" :class="selected.length > 0 ? 'visible' : ''">
        <span class="bulk-count" x-text="selected.length + ' ' + i18n.selected_items"></span>
        <span class="bulk-sep">|</span>
        <div class="bulk-actions" style="display:flex;gap:.5rem;align-items:center;">
            <select x-model="bulkAction" class="form-control" style="min-width:170px;">
                <option value="archive">{{ __('ui.platform_tenants_page.bulk_archive') }}</option>
            </select>
            <button type="button" @click="submitBulk()" :disabled="bulkSaving" class="btn btn-primary btn-sm">
                <span x-show="!bulkSaving">{{ __('ui.platform_tenants_page.apply') }}</span>
                <span x-show="bulkSaving"><span class="btn-spinner"></span></span>
            </button>
        </div>
        <button type="button" class="bulk-close" @click="selected = []"><i class="ri-close-line"></i></button>
    </div>

    {{-- Impersonate Modal — mirrors the delete-modal shape so both live in the
         same visual family. Confirming navigates to the impersonation URL; the
         server does the actual role switch. --}}
    <div class="modal-overlay" :class="impersonateModal.show ? 'show' : ''" role="dialog" aria-modal="true"
         @click.self="impersonateModal.show = false" @keydown.escape.window="impersonateModal.show = false">
        <div class="modal-box" style="max-width:460px">
            <div class="modal-icon" style="color:var(--brand);background:rgba(16,185,129,.15)"><i class="ri-login-box-line"></i></div>
            <h3 x-text="i18n.impersonate_prompt.replace(':name', impersonateModal.name)"></h3>
            <p x-text="i18n.impersonate_confirm"></p>
            <div class="modal-actions">
                <button type="button" @click="impersonateModal.show = false" class="btn btn-outline">
                    {{ __('ui.cancel') }}
                </button>
                <a :href="impersonateModal.url" class="btn btn-primary">
                    <i class="ri-login-box-line"></i> {{ __('ui.platform_tenants_page.impersonate') }}
                </a>
            </div>
        </div>
    </div>

</div>

<script>
function tenantsPage() {
    return {
        i18n:        @json($i18n),
        plans:       @json($plans),
        indexUrl:    @json(route('super_admin.platform.tenants')),
        showUrlTpl:        @json(route('super_admin.platform.tenants.show',   ['tenant' => '__ID__'])),
        editUrlTpl:        @json(route('super_admin.platform.tenants.edit',   ['tenant' => '__ID__'])),
        impersonateUrlTpl: @json(route('super_admin.platform.tenants.impersonate-admin', ['tenant' => '__ID__'])),
        bulkUrl:     @json(route('super_admin.platform.tenants.bulk')),

        tenants:     [],
        stats:       { total: null, active: null, trial: null, inactive: null },
        loading:     true,
        page:        1,
        lastPage:    1,
        total:       0,

        search:     '',
        filters:    { plan_id: '', status: '', is_active: '', archived: '', created_from: '', created_to: '', ends_from: '', ends_to: '' },

        selected:   [],
        bulkAction: 'archive',
        bulkSaving: false,

        impersonateModal: { show: false, id: null, name: '', url: '' },

        init() {
            this.loadData();
            window.addEventListener('pageshow', (e) => {
                if (e.persisted) this.loadData();
            });
        },

        reload() {
            this.page     = 1;
            this.selected = [];
            this.loadData();
        },

        buildParams() {
            const p = new URLSearchParams();
            p.set('per_page', '20');
            p.set('page', String(this.page));
            if (this.search.trim())        p.set('search',    this.search.trim());
            if (this.filters.plan_id)      p.set('plan_id',   this.filters.plan_id);
            if (this.filters.status)       p.set('status',    this.filters.status);
            if (this.filters.is_active !== '') p.set('is_active', this.filters.is_active);
            if (this.filters.archived)          p.set('archived',  this.filters.archived);
            if (this.filters.created_from)      p.set('created_from', this.filters.created_from);
            if (this.filters.created_to)        p.set('created_to',   this.filters.created_to);
            if (this.filters.ends_from)         p.set('ends_from',    this.filters.ends_from);
            if (this.filters.ends_to)           p.set('ends_to',      this.filters.ends_to);
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
                this.tenants  = data.data || [];
                this.page     = data.current_page ?? 1;
                this.lastPage = data.last_page ?? 1;
                this.total    = data.total ?? 0;
                if (data.stats) this.stats = data.stats;
            } catch (e) {
                console.error('Tenants fetch failed:', e);
                this.tenants = [];
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

        clearFilters() {
            this.search  = '';
            this.filters = { plan_id: '', status: '', is_active: '', archived: '', created_from: '', created_to: '', ends_from: '', ends_to: '' };
            this.reload();
        },

        // Preset chips. Each rebuilds the filters state from scratch (rather
        // than layering on top of whatever the user had) so clicking a
        // preset always yields a predictable result.
        applyPreset(name) {
            const today = new Date();
            const iso   = (d) => d.toISOString().slice(0, 10); // YYYY-MM-DD
            const plus  = (days) => { const d = new Date(today); d.setDate(d.getDate() + days); return d; };
            const minus = (days) => { const d = new Date(today); d.setDate(d.getDate() - days); return d; };

            // Blank slate first — presets are exclusive, not additive.
            this.search  = '';
            this.filters = { plan_id: '', status: '', is_active: '', archived: '', created_from: '', created_to: '', ends_from: '', ends_to: '' };

            switch (name) {
                case 'trials_ending_soon':
                    // Trials whose 7-day (or however long) window ends
                    // between today and 7 days from now.
                    this.filters.status    = 'trial';
                    this.filters.ends_from = iso(today);
                    this.filters.ends_to   = iso(plus(7));
                    break;

                case 'renewing_this_month':
                    // Paid subscriptions whose renewal date falls in the
                    // next 30 days — the "who needs a heads-up email"
                    // bucket.
                    this.filters.status    = 'active';
                    this.filters.ends_from = iso(today);
                    this.filters.ends_to   = iso(plus(30));
                    break;

                case 'new_this_week':
                    this.filters.created_from = iso(minus(7));
                    this.filters.created_to   = iso(today);
                    break;
            }

            this.reload();
        },

        isSelected(id)    { return this.selected.includes(id); },
        toggleSelect(id)  {
            if (this.isSelected(id)) this.selected = this.selected.filter(s => s !== id);
            else this.selected.push(id);
        },
        toggleAll() {
            this.selected = this.isAllSelected() ? [] : this.tenants.map(t => t.id);
        },
        isAllSelected()   { return this.tenants.length > 0 && this.selected.length === this.tenants.length; },
        isIndeterminate() { return this.selected.length > 0 && this.selected.length < this.tenants.length; },

        openImpersonateModal(id, name) {
            // URL is resolved here so the modal's Confirm button is a real
            // <a href="..."> — right-click "open in new tab" then works
            // exactly like the row's own button, and no JS is required
            // between click and navigation.
            this.impersonateModal = { show: true, id, name, url: this.impersonateUrl(id) };
        },


        async submitBulk() {
            if (!this.selected.length) return;
            if (this.bulkAction === 'archive') {
                if (!confirm(this.i18n.archive_selected_confirm)) return;
            }
            this.bulkSaving = true;
            try {
                const res = await fetch(this.bulkUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept':       'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ action: this.bulkAction, ids: this.selected.join(',') }),
                });
                if (!res.ok) throw new Error();
                this.selected = [];
                this.reload();
            } catch {
                window.showToast?.('error', 'Erreur lors de l\'action groupée.');
            } finally {
                this.bulkSaving = false;
            }
        },

        statusBadge(s) {
            const map = { active: 'badge badge-green', trial: 'badge badge-orange', suspended: 'badge badge-red' };
            return map[s] || 'badge badge-gray';
        },

        statusIcon(s) {
            const map = { active: 'ri-checkbox-circle-line', trial: 'ri-time-line', suspended: 'ri-close-circle-line' };
            return map[s] || 'ri-stop-circle-line';
        },

        statusLabel(s) {
            return (this.i18n.statuses && this.i18n.statuses[s]) || s;
        },

        formatDate(ts) {
            if (!ts) return '-';
            return new Date(ts).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
        },

        // "Plan period" = the window the tenant is currently billed/trialing
        // for. Trial uses trial_ends_at (set at register), paid uses
        // subscription_ends_at (set on capture). Start is subscription_starts_at
        // for paid, or the tenant's created_at for trial since the trial begins
        // at signup. Returns "" when there's no meaningful window (e.g. a
        // suspended tenant with no future date on either column) — caller
        // renders an em-dash in that case.
        planPeriod(tenant) {
            const status = tenant.subscription_status;
            let start = null, end = null;

            if (status === 'trial') {
                start = tenant.subscription_starts_at ?? tenant.created_at;
                end   = tenant.trial_ends_at;
            } else if (status === 'active') {
                start = tenant.subscription_starts_at ?? tenant.created_at;
                end   = tenant.subscription_ends_at;
            } else {
                // suspended / cancelled — show the last-known window if we
                // still have both dates, otherwise nothing (the row's
                // status badge already tells the story).
                start = tenant.subscription_starts_at;
                end   = tenant.subscription_ends_at ?? tenant.trial_ends_at;
            }

            if (!start || !end) return '';
            return `${this.formatDate(start)} → ${this.formatDate(end)}`;
        },

        showUrl(id)        { return this.showUrlTpl.replace('__ID__', String(id)); },
        editUrl(id)        { return this.editUrlTpl.replace('__ID__', String(id)); },
        impersonateUrl(id) { return this.impersonateUrlTpl.replace('__ID__', String(id)); },
    };
}
</script>
@endsection
