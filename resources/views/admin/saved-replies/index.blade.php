@extends('layouts.admin')

@section('title', __('ui.saved_replies_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.saved_replies_page.breadcrumb') }}</span>
@endsection

@section('content')
@php $panelPrefix = auth()->user()->routeNamePrefix(); @endphp

<div x-data="savedRepliesPage()" x-init="init()" x-cloak>

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.saved_replies_page.title') }}</div>
            <div class="page-subtitle">{{ __('ui.saved_replies_page.subtitle') }}</div>
        </div>
        <div class="page-header-actions">
            <button type="button" @click="openCreate()" class="btn btn-primary btn-sm">
                <i class="ri-add-line"></i> {{ __('ui.saved_replies_page.add_reply') }}
            </button>
        </div>
    </div>

    {{-- Stats --}}
    <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-chat-3-line"></i></div>
            <div class="stat-card-value" x-text="tenantReplies.length"></div>
            <div class="stat-card-label">{{ __('ui.saved_replies_page.tenant_replies') }}</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-card-icon"><i class="ri-user-line"></i></div>
            <div class="stat-card-value" x-text="personalReplies.length"></div>
            <div class="stat-card-label">{{ __('ui.saved_replies_page.personal_replies') }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-hashtag"></i></div>
            <div class="stat-card-value" x-text="shortcuts.length"></div>
            <div class="stat-card-label">{{ __('ui.saved_replies_page.with_shortcuts') }}</div>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="table-toolbar" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-lg);margin-bottom:1rem">
        <div class="filter-input-wrap">
            <i class="ri-search-line"></i>
            <input type="text" x-model="search" @input.debounce.200ms="filterReplies()"
                   placeholder="{{ __('ui.saved_replies_page.search_placeholder') }}" class="filter-input">
        </div>
        <select x-model="scopeFilter" @change="filterReplies()" class="toolbar-select">
            <option value="">{{ __('ui.saved_replies_page.all_scopes') }}</option>
            <option value="tenant">{{ __('ui.saved_replies_page.scope_tenant') }}</option>
            <option value="personal">{{ __('ui.saved_replies_page.scope_personal') }}</option>
        </select>
        <button type="button" @click="search='';scopeFilter='';filterReplies()" class="btn btn-ghost btn-sm">
            {{ __('ui.customers_page.clear') }}
        </button>
    </div>

    {{-- Table --}}
    <div class="card" style="padding:0">
        <div x-show="loading" class="spinner-wrap" style="min-height:160px">
            <div>
                <div class="spinner" style="margin:0 auto 1rem"></div>
                <div style="color:var(--text-muted);font-size:.875rem;text-align:center">{{ __('ui.conversations_page.loading') }}</div>
            </div>
        </div>

        <div x-show="!loading && filtered.length === 0" class="empty-state" style="padding:3rem">
            <div class="empty-state-icon"><i class="ri-chat-3-line"></i></div>
            <h4>{{ __('ui.saved_replies_page.no_replies') }}</h4>
            <p>{{ __('ui.saved_replies_page.no_replies_hint') }}</p>
            <button type="button" @click="openCreate()" class="btn btn-primary btn-sm" style="margin-top:.75rem">
                <i class="ri-add-line"></i> {{ __('ui.saved_replies_page.add_reply') }}
            </button>
        </div>

        <div x-show="!loading && filtered.length > 0">
            <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>{{ __('ui.saved_replies_page.col_title') }}</th>
                            <th>{{ __('ui.saved_replies_page.col_shortcut') }}</th>
                            <th>{{ __('ui.saved_replies_page.col_body') }}</th>
                            <th>{{ __('ui.saved_replies_page.col_scope') }}</th>
                            <th style="width:88px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="reply in filtered" :key="reply.id">
                            <tr>
                                <td style="color:var(--text-muted);font-size:.8125rem;" x-text="reply.sort_order || '—'"></td>
                                <td>
                                    <span style="font-weight:600;font-size:.875rem;color:var(--text-primary);" x-text="reply.title"></span>
                                </td>
                                <td>
                                    <template x-if="reply.shortcut">
                                        <span style="display:inline-flex;align-items:center;padding:.2rem .55rem;background:rgba(139,92,246,.1);border:1px solid rgba(139,92,246,.2);border-radius:.375rem;font-size:.8rem;font-weight:600;color:#8b5cf6;font-family:monospace;"
                                              x-text="reply.shortcut"></span>
                                    </template>
                                    <template x-if="!reply.shortcut">
                                        <span style="color:var(--text-muted);font-size:.8125rem;">—</span>
                                    </template>
                                </td>
                                <td style="max-width:340px;">
                                    <span style="font-size:.8125rem;color:var(--text-secondary);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"
                                          x-text="reply.body"></span>
                                </td>
                                <td>
                                    <template x-if="reply.scope === 'tenant'">
                                        <span class="badge badge-green">
                                            <i class="ri-building-2-line"></i>
                                            {{ __('ui.saved_replies_page.scope_tenant') }}
                                        </span>
                                    </template>
                                    <template x-if="reply.scope === 'personal'">
                                        <span class="badge badge-blue">
                                            <i class="ri-user-line"></i>
                                            {{ __('ui.saved_replies_page.scope_personal') }}
                                        </span>
                                    </template>
                                </td>
                                <td>
                                    <div style="display:flex;gap:.25rem;justify-content:flex-end;">
                                        <button type="button" @click="openEdit(reply)" class="action-btn" title="{{ __('ui.edit') }}">
                                            <i class="ri-pencil-line"></i>
                                        </button>
                                        <button type="button" @click="confirmDelete(reply)" class="action-btn danger" title="{{ __('ui.delete') }}">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── Add / Edit Modal ──────────────────────────────────────────────────── --}}
    <div x-show="showModal" x-transition.opacity
         style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;display:flex;align-items:center;justify-content:center;padding:1rem;">
        <div @click.stop
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             style="background:var(--card-bg);border-radius:1rem;width:100%;max-width:560px;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;">

            {{-- Modal header --}}
            <div style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--card-border);display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <div style="font-weight:700;font-size:.9375rem;color:var(--text-primary);"
                         x-text="editId ? '{{ __('ui.saved_replies_page.edit_reply') }}' : '{{ __('ui.saved_replies_page.add_reply') }}'"></div>
                    <div style="font-size:.8125rem;color:var(--text-muted);margin-top:.125rem;">{{ __('ui.saved_replies_page.modal_subtitle') }}</div>
                </div>
                <button type="button" @click="closeModal()"
                        style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:1.25rem;padding:.25rem;display:flex;align-items:center;border-radius:.5rem;transition:background .1s;"
                        onmouseenter="this.style.background='var(--page-bg)'" onmouseleave="this.style.background='none'">
                    <i class="ri-close-line"></i>
                </button>
            </div>

            {{-- Modal body --}}
            <div style="padding:1.5rem;display:flex;flex-direction:column;gap:1rem;">

                {{-- Title --}}
                <div class="form-group">
                    <label class="form-label">{{ __('ui.saved_replies_page.field_title') }} <span style="color:#ef4444">*</span></label>
                    <input type="text" x-model="form.title" class="form-control"
                           placeholder="{{ __('ui.saved_replies_page.field_title_placeholder') }}" maxlength="120">
                </div>

                {{-- Shortcut --}}
                <div class="form-group">
                    <label class="form-label">{{ __('ui.saved_replies_page.field_shortcut') }}</label>
                    <div style="position:relative;">
                        <span style="position:absolute;left:.875rem;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.875rem;pointer-events:none;font-family:monospace;">/</span>
                        <input type="text" x-model="form.shortcut" class="form-control" style="padding-left:1.75rem;font-family:monospace;"
                               placeholder="{{ __('ui.saved_replies_page.field_shortcut_placeholder') }}" maxlength="32"
                               @input="form.shortcut = form.shortcut.replace(/[^a-z0-9_-]/g, '').replace(/^\//, '')">
                    </div>
                    <div class="form-hint">{{ __('ui.saved_replies_page.field_shortcut_hint') }}</div>
                </div>

                {{-- Body --}}
                <div class="form-group">
                    <label class="form-label">{{ __('ui.saved_replies_page.field_body') }} <span style="color:#ef4444">*</span></label>
                    <textarea x-model="form.body" rows="4" class="form-control"
                              placeholder="{{ __('ui.saved_replies_page.field_body_placeholder') }}" maxlength="4000"
                              style="resize:vertical;"></textarea>
                    <div style="display:flex;justify-content:space-between;margin-top:.25rem;">
                        <div class="form-hint">{{ __('ui.saved_replies_page.field_body_hint') }}</div>
                        <span style="font-size:.75rem;color:var(--text-muted);" x-text="(form.body || '').length + '/4000'"></span>
                    </div>
                </div>

                {{-- Scope + Sort --}}
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.saved_replies_page.field_scope') }}</label>
                        <select x-model="form.scope" class="form-control">
                            <option value="tenant">{{ __('ui.saved_replies_page.scope_tenant_label') }}</option>
                            <option value="personal">{{ __('ui.saved_replies_page.scope_personal_label') }}</option>
                        </select>
                        <div class="form-hint" x-show="form.scope === 'tenant'">{{ __('ui.saved_replies_page.scope_tenant_hint') }}</div>
                        <div class="form-hint" x-show="form.scope === 'personal'">{{ __('ui.saved_replies_page.scope_personal_hint') }}</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.saved_replies_page.field_sort') }}</label>
                        <input type="number" x-model.number="form.sort_order" min="0" max="9999" class="form-control" placeholder="0">
                        <div class="form-hint">{{ __('ui.saved_replies_page.field_sort_hint') }}</div>
                    </div>
                </div>

                {{-- Error --}}
                <div x-show="modalError"
                     style="padding:.75rem 1rem;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);border-radius:.625rem;font-size:.8125rem;color:#ef4444;display:flex;gap:.5rem;align-items:center;">
                    <i class="ri-error-warning-line"></i>
                    <span x-text="modalError"></span>
                </div>

            </div>

            {{-- Modal footer --}}
            <div style="padding:1rem 1.5rem;border-top:1px solid var(--card-border);display:flex;justify-content:flex-end;gap:.5rem;background:var(--page-bg);">
                <button type="button" @click="closeModal()" class="btn btn-outline btn-sm">{{ __('ui.cancel') }}</button>
                <button type="button" @click="saveReply()" :disabled="saving"
                        class="btn btn-primary btn-sm">
                    <template x-if="!saving">
                        <span><i class="ri-save-3-line"></i> {{ __('ui.save') }}</span>
                    </template>
                    <template x-if="saving">
                        <span style="display:flex;align-items:center;gap:.375rem;">
                            <div class="spinner" style="width:.875rem;height:.875rem;border-width:2px;"></div>
                            {{ __('ui.processing') }}
                        </span>
                    </template>
                </button>
            </div>
        </div>
    </div>

    {{-- ── Delete Confirm Modal ──────────────────────────────────────────────── --}}
    <div x-show="deleteTarget" x-transition.opacity
         style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;display:flex;align-items:center;justify-content:center;padding:1rem;">
        <div @click.stop style="background:var(--card-bg);border-radius:1rem;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;">
            <div style="padding:1.5rem;text-align:center;">
                <div style="width:3rem;height:3rem;border-radius:50%;background:rgba(239,68,68,.1);display:flex;align-items:center;justify-content:center;margin:0 auto .875rem;font-size:1.375rem;color:#ef4444;">
                    <i class="ri-delete-bin-line"></i>
                </div>
                <div style="font-weight:700;font-size:.9375rem;color:var(--text-primary);margin-bottom:.375rem;">
                    {{ __('ui.saved_replies_page.delete_confirm_title') }}
                </div>
                <div style="font-size:.8125rem;color:var(--text-muted);margin-bottom:1.25rem;">
                    {{ __('ui.saved_replies_page.delete_confirm_body') }}
                    <strong style="color:var(--text-primary);" x-text="deleteTarget?.title"></strong>?
                </div>
                <div style="display:flex;gap:.5rem;justify-content:center;">
                    <button type="button" @click="deleteTarget=null" class="btn btn-outline btn-sm">{{ __('ui.cancel') }}</button>
                    <button type="button" @click="doDelete()" :disabled="saving" class="btn btn-sm"
                            style="background:#ef4444;color:#fff;border-color:#ef4444;">
                        <i class="ri-delete-bin-line"></i> {{ __('ui.delete') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
function savedRepliesPage() {
    return {
        replies:     [],
        filtered:    [],
        loading:     true,
        search:      '',
        scopeFilter: '',
        showModal:   false,
        editId:      null,
        saving:      false,
        modalError:  null,
        deleteTarget: null,

        form: { title: '', shortcut: '', body: '', scope: 'tenant', sort_order: 0 },

        get tenantReplies()   { return this.replies.filter(r => r.scope === 'tenant'); },
        get personalReplies() { return this.replies.filter(r => r.scope === 'personal'); },
        get shortcuts()       { return this.replies.filter(r => r.shortcut); },

        async init() {
            await this.load();
        },

        async load() {
            this.loading = true;
            try {
                const res = await fetch('/api/saved-replies', {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                this.replies = data.data || [];
                this.filterReplies();
            } catch(e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        },

        filterReplies() {
            let list = this.replies;
            const q = this.search.trim().toLowerCase();
            if (q) list = list.filter(r =>
                r.title.toLowerCase().includes(q)
                || (r.body || '').toLowerCase().includes(q)
                || (r.shortcut || '').toLowerCase().includes(q)
            );
            if (this.scopeFilter) list = list.filter(r => r.scope === this.scopeFilter);
            this.filtered = list;
        },

        openCreate() {
            this.editId     = null;
            this.form       = { title: '', shortcut: '', body: '', scope: 'tenant', sort_order: 0 };
            this.modalError = null;
            this.showModal  = true;
        },

        openEdit(reply) {
            this.editId     = reply.id;
            this.form       = {
                title:      reply.title,
                shortcut:   (reply.shortcut || '').replace(/^\//, ''),
                body:       reply.body,
                scope:      reply.scope,
                sort_order: reply.sort_order || 0,
            };
            this.modalError = null;
            this.showModal  = true;
        },

        closeModal() {
            this.showModal = false;
            this.editId    = null;
            this.modalError= null;
        },

        async saveReply() {
            if (!this.form.title.trim()) { this.modalError = '{{ __('ui.saved_replies_page.error_title_required') }}'; return; }
            if (!this.form.body.trim())  { this.modalError = '{{ __('ui.saved_replies_page.error_body_required') }}'; return; }

            this.saving = true;
            this.modalError = null;

            const payload = {
                title:      this.form.title.trim(),
                shortcut:   this.form.shortcut.trim() ? '/' + this.form.shortcut.trim().replace(/^\//, '') : null,
                body:       this.form.body.trim(),
                scope:      this.form.scope,
                sort_order: this.form.sort_order || 0,
            };

            try {
                const url    = this.editId ? `/api/saved-replies/${this.editId}` : '/api/saved-replies';
                const method = this.editId ? 'PUT' : 'POST';
                const res    = await fetch(url, {
                    method,
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (!res.ok) {
                    const first = Object.values(data.errors || {})[0];
                    this.modalError = (Array.isArray(first) ? first[0] : first) || data.message || '{{ __('ui.saved_replies_page.save_error') }}';
                    return;
                }
                this.closeModal();
                await this.load();
            } catch {
                this.modalError = '{{ __('ui.saved_replies_page.save_error') }}';
            } finally {
                this.saving = false;
            }
        },

        confirmDelete(reply) {
            this.deleteTarget = reply;
        },

        async doDelete() {
            if (!this.deleteTarget) return;
            this.saving = true;
            try {
                await fetch(`/api/saved-replies/${this.deleteTarget.id}`, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
                });
                this.deleteTarget = null;
                await this.load();
            } finally {
                this.saving = false;
            }
        },
    };
}

function csrf() {
    return document.querySelector('meta[name=csrf-token]').content;
}
</script>
@endpush
@endsection
