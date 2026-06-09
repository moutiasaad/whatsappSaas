@extends('layouts.admin')

@section('title', __('ui.teams_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.teams_page.breadcrumb') }}</span>
@endsection

@section('content')
@php
    $panelPrefix    = auth()->user()->routeNamePrefix();
    $canManageTeams = auth()->user()->hasAnyRole(['admin', 'super_admin']);
    $isSupervisor   = auth()->user()->isSupervisor();
    $i18n = [
        'active'                 => __('ui.teams_page.active'),
        'inactive'               => __('ui.teams_page.inactive'),
        'no_description'         => __('ui.teams_page.no_description'),
        'no_teams_found'         => __('ui.teams_page.no_teams_found'),
        'try_adjusting'          => __('ui.teams_page.try_adjusting'),
        'delete_team_prompt'     => __('ui.teams_page.delete_team_prompt'),
        'delete_team_message'    => __('ui.teams_page.delete_team_message'),
        'delete_selected_prompt' => __('ui.teams_page.delete_selected_prompt'),
        'deleted_toast'          => __('ui.controller_messages.team_deleted'),
        'selected_items'         => __('ui.selected_items'),
        'loading'                => __('ui.conversations_page.loading'),
    ];
@endphp

<div x-data="teamsPage()" x-init="init()" x-cloak>

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.teams_page.title') }}</div>
            <div class="page-subtitle">
                @if($isSupervisor)
                    {{ __('ui.teams_page.subtitle_supervisor') }}
                @else
                    {{ __('ui.teams_page.subtitle_default') }}
                @endif
            </div>
        </div>
        @if($canManageTeams)
            <div class="page-header-actions">
                <a href="{{ route($panelPrefix . '.teams.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> {{ __('ui.teams_page.new_team') }}
                </a>
            </div>
        @endif
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-team-line"></i></div>
            <div class="stat-card-value" x-text="stats.total ?? '-'"></div>
            <div class="stat-card-label">{{ __('ui.teams_page.total_teams') }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-checkbox-circle-line"></i></div>
            <div class="stat-card-value" x-text="stats.active ?? '-'"></div>
            <div class="stat-card-label">{{ __('ui.teams_page.active_teams') }}</div>
        </div>
        <div class="stat-card red">
            <div class="stat-card-icon"><i class="ri-pause-circle-line"></i></div>
            <div class="stat-card-value" x-text="stats.inactive ?? '-'"></div>
            <div class="stat-card-label">{{ __('ui.teams_page.inactive_teams') }}</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-card-icon"><i class="ri-inbox-line"></i></div>
            <div class="stat-card-value" x-text="stats.pool ?? '-'"></div>
            <div class="stat-card-label">{{ __('ui.teams_page.pool_conversations') }}</div>
        </div>
    </div>

    <div class="table-toolbar" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-lg);margin-bottom:1rem">
        <div class="filter-input-wrap">
            <i class="ri-search-line"></i>
            <input type="text" x-model="search" @input.debounce.350ms="reload()"
                   placeholder="{{ __('ui.teams_page.search_placeholder') }}" class="filter-input">
        </div>

        <select x-model="filters.is_active" @change="reload()" class="toolbar-select">
            <option value="">{{ __('ui.teams_page.active_inactive') }}</option>
            <option value="1">{{ __('ui.teams_page.active_only') }}</option>
            <option value="0">{{ __('ui.teams_page.inactive_only') }}</option>
        </select>

        <select x-model="filters.sort" @change="reload()" class="toolbar-select">
            <option value="name_asc">{{ __('ui.teams_page.name_az') }}</option>
            <option value="name_desc">{{ __('ui.teams_page.name_za') }}</option>
            <option value="activity_desc">{{ __('ui.teams_page.most_active') }}</option>
            <option value="pool_desc">{{ __('ui.teams_page.most_in_pool') }}</option>
        </select>

        <button type="button" @click="clearFilters()" class="btn btn-ghost btn-sm">{{ __('ui.teams_page.clear') }}</button>
    </div>

    {{-- Loading --}}
    <div x-show="loading" class="spinner-wrap" style="min-height:200px">
        <div>
            <div class="spinner" style="margin:0 auto 1rem"></div>
            <div style="color:var(--text-muted);font-size:.875rem;text-align:center" x-text="i18n.loading"></div>
        </div>
    </div>

    <div class="card" style="padding:0" x-show="!loading">

        <div x-show="teams.length === 0" class="empty-state" style="padding:3rem">
            <div class="empty-state-icon"><i class="ri-team-line"></i></div>
            <h4 x-text="i18n.no_teams_found"></h4>
            <p x-text="i18n.try_adjusting"></p>
            @if($canManageTeams)
                <a href="{{ route($panelPrefix . '.teams.create') }}" class="btn btn-primary">{{ __('ui.teams_page.create_team') }}</a>
            @endif
        </div>

        <div x-show="teams.length > 0">
            <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none">
                <table class="data-table">
                    <thead>
                        <tr>
                            @if($canManageTeams)
                                <th style="width:2.5rem">
                                    <input type="checkbox" class="header-cb"
                                           :checked="isAllSelected()"
                                           :indeterminate.prop="isIndeterminate()"
                                           @change="toggleAll()">
                                </th>
                            @endif
                            <th>{{ __('ui.teams_page.team') }}</th>
                            <th>{{ __('ui.teams_page.status') }}</th>
                            <th>{{ __('ui.teams_page.members') }}</th>
                            <th>{{ __('ui.teams_page.active_conversations') }}</th>
                            <th>{{ __('ui.teams_page.pool') }}</th>
                            <th>{{ __('ui.teams_page.closed') }}</th>
                            <th style="width:120px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="team in teams" :key="team.id">
                            <tr>
                                @if($canManageTeams)
                                    <td>
                                        <input type="checkbox" class="row-cb"
                                               :checked="isSelected(team.id)"
                                               @change="toggleSelect(team.id)">
                                    </td>
                                @endif
                                <td>
                                    <div style="display:flex;align-items:center;gap:.75rem;min-width:0;">
                                        <div style="width:2rem;height:2rem;border-radius:.625rem;background:linear-gradient(135deg,rgba(16,185,129,.15),rgba(5,150,105,.25));display:flex;align-items:center;justify-content:center;color:var(--brand);flex-shrink:0;">
                                            <i class="ri-team-line"></i>
                                        </div>
                                        <div style="display:flex;flex-direction:column;gap:.125rem;min-width:0;">
                                            <span style="font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" x-text="team.name"></span>
                                            <span style="font-size:.75rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" x-text="team.description || i18n.no_description"></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span :class="team.is_active ? 'badge badge-green' : 'badge badge-gray'">
                                        <i :class="team.is_active ? 'ri-checkbox-circle-line' : 'ri-pause-circle-line'"></i>
                                        <span x-text="team.is_active ? i18n.active : i18n.inactive"></span>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-purple">
                                        <i class="ri-user-line"></i>
                                        <span x-text="team.users_count ?? 0"></span>
                                    </span>
                                </td>
                                <td x-text="team.active_conversations_count ?? 0"></td>
                                <td x-text="team.pool_count ?? 0"></td>
                                <td x-text="team.closed_count ?? 0"></td>
                                <td>
                                    <div style="display:flex;gap:.25rem;justify-content:flex-end;">
                                        <a :href="editUrl(team.id)" class="action-btn" title="{{ __('ui.teams_page.manage') }}">
                                            <i class="ri-pencil-line"></i>
                                        </a>
                                        <a :href="conversationsUrl(team.id)" class="action-btn" title="{{ __('ui.teams_page.view_conversations') }}">
                                            <i class="ri-message-3-line"></i>
                                        </a>
                                        @if($canManageTeams)
                                            <button type="button"
                                                    @click="openDeleteModal(team.id, team.name)"
                                                    class="action-btn danger"
                                                    title="{{ __('ui.teams_page.delete') }}">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div x-show="lastPage > 1" style="padding:12px 16px;display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--card-border);flex-wrap:wrap;gap:8px;">
                <div style="font-size:12px;color:var(--text-muted);" x-text="total + ' {{ __('ui.total_records') }}'"></div>
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
    @if($canManageTeams)
        <div class="bulk-bar" :class="selected.length > 0 ? 'visible' : ''">
            <span class="bulk-count" x-text="selected.length + ' ' + i18n.selected_items"></span>
            <span class="bulk-sep">|</span>
            <div class="bulk-actions" style="display:flex;gap:.5rem;align-items:center;">
                <select x-model="bulkAction" class="form-control" style="min-width:170px;">
                    <option value="enable">{{ __('ui.teams_page.enable') }}</option>
                    <option value="disable">{{ __('ui.teams_page.disable') }}</option>
                    <option value="delete">{{ __('ui.teams_page.delete') }}</option>
                </select>
                <button type="button" @click="submitBulk()" :disabled="bulkSaving" class="btn btn-primary btn-sm">
                    <span x-show="!bulkSaving">{{ __('ui.teams_page.apply') }}</span>
                    <span x-show="bulkSaving"><span class="btn-spinner"></span></span>
                </button>
            </div>
            <button type="button" class="bulk-close" @click="selected = []"><i class="ri-close-line"></i></button>
        </div>
    @endif

    {{-- Delete Modal --}}
    <div class="modal-overlay" :class="deleteModal.show ? 'show' : ''" role="dialog" aria-modal="true"
         @click.self="deleteModal.show = false" @keydown.escape.window="deleteModal.show = false">
        <div class="modal-box" style="max-width:420px">
            <div class="modal-icon danger"><i class="ri-delete-bin-line"></i></div>
            <h3 x-text="i18n.delete_team_prompt.replace(':name', deleteModal.name)"></h3>
            <p x-text="i18n.delete_team_message"></p>
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

</div>

<script>
function teamsPage() {
    return {
        i18n:                @json($i18n),
        indexUrl:            @json(route($panelPrefix . '.teams.index')),
        editUrlTpl:          @json(route($panelPrefix . '.teams.edit', ['team' => '__ID__'])),
        conversationsBaseUrl: @json(route($panelPrefix . '.conversations.index')),
        destroyUrlTpl:       @json($canManageTeams ? route($panelPrefix . '.teams.destroy', ['team' => '__ID__']) : null),
        bulkUrl:             @json($canManageTeams ? route($panelPrefix . '.teams.bulk') : null),

        teams:       [],
        stats:       { total: null, active: null, inactive: null, pool: null },
        loading:     true,
        page:        1,
        lastPage:    1,
        total:       0,

        search:     '',
        filters:    { is_active: '', sort: 'name_asc' },

        selected:   [],
        bulkAction: 'enable',
        bulkSaving: false,

        deleteModal: { show: false, id: null, name: '', saving: false },

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
            p.set('per_page', '18');
            p.set('page', String(this.page));
            if (this.search.trim()) p.set('search', this.search.trim());
            if (this.filters.is_active !== '') p.set('is_active', this.filters.is_active);
            if (this.filters.sort) p.set('sort', this.filters.sort);
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
                this.teams    = data.data || [];
                this.page     = data.current_page ?? 1;
                this.lastPage = data.last_page ?? 1;
                this.total    = data.total ?? 0;
                if (data.stats) this.stats = data.stats;
            } catch (e) {
                console.error('Teams fetch failed:', e);
                this.teams = [];
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
            this.filters = { is_active: '', sort: 'name_asc' };
            this.reload();
        },

        isSelected(id)    { return this.selected.includes(id); },
        toggleSelect(id)  {
            if (this.isSelected(id)) this.selected = this.selected.filter(s => s !== id);
            else this.selected.push(id);
        },
        toggleAll() {
            this.selected = this.isAllSelected() ? [] : this.teams.map(t => t.id);
        },
        isAllSelected()   { return this.teams.length > 0 && this.selected.length === this.teams.length; },
        isIndeterminate() { return this.selected.length > 0 && this.selected.length < this.teams.length; },

        openDeleteModal(id, name) {
            this.deleteModal = { show: true, id, name, saving: false };
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
            if (!this.selected.length || !this.bulkUrl) return;
            if (this.bulkAction === 'delete') {
                if (!confirm(this.i18n.delete_selected_prompt)) return;
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

        editUrl(id)          { return this.editUrlTpl.replace('__ID__', String(id)); },
        conversationsUrl(id) { return this.conversationsBaseUrl + '?team_id=' + id; },
    };
}
</script>
@endsection
