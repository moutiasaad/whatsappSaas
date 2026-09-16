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
        'delete_prompt'          => __('ui.platform_tenants_page.delete_prompt'),
        'delete_message'         => __('ui.platform_tenants_page.delete_message'),
        'delete_selected_confirm'=> __('ui.platform_tenants_page.delete_selected_confirm'),
        'impersonate_prompt'     => __('ui.platform_tenants_page.impersonate_prompt'),
        'impersonate_confirm'    => __('ui.platform_tenants_page.impersonate_confirm'),
        'block'                  => __('ui.platform_tenants_page.block'),
        'unblock'                => __('ui.platform_tenants_page.unblock'),
        'block_prompt'           => __('ui.platform_tenants_page.block_prompt'),
        'unblock_prompt'         => __('ui.platform_tenants_page.unblock_prompt'),
        'block_message'          => __('ui.platform_tenants_page.block_message'),
        'unblock_message'        => __('ui.platform_tenants_page.unblock_message'),
        'archive'                => __('ui.platform_tenants_page.archive'),
        'restore'                => __('ui.platform_tenants_page.restore'),
        'archive_prompt'         => __('ui.platform_tenants_page.archive_prompt'),
        'restore_prompt'         => __('ui.platform_tenants_page.restore_prompt'),
        'archive_message'        => __('ui.platform_tenants_page.archive_message'),
        'restore_message'        => __('ui.platform_tenants_page.restore_message'),
        'deleted_toast'          => __('ui.controller_messages.tenant_deleted'),
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
                                        {{-- Block / unblock the whole tenant. Icon + tooltip flip
                                             based on current state so a single button covers both
                                             actions without needing to duplicate a row. --}}
                                        <button type="button" @click="openBlockModal(tenant.id, tenant.name, tenant.is_active)"
                                                class="action-btn"
                                                :title="tenant.is_active ? i18n.block : i18n.unblock">
                                            <i :class="tenant.is_active ? 'ri-forbid-2-line' : 'ri-checkbox-circle-line'"></i>
                                        </button>
                                        {{-- Archive / restore. Uses `!!tenant.archived_at` so the
                                             icon flips based on presence, not the exact string value
                                             (the API returns an ISO timestamp when archived, null when not). --}}
                                        <button type="button" @click="openArchiveModal(tenant.id, tenant.name, !!tenant.archived_at)"
                                                class="action-btn"
                                                :title="!!tenant.archived_at ? i18n.restore : i18n.archive">
                                            <i :class="!!tenant.archived_at ? 'ri-inbox-unarchive-line' : 'ri-inbox-archive-line'"></i>
                                        </button>
                                        <button type="button" @click="openDeleteModal(tenant.id, tenant.name)"
                                                class="action-btn danger" title="{{ __('ui.platform_tenants_page.delete') }}">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
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
                <option value="enable">{{ __('ui.platform_tenants_page.bulk_enable') }}</option>
                <option value="disable">{{ __('ui.platform_tenants_page.bulk_disable') }}</option>
                <option value="delete">{{ __('ui.platform_tenants_page.bulk_delete') }}</option>
            </select>
            <button type="button" @click="submitBulk()" :disabled="bulkSaving" class="btn btn-primary btn-sm">
                <span x-show="!bulkSaving">{{ __('ui.platform_tenants_page.apply') }}</span>
                <span x-show="bulkSaving"><span class="btn-spinner"></span></span>
            </button>
        </div>
        <button type="button" class="bulk-close" @click="selected = []"><i class="ri-close-line"></i></button>
    </div>

    {{-- Delete Modal --}}
    <div class="modal-overlay" :class="deleteModal.show ? 'show' : ''" role="dialog" aria-modal="true"
         @click.self="deleteModal.show = false" @keydown.escape.window="deleteModal.show = false">
        <div class="modal-box" style="max-width:420px">
            <div class="modal-icon danger"><i class="ri-delete-bin-line"></i></div>
            <h3 x-text="i18n.delete_prompt.replace(':name', deleteModal.name)"></h3>
            <p x-text="i18n.delete_message"></p>
            <div class="modal-actions">
                <button type="button" @click="deleteModal.show = false" class="btn btn-outline">
                    {{ __('ui.cancel') }}
                </button>
                <button type="button" @click="confirmDelete()" :disabled="deleteModal.saving" class="btn btn-danger">
                    <span x-show="!deleteModal.saving"><i class="ri-delete-bin-line"></i> {{ __('ui.delete') }}</span>
                    <span x-show="deleteModal.saving"><span class="btn-spinner"></span> {{ __('ui.deleting') }}</span>
                </button>
            </div>
        </div>
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

    {{-- Archive / Restore Modal — same one-modal-two-directions shape as block. --}}
    <div class="modal-overlay" :class="archiveModal.show ? 'show' : ''" role="dialog" aria-modal="true"
         @click.self="archiveModal.show = false" @keydown.escape.window="archiveModal.show = false">
        <div class="modal-box" style="max-width:460px">
            <div class="modal-icon" :class="archiveModal.currentlyArchived ? '' : 'danger'"
                 :style="archiveModal.currentlyArchived ? 'color:var(--brand);background:rgba(16,185,129,.15)' : ''">
                <i :class="archiveModal.currentlyArchived ? 'ri-inbox-unarchive-line' : 'ri-inbox-archive-line'"></i>
            </div>
            <h3 x-text="(archiveModal.currentlyArchived ? i18n.restore_prompt : i18n.archive_prompt).replace(':name', archiveModal.name)"></h3>
            <p x-text="archiveModal.currentlyArchived ? i18n.restore_message : i18n.archive_message"></p>
            <div class="modal-actions">
                <button type="button" @click="archiveModal.show = false" class="btn btn-outline">
                    {{ __('ui.cancel') }}
                </button>
                <button type="button" @click="confirmArchiveToggle()" :disabled="archiveModal.saving"
                        :class="archiveModal.currentlyArchived ? 'btn btn-primary' : 'btn btn-danger'">
                    <span x-show="!archiveModal.saving">
                        <i :class="archiveModal.currentlyArchived ? 'ri-inbox-unarchive-line' : 'ri-inbox-archive-line'"></i>
                        <span x-text="archiveModal.currentlyArchived ? i18n.restore : i18n.archive"></span>
                    </span>
                    <span x-show="archiveModal.saving">
                        <span class="btn-spinner"></span> {{ __('ui.processing') }}
                    </span>
                </button>
            </div>
        </div>
    </div>

    {{-- Block / Unblock Modal — one modal handles both directions. Icon + copy
         + confirm-button variant swap based on the tenant's CURRENT state so
         we don't need two near-duplicate modals. --}}
    <div class="modal-overlay" :class="blockModal.show ? 'show' : ''" role="dialog" aria-modal="true"
         @click.self="blockModal.show = false" @keydown.escape.window="blockModal.show = false">
        <div class="modal-box" style="max-width:460px">
            <div class="modal-icon" :class="blockModal.currentlyActive ? 'danger' : ''"
                 :style="blockModal.currentlyActive ? '' : 'color:var(--brand);background:rgba(16,185,129,.15)'">
                <i :class="blockModal.currentlyActive ? 'ri-forbid-2-line' : 'ri-checkbox-circle-line'"></i>
            </div>
            <h3 x-text="(blockModal.currentlyActive ? i18n.block_prompt : i18n.unblock_prompt).replace(':name', blockModal.name)"></h3>
            <p x-text="blockModal.currentlyActive ? i18n.block_message : i18n.unblock_message"></p>
            <div class="modal-actions">
                <button type="button" @click="blockModal.show = false" class="btn btn-outline">
                    {{ __('ui.cancel') }}
                </button>
                <button type="button" @click="confirmBlockToggle()" :disabled="blockModal.saving"
                        :class="blockModal.currentlyActive ? 'btn btn-danger' : 'btn btn-primary'">
                    <span x-show="!blockModal.saving">
                        <i :class="blockModal.currentlyActive ? 'ri-forbid-2-line' : 'ri-checkbox-circle-line'"></i>
                        <span x-text="blockModal.currentlyActive ? i18n.block : i18n.unblock"></span>
                    </span>
                    <span x-show="blockModal.saving">
                        <span class="btn-spinner"></span> {{ __('ui.processing') }}
                    </span>
                </button>
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
        destroyUrlTpl:     @json(route('super_admin.platform.tenants.destroy', ['tenant' => '__ID__'])),
        impersonateUrlTpl: @json(route('super_admin.platform.tenants.impersonate-admin', ['tenant' => '__ID__'])),
        toggleActiveUrlTpl:@json(route('super_admin.platform.tenants.toggle-active',     ['tenant' => '__ID__'])),
        toggleArchiveUrlTpl:@json(route('super_admin.platform.tenants.toggle-archive',   ['tenant' => '__ID__'])),
        bulkUrl:     @json(route('super_admin.platform.tenants.bulk')),

        tenants:     [],
        stats:       { total: null, active: null, trial: null, inactive: null },
        loading:     true,
        page:        1,
        lastPage:    1,
        total:       0,

        search:     '',
        filters:    { plan_id: '', status: '', is_active: '', archived: '' },

        selected:   [],
        bulkAction: 'enable',
        bulkSaving: false,

        deleteModal:      { show: false, id: null, name: '', saving: false },
        impersonateModal: { show: false, id: null, name: '', url: '' },
        blockModal:       { show: false, id: null, name: '', currentlyActive: true, saving: false },
        archiveModal:     { show: false, id: null, name: '', currentlyArchived: false, saving: false },

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
            this.filters = { plan_id: '', status: '', is_active: '', archived: '' };
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

        openDeleteModal(id, name) {
            this.deleteModal = { show: true, id, name, saving: false };
        },

        openImpersonateModal(id, name) {
            // URL is resolved here so the modal's Confirm button is a real
            // <a href="..."> — right-click "open in new tab" then works
            // exactly like the row's own button, and no JS is required
            // between click and navigation.
            this.impersonateModal = { show: true, id, name, url: this.impersonateUrl(id) };
        },

        openBlockModal(id, name, currentlyActive) {
            // `currentlyActive` is captured at open time so the modal's
            // labels/colors don't flip mid-confirmation if another tab
            // toggles the tenant while this modal is open.
            this.blockModal = { show: true, id, name, currentlyActive: !!currentlyActive, saving: false };
        },

        async confirmBlockToggle() {
            this.blockModal.saving = true;
            try {
                const res = await fetch(this.toggleActiveUrlTpl.replace('__ID__', String(this.blockModal.id)), {
                    method: 'PATCH',
                    headers: {
                        'Accept':       'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    credentials: 'same-origin',
                });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                this.blockModal.show = false;
                // Refresh the table so the row's icon/tooltip flip to reflect
                // the new state without a hard page reload.
                this.loadData();
            } catch (e) {
                console.error('Toggle tenant active failed:', e);
            } finally {
                this.blockModal.saving = false;
            }
        },

        openArchiveModal(id, name, currentlyArchived) {
            this.archiveModal = { show: true, id, name, currentlyArchived: !!currentlyArchived, saving: false };
        },

        async confirmArchiveToggle() {
            this.archiveModal.saving = true;
            try {
                const res = await fetch(this.toggleArchiveUrlTpl.replace('__ID__', String(this.archiveModal.id)), {
                    method: 'PATCH',
                    headers: {
                        'Accept':       'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    credentials: 'same-origin',
                });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                this.archiveModal.show = false;
                // If we just archived a tenant while viewing the default list
                // (which hides archived), the row will disappear on reload —
                // that's the correct behavior. If viewing "archived only",
                // an unarchive will similarly hide it. Either way, reload
                // reflects the truth.
                this.loadData();
            } catch (e) {
                console.error('Toggle tenant archive failed:', e);
            } finally {
                this.archiveModal.saving = false;
            }
        },

        async confirmDelete() {
            this.deleteModal.saving = true;
            try {
                const res = await fetch(this.destroyUrlTpl.replace('__ID__', String(this.deleteModal.id)), {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                });
                if (!res.ok) throw new Error();
                const name = this.deleteModal.name;
                this.deleteModal.show = false;
                window.showToast?.('success', this.i18n.deleted_toast.replace(':name', name));
                this.reload();
            } catch {
                window.showToast?.('error', 'Erreur lors de la suppression.');
                this.deleteModal.saving = false;
            }
        },

        async submitBulk() {
            if (!this.selected.length) return;
            if (this.bulkAction === 'delete') {
                if (!confirm(this.i18n.delete_selected_confirm)) return;
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
