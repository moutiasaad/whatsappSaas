@extends('layouts.admin')

@section('title', __('ui.users_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.users_page.breadcrumb') }}</span>
@endsection

@section('content')
@php
    $panelPrefix = auth()->user()->routeNamePrefix();
    $actor       = auth()->user();
    $i18n = [
        'never'                  => __('ui.users_page.never'),
        'active'                 => __('ui.users_page.active'),
        'inactive'               => __('ui.users_page.inactive'),
        'no_users_found'         => __('ui.users_page.no_users_found'),
        'try_adjusting'          => __('ui.users_page.try_adjusting'),
        'invite_first_member'    => __('ui.users_page.invite_first_member'),
        'impersonate'            => __('ui.users_page.impersonate'),
        'impersonate_confirm'    => __('ui.users_page.impersonate_confirm'),
        'delete_user_prompt'     => __('ui.users_page.delete_user_prompt'),
        'delete_user_message'    => __('ui.users_page.delete_user_message'),
        'delete_selected_confirm'=> __('ui.users_page.delete_selected_confirm'),
        'deleted_toast'          => __('ui.controller_messages.user_deleted'),
        'selected_items'         => __('ui.selected_items'),
        'loading'                => __('ui.conversations_page.loading'),
        'load_more'              => __('ui.conversations_page.load_more'),
        'loading_more'           => __('ui.conversations_page.loading_more'),
        'roles'                  => __('ui.roles'),
        'just_now'               => __('ui.conversations_page.just_now'),
        'minutes_ago'            => __('ui.conversations_page.minutes_ago'),
        'hours_ago'              => __('ui.conversations_page.hours_ago'),
        'days_ago'               => __('ui.conversations_page.days_ago'),
    ];
@endphp

<div x-data="usersPage()" x-init="init()" x-cloak>

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.users_page.title') }}</div>
            <div class="page-subtitle">{{ __('ui.users_page.subtitle') }}</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route($panelPrefix . '.users.create') }}" class="btn btn-primary">
                <i class="ri-user-add-line"></i> {{ __('ui.users_page.invite_user') }}
            </a>
        </div>
    </div>

    <div class="table-toolbar" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-lg);margin-bottom:1rem">
        <div class="filter-input-wrap">
            <i class="ri-search-line"></i>
            <input type="text" x-model="search" @input.debounce.350ms="reload()"
                   placeholder="{{ __('ui.users_page.search_placeholder') }}" class="filter-input">
        </div>
        <select x-model="filters.role" @change="reload()" class="toolbar-select">
            <option value="">{{ __('ui.users_page.all_roles') }}</option>
            <option value="admin">{{ __('ui.roles.admin') }}</option>
            <option value="supervisor">{{ __('ui.roles.supervisor') }}</option>
            <option value="agent">{{ __('ui.roles.agent') }}</option>
        </select>
        <select x-model="filters.status" @change="reload()" class="toolbar-select">
            <option value="">{{ __('ui.users_page.all_status') }}</option>
            <option value="active">{{ __('ui.users_page.active') }}</option>
            <option value="inactive">{{ __('ui.users_page.inactive') }}</option>
        </select>
        <button type="button" @click="clearFilters()" class="btn btn-ghost btn-sm">{{ __('ui.users_page.clear') }}</button>
    </div>

    {{-- Loading --}}
    <div x-show="loading" class="spinner-wrap" style="min-height:200px">
        <div>
            <div class="spinner" style="margin:0 auto 1rem"></div>
            <div style="color:var(--text-muted);font-size:.875rem;text-align:center" x-text="i18n.loading"></div>
        </div>
    </div>

    <div class="card" style="padding:0" x-show="!loading">

        <div x-show="users.length === 0" class="empty-state">
            <div class="empty-state-icon"><i class="ri-team-line"></i></div>
            <h4 x-text="i18n.no_users_found"></h4>
            <p x-text="hasFilters() ? i18n.try_adjusting : i18n.invite_first_member"></p>
            <template x-if="!hasFilters()">
                <a href="{{ route($panelPrefix . '.users.create') }}" class="btn btn-primary">{{ __('ui.users_page.invite_user') }}</a>
            </template>
        </div>

        <div x-show="users.length > 0">
            <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:2.5rem">
                                <input type="checkbox"
                                       :checked="isAllSelected()"
                                       :indeterminate.prop="isIndeterminate()"
                                       @change="toggleAll()"
                                       style="cursor:pointer;accent-color:var(--brand)">
                            </th>
                            <th>{{ __('ui.users_page.user') }}</th>
                            <th>{{ __('ui.users_page.role') }}</th>
                            <th>{{ __('ui.users_page.teams') }}</th>
                            <th>{{ __('ui.users_page.status') }}</th>
                            <th>{{ __('ui.users_page.last_login') }}</th>
                            <th style="width:100px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="user in users" :key="user.id">
                            <tr>
                                <td>
                                    <input type="checkbox"
                                           :checked="isSelected(user.id)"
                                           @change="toggleSelect(user.id)"
                                           style="cursor:pointer;accent-color:var(--brand)">
                                </td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:.75rem">
                                        <img :src="user.avatar_url" :alt="user.name"
                                             style="width:2rem;height:2rem;border-radius:50%;object-fit:cover">
                                        <div>
                                            <div style="font-weight:500;font-size:.875rem" x-text="user.name"></div>
                                            <div style="font-size:.75rem;color:var(--text-muted)" x-text="user.email"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span :class="roleBadge(user.role)" x-text="roleLabel(user.role)"></span>
                                </td>
                                <td>
                                    <div style="display:flex;flex-wrap:wrap;gap:.25rem">
                                        <template x-for="team in (user.teams || []).slice(0, 3)" :key="team.id">
                                            <span class="badge badge-gray" style="font-size:.6875rem" x-text="team.name"></span>
                                        </template>
                                        <template x-if="user.teams && user.teams.length > 3">
                                            <span class="badge badge-gray" style="font-size:.6875rem" x-text="'+' + (user.teams.length - 3)"></span>
                                        </template>
                                        <template x-if="!user.teams || user.teams.length === 0">
                                            <span style="color:var(--text-muted);font-size:.8125rem">—</span>
                                        </template>
                                    </div>
                                </td>
                                <td>
                                    <span :class="user.is_active ? 'badge badge-green' : 'badge badge-gray'"
                                          x-text="user.is_active ? i18n.active : i18n.inactive"></span>
                                </td>
                                <td>
                                    <span style="font-size:.8125rem;color:var(--text-muted)" x-text="lastLogin(user)"></span>
                                </td>
                                <td>
                                    <div style="display:flex;gap:.25rem">
                                        <a :href="editUrl(user.id)" class="action-btn" title="{{ __('ui.users_page.edit') }}">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <template x-if="canImpersonate(user)">
                                            <a :href="impersonateUrl(user.id)" class="action-btn"
                                               title="{{ __('ui.users_page.impersonate') }}"
                                               @click.prevent="openImpersonateConfirm(user)">
                                                <i class="ri-user-shared-line"></i>
                                            </a>
                                        </template>
                                        <button type="button" @click="openDeleteModal(user.id, user.name)"
                                                class="action-btn danger" title="{{ __('ui.users_page.delete') }}">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div x-show="hasMore" style="padding:1rem;text-align:center;border-top:1px solid var(--card-border)">
                <button @click="loadMore()" :disabled="loadingMore" class="btn btn-outline btn-sm">
                    <template x-if="!loadingMore">
                        <span><i class="ri-arrow-down-line"></i> <span x-text="i18n.load_more"></span></span>
                    </template>
                    <template x-if="loadingMore">
                        <span><span class="btn-spinner"></span> <span x-text="i18n.loading_more"></span></span>
                    </template>
                </button>
            </div>
        </div>
    </div>

    {{-- Bulk Bar --}}
    <div class="bulk-bar" :class="selected.length > 0 ? 'visible' : ''">
        <span class="bulk-count" x-text="selected.length + ' ' + i18n.selected_items"></span>
        <span class="bulk-sep">|</span>
        <div class="bulk-actions" style="display:flex;gap:.5rem;align-items:center;">
            <select x-model="bulkAction" class="form-control" style="min-width:170px;">
                <option value="activate">{{ __('ui.users_page.activate') }}</option>
                <option value="deactivate">{{ __('ui.users_page.deactivate') }}</option>
                <option value="delete">{{ __('ui.users_page.delete') }}</option>
            </select>
            <button type="button" @click="submitBulk()" :disabled="bulkSaving" class="btn btn-primary btn-sm">
                <span x-show="!bulkSaving">{{ __('ui.users_page.apply') }}</span>
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
            <h3 x-text="i18n.delete_user_prompt.replace(':name', deleteModal.name)"></h3>
            <p x-text="i18n.delete_user_message"></p>
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
function usersPage() {
    return {
        i18n:              @json($i18n),
        actorRole:         @json($actor->role),
        indexUrl:          @json(route($panelPrefix . '.users.index')),
        editUrlTpl:        @json(route($panelPrefix . '.users.edit',        ['user' => '__ID__'])),
        destroyUrlTpl:     @json(route($panelPrefix . '.users.destroy',     ['user' => '__ID__'])),
        impersonateUrlTpl: @json(route($panelPrefix . '.users.impersonate', ['user' => '__ID__'])),
        bulkUrl:           @json(route($panelPrefix . '.users.bulk')),

        users:       [],
        loading:     true,
        loadingMore: false,
        hasMore:     false,
        page:        1,

        search:     '',
        filters:    { role: '', status: '' },

        selected:   [],
        bulkAction: 'activate',
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
            this.users    = [];
            this.selected = [];
            this.loadData();
        },

        buildParams() {
            const p = new URLSearchParams();
            p.set('per_page', '20');
            p.set('page', String(this.page));
            if (this.search.trim())  p.set('search', this.search.trim());
            if (this.filters.role)   p.set('role',   this.filters.role);
            if (this.filters.status) p.set('status', this.filters.status);
            return p;
        },

        hasFilters() {
            return this.search.trim() !== '' || this.filters.role !== '' || this.filters.status !== '';
        },

        async loadData(append = false) {
            if (!append) { this.loading = true; this.page = 1; }
            else         { this.loadingMore = true; this.page++; }
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
                this.users   = append ? [...this.users, ...(data.data || [])] : (data.data || []);
                this.hasMore = (data.current_page ?? 1) < (data.last_page ?? 1);
            } catch (e) {
                console.error('Users fetch failed:', e);
                if (!append) this.users = [];
            } finally {
                this.loading     = false;
                this.loadingMore = false;
            }
        },

        loadMore() { this.loadData(true); },

        clearFilters() {
            this.search  = '';
            this.filters = { role: '', status: '' };
            this.reload();
        },

        hasFilters() {
            return this.search.trim() !== '' || this.filters.role !== '' || this.filters.status !== '';
        },

        isSelected(id)    { return this.selected.includes(id); },
        toggleSelect(id)  {
            if (this.isSelected(id)) this.selected = this.selected.filter(s => s !== id);
            else this.selected.push(id);
        },
        toggleAll() {
            this.selected = this.isAllSelected() ? [] : this.users.map(u => u.id);
        },
        isAllSelected()   { return this.users.length > 0 && this.selected.length === this.users.length; },
        isIndeterminate() { return this.selected.length > 0 && this.selected.length < this.users.length; },

        canImpersonate(user) {
            if (this.actorRole === 'super_admin') return true;
            if (this.actorRole === 'admin') return user.role !== 'admin' && user.role !== 'super_admin';
            return false;
        },

        openImpersonateConfirm(user) {
            window.confirmSend?.({
                title:    this.i18n.impersonate,
                message:  this.i18n.impersonate_confirm.replace(':name', user.name),
                callback: () => { window.location.href = this.impersonateUrl(user.id); },
            });
        },

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

        roleBadge(role) {
            const map = { admin: 'badge-purple', supervisor: 'badge-blue', agent: 'badge-brand', super_admin: 'badge-red' };
            return 'badge ' + (map[role] || 'badge-gray');
        },

        roleLabel(role) {
            return (this.i18n.roles && this.i18n.roles[role]) || role;
        },

        lastLogin(user) {
            if (!user.last_login_at) return this.i18n.never;
            return this.timeAgo(user.last_login_at);
        },

        timeAgo(ts) {
            if (!ts) return '-';
            const diff = (Date.now() - new Date(ts)) / 1000;
            if (diff < 60)           return this.i18n.just_now;
            if (diff < 3600)         return `${Math.floor(diff / 60)}${this.i18n.minutes_ago}`;
            if (diff < 86400)        return `${Math.floor(diff / 3600)}${this.i18n.hours_ago}`;
            if (diff < 86400 * 30)   return `${Math.floor(diff / 86400)}${this.i18n.days_ago}`;
            return new Date(ts).toLocaleDateString();
        },

        editUrl(id)        { return this.editUrlTpl.replace('__ID__', String(id)); },
        impersonateUrl(id) { return this.impersonateUrlTpl.replace('__ID__', String(id)); },
    };
}
</script>
@endsection
