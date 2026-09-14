@extends('layouts.admin')

@section('title', __('ui.saved_replies_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.saved_replies_page.breadcrumb') }}</span>
@endsection

@section('content')
@php
    $panelPrefix = auth()->user()->routeNamePrefix();

    // Agents own their personal replies only. Team replies belong to whoever
    // runs the workspace, which mirrors what Api\SavedReplyController enforces.
    $canManageTeam = auth()->user()->isAdmin()
        || auth()->user()->isSupervisor()
        || auth()->user()->isSuperAdmin();

    $i18n = [
        'errTitle'   => __('ui.saved_replies_page.error_title_required'),
        'errBody'    => __('ui.saved_replies_page.error_body_required'),
        'saveError'  => __('ui.saved_replies_page.save_error'),
        'addTitle'   => __('ui.saved_replies_page.add_reply'),
        'editTitle'  => __('ui.saved_replies_page.edit_reply'),
        'scopeTeam'  => __('ui.saved_replies_page.scope_tenant'),
        'scopeSelf'  => __('ui.saved_replies_page.scope_personal'),
        'none'       => __('ui.saved_replies_page.none'),
        'secAll'      => __('ui.saved_replies_page.sec_all_desc'),
        'secTeam'     => __('ui.saved_replies_page.sec_team_desc'),
        'secPersonal' => __('ui.saved_replies_page.sec_personal_desc'),
        'secShortcut' => __('ui.saved_replies_page.sec_shortcut_desc'),
        'ttlAll'      => __('ui.saved_replies_page.nav_all'),
        'ttlTeam'     => __('ui.saved_replies_page.nav_team'),
        'ttlPersonal' => __('ui.saved_replies_page.nav_personal'),
        'ttlShortcut' => __('ui.saved_replies_page.nav_shortcuts'),
    ];
@endphp

{{-- The console fills the viewport and manages its own scroll regions, so the
     shared .page-content padding/height is neutralised for this route. --}}
<script>document.body.classList.add('wc-host');</script>

<div class="wv-console" x-data="savedRepliesConsole()" x-cloak>
    <div class="wc-shell">

        {{-- ══ PAGE HEAD ═══════════════════════════════════════════ --}}
        <div class="wc-phead">
            <div class="m">
                <h1>
                    {{ __('ui.saved_replies_page.title') }}
                    <span class="wc-pill" :class="replies.length ? 'on' : 'off'">
                        <i></i><span x-text="replies.length"></span>
                    </span>
                </h1>
                <p>{{ __('ui.saved_replies_page.subtitle') }}</p>
            </div>
            <div class="acts">
                <button type="button" class="wc-btn p" @click="openCreate()">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                    {{ __('ui.saved_replies_page.new_reply') }}
                </button>
            </div>
        </div>

        <div class="wc-body">

            {{-- ══ VIEW NAV ═════════════════════════════════════════ --}}
            <nav class="wc-snav">
                <div class="lbl">{{ __('ui.saved_replies_page.nav_views') }}</div>

                <button type="button" class="wc-sn" :class="view === 'all' ? 'on' : ''" @click="go('all')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="16" rx="2.6" stroke="currentColor" stroke-width="2"/><path d="M7 9h10M7 13h10M7 17h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <span>{{ __('ui.saved_replies_page.nav_all') }}</span>
                    <span class="n" x-text="replies.length"></span>
                </button>

                <button type="button" class="wc-sn" :class="view === 'tenant' ? 'on' : ''" @click="go('tenant')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M16 20v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="9" cy="7" r="3.4" stroke="currentColor" stroke-width="2"/><path d="M22 20v-2a4 4 0 00-3-3.9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <span>{{ __('ui.saved_replies_page.nav_team') }}</span>
                    <span class="n" x-text="countBy('tenant')"></span>
                </button>

                <button type="button" class="wc-sn" :class="view === 'personal' ? 'on' : ''" @click="go('personal')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.4" stroke="currentColor" stroke-width="2"/><path d="M5.5 20a6.5 6.5 0 0113 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <span>{{ __('ui.saved_replies_page.nav_personal') }}</span>
                    <span class="n" x-text="countBy('personal')"></span>
                </button>

                <button type="button" class="wc-sn" :class="view === 'shortcut' ? 'on' : ''" @click="go('shortcut')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M9 20l6-16M5 8h14M5 16h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <span>{{ __('ui.saved_replies_page.nav_shortcuts') }}</span>
                    <span class="n" x-text="countShortcuts()"></span>
                </button>
            </nav>

            {{-- ══ LIST ═════════════════════════════════════════════ --}}
            <div class="wc-form" x-ref="listScroll"><div class="wc-fwrap">

                <div class="wc-sechead">
                    <h2 x-text="viewTitle()"></h2>
                    <p x-text="viewDesc()"></p>
                </div>

                <div class="wc-toolbar">
                    <div class="wc-search">
                        <i class="ri-search-line"></i>
                        <input class="wc-inp" type="search" x-model="search"
                               placeholder="{{ __('ui.saved_replies_page.search_placeholder') }}">
                    </div>
                    <button type="button" class="wc-btn g sm" @click="search = ''" x-show="search">
                        {{ __('ui.saved_replies_page.clear') }}
                    </button>
                </div>

                {{-- Loading --}}
                <div class="wc-blank" x-show="loading">
                    <div class="wc-spin"></div>
                    <div class="t">{{ __('ui.saved_replies_page.loading') }}</div>
                </div>

                {{-- Nothing at all --}}
                <div class="wc-blank" x-show="!loading && replies.length === 0">
                    <div class="ico"><i class="ri-chat-quote-line" style="font-size:26px"></i></div>
                    <div class="t">{{ __('ui.saved_replies_page.no_replies') }}</div>
                    <div class="s">{{ __('ui.saved_replies_page.no_replies_hint') }}</div>
                    <button type="button" class="wc-btn p sm" style="margin-top:14px" @click="openCreate()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                        {{ __('ui.saved_replies_page.new_reply') }}
                    </button>
                </div>

                {{-- Filtered to nothing --}}
                <div class="wc-blank" x-show="!loading && replies.length > 0 && filtered().length === 0">
                    <div class="ico"><i class="ri-filter-off-line" style="font-size:26px"></i></div>
                    <div class="t">{{ __('ui.saved_replies_page.no_matches') }}</div>
                    <div class="s">{{ __('ui.saved_replies_page.no_matches_hint') }}</div>
                </div>

                {{-- Rows --}}
                <div class="wc-list" x-show="!loading && filtered().length > 0">
                    <template x-for="reply in filtered()" :key="reply.id">
                        <div class="wc-item" :class="selectedId === reply.id ? 'on' : ''" @click="select(reply)">
                            <div class="m">
                                <div class="hd">
                                    <span class="ttl" x-text="reply.title"></span>
                                    <template x-if="reply.shortcut">
                                        <span class="wc-code" x-text="reply.shortcut"></span>
                                    </template>
                                    <span class="wc-badge" :class="reply.scope === 'tenant' ? 'team' : 'personal'"
                                          x-text="reply.scope === 'tenant' ? i18n.scopeTeam : i18n.scopeSelf"></span>
                                </div>
                                <div class="bd" x-text="reply.body"></div>
                            </div>
                            <div class="acts" x-show="canWrite(reply)">
                                <button type="button" class="wc-ib" @click.stop="openEdit(reply)" title="{{ __('ui.edit') }}">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M4 20h4l10-10-4-4L4 16v4z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M13.5 6.5l4 4" stroke="currentColor" stroke-width="2"/></svg>
                                </button>
                                <button type="button" class="wc-ib del" @click.stop="deleteTarget = reply" title="{{ __('ui.delete') }}">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M4 7h16M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2M6 7l1 13a1 1 0 001 1h8a1 1 0 001-1l1-13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

            </div></div>

            {{-- ══ PREVIEW ══════════════════════════════════════════ --}}
            <aside class="wc-prev" :class="previewOpen ? 'open' : ''">
                <div class="wc-pvh">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="eye"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
                    <span class="t">{{ __('ui.saved_replies_page.preview') }}</span>
                </div>

                <div class="wc-pvbody">
                    <div class="wc-blank" style="width:288px" x-show="!selected()">
                        <div class="ico"><i class="ri-cursor-line" style="font-size:26px"></i></div>
                        <div class="t">{{ __('ui.saved_replies_page.pv_empty_title') }}</div>
                        <div class="s">{{ __('ui.saved_replies_page.pv_empty_hint') }}</div>
                    </div>

                    <template x-if="selected()">
                        <div class="wc-convo">
                            <div class="ch">
                                <span class="av" style="background:var(--wc-teal)"><i class="ri-user-3-line"></i></span>
                                <div class="m">
                                    <div class="n">{{ __('ui.saved_replies_page.pv_agent') }}</div>
                                    <div class="s" x-text="selected().title"></div>
                                </div>
                            </div>
                            <div class="cb">
                                <div class="wc-bub out" x-text="selected().body"></div>
                            </div>
                            <div class="cf">
                                <span class="fi" x-text="selected().shortcut || '{{ __('ui.saved_replies_page.pv_composer') }}'"></span>
                                <span class="sb"><svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M3 12L21 4l-8 17-2-7-8-2z" stroke="#fff" stroke-width="2.2" stroke-linejoin="round"/></svg></span>
                            </div>
                        </div>
                    </template>

                    <template x-if="selected()">
                        <div class="wc-pvmeta">
                            <div class="kv"><span class="k">{{ __('ui.saved_replies_page.meta_scope') }}</span><span class="v" x-text="selected().scope === 'tenant' ? i18n.scopeTeam : i18n.scopeSelf"></span></div>
                            <div class="kv"><span class="k">{{ __('ui.saved_replies_page.meta_shortcut') }}</span><span class="v" x-text="selected().shortcut || i18n.none"></span></div>
                            <div class="kv"><span class="k">{{ __('ui.saved_replies_page.meta_order') }}</span><span class="v" x-text="selected().sort_order ?? 0"></span></div>
                            <div class="kv"><span class="k">{{ __('ui.saved_replies_page.meta_length') }}</span><span class="v" x-text="(selected().body || '').length"></span></div>
                        </div>
                    </template>

                    <template x-if="selected() && canWrite(selected())">
                        <div style="display:flex;gap:8px;width:100%;max-width:288px">
                            <button type="button" class="wc-btn g sm" style="flex:1" @click="openEdit(selected())">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M4 20h4l10-10-4-4L4 16v4z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M13.5 6.5l4 4" stroke="currentColor" stroke-width="2"/></svg>
                                {{ __('ui.edit') }}
                            </button>
                            <button type="button" class="wc-btn g sm" style="flex:1;color:var(--wc-red);border-color:#fecaca" @click="deleteTarget = selected()">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M4 7h16M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2M6 7l1 13a1 1 0 001 1h8a1 1 0 001-1l1-13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                {{ __('ui.delete') }}
                            </button>
                        </div>
                    </template>
                </div>
            </aside>

        </div>
    </div>

    <button type="button" class="wc-pvfab" @click="previewOpen = true">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
        {{ __('ui.saved_replies_page.preview') }}
    </button>
    <div class="wc-scrim" :class="previewOpen ? 'on' : ''" @click="previewOpen = false"></div>

    {{-- ══ ADD / EDIT MODAL ═════════════════════════════════════════ --}}
    <div class="wc-ovl" :class="showModal ? 'on' : ''" @click.self="closeModal()">
        <div class="wc-modal">
            <div class="mh">
                <div class="m">
                    <div class="n" x-text="editId ? i18n.editTitle : i18n.addTitle"></div>
                    <div class="s">{{ __('ui.saved_replies_page.modal_subtitle') }}</div>
                </div>
                <button type="button" class="wc-x" @click="closeModal()" aria-label="{{ __('ui.cancel') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                </button>
            </div>

            <div class="mb">
                <div class="wc-fld">
                    <label for="srTitle">{{ __('ui.saved_replies_page.field_title') }}</label>
                    <input id="srTitle" class="wc-inp" type="text" maxlength="120" x-model="form.title"
                           placeholder="{{ __('ui.saved_replies_page.field_title_placeholder') }}" x-ref="titleInput">
                </div>

                <div class="wc-fld">
                    <label for="srShortcut">{{ __('ui.saved_replies_page.field_shortcut') }}</label>
                    <div class="wc-inp-prefix">
                        <span class="px">/</span>
                        <input id="srShortcut" class="wc-inp" type="text" maxlength="32" x-model="form.shortcut"
                               placeholder="{{ __('ui.saved_replies_page.field_shortcut_placeholder') }}"
                               @input="form.shortcut = form.shortcut.replace(/[^a-z0-9_-]/g, '')">
                    </div>
                    <div class="wc-hint">{{ __('ui.saved_replies_page.field_shortcut_hint') }}</div>
                </div>

                <div class="wc-fld">
                    <label for="srBody">{{ __('ui.saved_replies_page.field_body') }}</label>
                    <textarea id="srBody" class="wc-inp" maxlength="4000" x-model="form.body"
                              placeholder="{{ __('ui.saved_replies_page.field_body_placeholder') }}"></textarea>
                    <div class="wc-cnt"><span x-text="(form.body || '').length"></span>/4000</div>
                    <div class="wc-hint">{{ __('ui.saved_replies_page.field_body_hint') }}</div>
                </div>

                {{-- The API's update() does not accept `scope`, so it is only
                     offered while creating. On edit it is shown read-only
                     rather than as a control that silently does nothing. --}}
                <div class="wc-fld">
                    <span class="wc-flabel">{{ __('ui.saved_replies_page.field_scope') }}</span>

                    <div x-show="editId">
                        <span class="wc-badge" :class="form.scope === 'tenant' ? 'team' : 'personal'"
                              x-text="form.scope === 'tenant' ? i18n.scopeTeam : i18n.scopeSelf"></span>
                        <div class="wc-hint">{{ __('ui.saved_replies_page.scope_locked') }}</div>
                    </div>

                    <div class="wc-seg" x-show="!editId">
                        <button type="button" x-show="canManageTeam" :class="form.scope === 'tenant' ? 'on' : ''" @click="form.scope = 'tenant'">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M16 20v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="9" cy="7" r="3.4" stroke="currentColor" stroke-width="2"/></svg>
                            {{ __('ui.saved_replies_page.scope_tenant') }}
                        </button>
                        <button type="button" :class="form.scope === 'personal' ? 'on' : ''" @click="form.scope = 'personal'">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.4" stroke="currentColor" stroke-width="2"/><path d="M5.5 20a6.5 6.5 0 0113 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            {{ __('ui.saved_replies_page.scope_personal') }}
                        </button>
                    </div>
                    <div class="wc-hint" x-show="!editId && form.scope === 'tenant'">{{ __('ui.saved_replies_page.scope_tenant_hint') }}</div>
                    <div class="wc-hint" x-show="!editId && form.scope === 'personal'">{{ __('ui.saved_replies_page.scope_personal_hint') }}</div>
                </div>

                <div class="wc-fld">
                    <label for="srSort">{{ __('ui.saved_replies_page.field_sort') }}</label>
                    <input id="srSort" class="wc-inp" type="number" min="0" max="9999" x-model.number="form.sort_order" placeholder="0">
                    <div class="wc-hint">{{ __('ui.saved_replies_page.field_sort_hint') }}</div>
                </div>

                <div class="wc-note danger" x-show="modalError">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 8v4.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="16" r="1" fill="currentColor"/></svg>
                    <div x-text="modalError"></div>
                </div>
            </div>

            <div class="mf">
                <button type="button" class="wc-btn g" @click="closeModal()">{{ __('ui.cancel') }}</button>
                <button type="button" class="wc-btn p" @click="saveReply()" :disabled="saving">
                    <template x-if="!saving">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M17 21v-8H7v8M7 3v5h8" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                    </template>
                    <span x-text="saving ? '{{ __('ui.processing') }}' : '{{ __('ui.save') }}'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- ══ DELETE CONFIRM ═══════════════════════════════════════════ --}}
    <div class="wc-ovl" :class="deleteTarget ? 'on' : ''" @click.self="deleteTarget = null">
        <div class="wc-modal sm">
            <div class="mb" style="text-align:center">
                <div style="width:46px;height:46px;border-radius:50%;background:var(--wc-red-50);color:var(--wc-red);display:grid;place-items:center;margin:0 auto 12px">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M4 7h16M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2M6 7l1 13a1 1 0 001 1h8a1 1 0 001-1l1-13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div style="font-size:15px;font-weight:700;letter-spacing:-.02em">{{ __('ui.saved_replies_page.delete_confirm_title') }}</div>
                <div style="font-size:13px;color:var(--wc-muted);margin-top:5px">
                    {{ __('ui.saved_replies_page.delete_confirm_body') }}
                    <strong style="color:var(--wc-text)" x-text="deleteTarget?.title"></strong>?
                </div>
            </div>
            <div class="mf" style="justify-content:center">
                <button type="button" class="wc-btn g" @click="deleteTarget = null">{{ __('ui.cancel') }}</button>
                <button type="button" class="wc-btn dgr" @click="doDelete()" :disabled="saving">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M4 7h16M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2M6 7l1 13a1 1 0 001 1h8a1 1 0 001-1l1-13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    {{ __('ui.delete') }}
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function savedRepliesConsole() {
    return {
        i18n:          @json($i18n),
        canManageTeam: @json($canManageTeam),
        replies:      [],
        loading:      true,
        view:         'all',
        search:       '',
        selectedId:   null,
        previewOpen:  false,
        showModal:    false,
        editId:       null,
        saving:       false,
        modalError:   null,
        deleteTarget: null,

        form: { title: '', shortcut: '', body: '', scope: 'tenant', sort_order: 0 },

        init() { this.load(); },

        /* ── data ─────────────────────────────────────────────────── */
        async load() {
            this.loading = true;
            try {
                const res  = await fetch('/api/saved-replies', {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                this.replies = data.data || [];
                // Drop a stale selection after a delete or a scope change.
                if (this.selectedId && !this.replies.some(r => r.id === this.selectedId)) this.selectedId = null;
            } catch (e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        },

        /* ── views ────────────────────────────────────────────────── */
        go(v) {
            this.view = v;
            if (this.$refs.listScroll) this.$refs.listScroll.scrollTop = 0;
        },
        countBy(scope)   { return this.replies.filter(r => r.scope === scope).length; },
        countShortcuts() { return this.replies.filter(r => r.shortcut).length; },
        viewTitle() {
            return { all: this.i18n.ttlAll, tenant: this.i18n.ttlTeam,
                     personal: this.i18n.ttlPersonal, shortcut: this.i18n.ttlShortcut }[this.view];
        },
        viewDesc() {
            return { all: this.i18n.secAll, tenant: this.i18n.secTeam,
                     personal: this.i18n.secPersonal, shortcut: this.i18n.secShortcut }[this.view];
        },
        filtered() {
            let list = this.replies;
            if (this.view === 'tenant' || this.view === 'personal') list = list.filter(r => r.scope === this.view);
            if (this.view === 'shortcut') list = list.filter(r => r.shortcut);

            const q = this.search.trim().toLowerCase();
            if (q) list = list.filter(r =>
                (r.title || '').toLowerCase().includes(q)
                || (r.body || '').toLowerCase().includes(q)
                || (r.shortcut || '').toLowerCase().includes(q)
            );
            return list;
        },

        /* ── selection ────────────────────────────────────────────── */
        select(reply) {
            this.selectedId = reply.id;
            // On narrow screens the preview is a drawer, so a tap should open it.
            if (window.matchMedia('(max-width: 1180px)').matches) this.previewOpen = true;
        },
        selected() { return this.replies.find(r => r.id === this.selectedId) || null; },

        // A team reply is read-only for an agent — the API would 403 the write.
        canWrite(reply) { return reply.scope !== 'tenant' || this.canManageTeam; },

        /* ── create / edit ────────────────────────────────────────── */
        openCreate() {
            this.editId     = null;
            this.form       = { title: '', shortcut: '', body: '', scope: this.canManageTeam ? 'tenant' : 'personal', sort_order: 0 };
            this.modalError = null;
            this.showModal  = true;
            this.$nextTick(() => this.$refs.titleInput?.focus());
        },
        openEdit(reply) {
            this.editId = reply.id;
            this.form   = {
                title:      reply.title,
                // Stored with the leading slash; the field edits the bare name.
                shortcut:   (reply.shortcut || '').replace(/^\//, ''),
                body:       reply.body,
                scope:      reply.scope,
                sort_order: reply.sort_order || 0,
            };
            this.modalError  = null;
            this.showModal   = true;
            this.previewOpen = false;
            this.$nextTick(() => this.$refs.titleInput?.focus());
        },
        closeModal() {
            this.showModal  = false;
            this.editId     = null;
            this.modalError = null;
        },

        async saveReply() {
            if (!this.form.title.trim()) { this.modalError = this.i18n.errTitle; return; }
            if (!this.form.body.trim())  { this.modalError = this.i18n.errBody;  return; }

            this.saving     = true;
            this.modalError = null;

            const shortcut = this.form.shortcut.trim().replace(/^\//, '');
            const payload  = {
                title:      this.form.title.trim(),
                shortcut:   shortcut ? '/' + shortcut : null,
                body:       this.form.body.trim(),
                scope:      this.form.scope,
                sort_order: this.form.sort_order || 0,
            };

            try {
                const url    = this.editId ? '/api/saved-replies/' + this.editId : '/api/saved-replies';
                const method = this.editId ? 'PUT' : 'POST';
                const res    = await fetch(url, {
                    method,
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (!res.ok) {
                    const first = Object.values(data.errors || {})[0];
                    this.modalError = (Array.isArray(first) ? first[0] : first) || data.message || this.i18n.saveError;
                    return;
                }
                // store()/update() return the bare model, not a { data } envelope.
                const savedId = data.data?.id ?? data.id;
                if (savedId) this.selectedId = savedId;
                this.closeModal();
                await this.load();
            } catch {
                this.modalError = this.i18n.saveError;
            } finally {
                this.saving = false;
            }
        },

        async doDelete() {
            if (!this.deleteTarget) return;
            this.saving = true;
            try {
                await fetch('/api/saved-replies/' + this.deleteTarget.id, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                });
                if (this.selectedId === this.deleteTarget.id) this.selectedId = null;
                this.deleteTarget = null;
                await this.load();
            } finally {
                this.saving = false;
            }
        },

        csrf() { return document.querySelector('meta[name=csrf-token]')?.content || ''; },
    };
}
</script>

@push('styles')
    {{-- Shared Wavadesk console shell — see /webchat/settings and /ai-settings. --}}
    <link rel="stylesheet" href="{{ asset('css/wavadesk-console.css') }}?v={{ filemtime(public_path('css/wavadesk-console.css')) }}">
@endpush
@endsection
