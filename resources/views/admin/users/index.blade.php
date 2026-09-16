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
        'archive'                => __('ui.users_page.archive'),
        'restore'                => __('ui.users_page.restore'),
        'archive_user_prompt'    => __('ui.users_page.archive_user_prompt'),
        'restore_user_prompt'    => __('ui.users_page.restore_user_prompt'),
        'archive_user_message'   => __('ui.users_page.archive_user_message'),
        'restore_user_message'   => __('ui.users_page.restore_user_message'),
        'archived_toast'         => __('ui.controller_messages.user_archived'),
        'unarchived_toast'       => __('ui.controller_messages.user_unarchived'),
        'selected_items'         => __('ui.selected_items'),
        'loading'                => __('ui.conversations_page.loading'),
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
        {{-- Archive filter — default hides archived rows. --}}
        <select x-model="filters.archived" @change="reload()" class="toolbar-select">
            <option value="">{{ __('ui.users_page.archive_hide') }}</option>
            <option value="1">{{ __('ui.users_page.archive_only') }}</option>
            <option value="all">{{ __('ui.users_page.archive_all') }}</option>
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
                                <input type="checkbox" class="header-cb"
                                       :checked="isAllSelected()"
                                       :indeterminate.prop="isIndeterminate()"
                                       @change="toggleAll()">
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
                                    <input type="checkbox" class="row-cb"
                                           :checked="isSelected(user.id)"
                                           @change="toggleSelect(user.id)">
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
                                        {{-- Archive / restore. Same icon set as the tenants list uses,
                                             so the two features look like siblings across the app. --}}
                                        <button type="button" @click="openArchiveModal(user.id, user.name, !!user.archived_at)"
                                                class="action-btn"
                                                :title="!!user.archived_at ? i18n.restore : i18n.archive">
                                            <i :class="!!user.archived_at ? 'ri-inbox-unarchive-line' : 'ri-inbox-archive-line'"></i>
                                        </button>
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

    {{-- Archive / Restore User Modal — one modal handles both directions
         based on archiveModal.currentlyArchived captured at open time. --}}
    <div class="modal-overlay" :class="archiveModal.show ? 'show' : ''" role="dialog" aria-modal="true"
         @click.self="archiveModal.show = false" @keydown.escape.window="archiveModal.show = false">
        <div class="modal-box" style="max-width:460px">
            <div class="modal-icon" :class="archiveModal.currentlyArchived ? '' : 'danger'"
                 :style="archiveModal.currentlyArchived ? 'color:var(--brand);background:rgba(16,185,129,.15)' : ''">
                <i :class="archiveModal.currentlyArchived ? 'ri-inbox-unarchive-line' : 'ri-inbox-archive-line'"></i>
            </div>
            <h3 x-text="(archiveModal.currentlyArchived ? i18n.restore_user_prompt : i18n.archive_user_prompt).replace(':name', archiveModal.name)"></h3>
            <p x-text="archiveModal.currentlyArchived ? i18n.restore_user_message : i18n.archive_user_message"></p>
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
        toggleArchiveUrlTpl:@json(route($panelPrefix . '.users.toggle-archive', ['user' => '__ID__'])),
        bulkUrl:           @json(route($panelPrefix . '.users.bulk')),

        users:       [],
        loading:     true,
        page:        1,
        lastPage:    1,
        total:       0,

        search:     '',
        filters:    { role: '', status: '', archived: '' },

        selected:   [],
        bulkAction: 'activate',
        bulkSaving: false,

        deleteModal:  { show: false, id: null, name: '', saving: false },
        archiveModal: { show: false, id: null, name: '', currentlyArchived: false, saving: false },

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
            if (this.search.trim())  p.set('search', this.search.trim());
            if (this.filters.role)     p.set('role',     this.filters.role);
            if (this.filters.status)   p.set('status',   this.filters.status);
            if (this.filters.archived) p.set('archived', this.filters.archived);
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
                this.users    = data.data || [];
                this.page     = data.current_page ?? 1;
                this.lastPage = data.last_page ?? 1;
                this.total    = data.total ?? 0;
            } catch (e) {
                console.error('Users fetch failed:', e);
                this.users = [];
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
            this.filters = { role: '', status: '', archived: '' };
            this.reload();
        },

        hasFilters() {
            return this.search.trim() !== '' || this.filters.role !== '' || this.filters.status !== '' || this.filters.archived !== '';
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

        openArchiveModal(id, name, currentlyArchived) {
            this.archiveModal = { show: true, id, name, currentlyArchived: !!currentlyArchived, saving: false };
        },

        async confirmArchiveToggle() {
            this.archiveModal.saving = true;
            try {
                const res = await fetch(this.toggleArchiveUrlTpl.replace('__ID__', String(this.archiveModal.id)), {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: {
                        'Accept':       'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                });
                if (!res.ok) throw new Error();
                const wasArchived = this.archiveModal.currentlyArchived;
                const name = this.archiveModal.name;
                this.archiveModal.show = false;
                window.showToast?.('success',
                    (wasArchived ? this.i18n.unarchived_toast : this.i18n.archived_toast).replace(':name', name));
                this.reload();
            } catch {
                window.showToast?.('error', 'Erreur.');
                this.archiveModal.saving = false;
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
