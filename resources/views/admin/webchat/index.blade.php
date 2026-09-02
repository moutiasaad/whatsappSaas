@extends('layouts.admin')

@section('title', __('ui.webchat_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.webchat_page.title') }}</span>
@endsection

@push('styles')
<style>
    /* Full-bleed chat workspace — mirrors the WhatsApp conversations show page */
    main.page-content { padding: 0 !important; }
</style>
@endpush

@section('content')
@php
    $panelPrefix = auth()->user()->routeNamePrefix();
    $i18n = [
        'title'                 => __('ui.webchat_page.title'),
        'online'                => __('ui.webchat_page.online'),
        'offline'               => __('ui.webchat_page.offline'),
        'tab_pending'           => __('ui.webchat_page.tab_pending'),
        'tab_mine'              => __('ui.webchat_page.tab_mine'),
        'tab_all'               => __('ui.webchat_page.tab_all'),
        'tab_closed'            => __('ui.webchat_page.tab_closed'),
        'no_conversations'      => __('ui.webchat_page.no_conversations'),
        'select_conversation'   => __('ui.webchat_page.select_conversation'),
        'visitor_prefix'        => __('ui.webchat_page.visitor_prefix'),
        'anonymous'             => __('ui.webchat_page.anonymous'),
        'no_messages_yet'       => __('ui.webchat_page.no_messages_yet'),
        'just_now'              => __('ui.webchat_page.just_now'),
        'claim_btn'             => __('ui.webchat_page.claim_btn'),
        'claim_to_reply'        => __('ui.webchat_page.claim_to_reply'),
        'claimed_by_you'        => __('ui.webchat_page.claimed_by_you'),
        'claimed_by_prefix'     => __('ui.webchat_page.claimed_by_prefix'),
        'locked_by_agent'       => __('ui.webchat_page.locked_by_agent'),
        'chat_closed'           => __('ui.webchat_page.chat_closed'),
        'close_chat'            => __('ui.webchat_page.close_chat'),
        'close_confirm'         => __('ui.webchat_page.close_confirm'),
        'release_chat'          => __('ui.webchat_page.release_chat'),
        'composer_placeholder'  => __('ui.webchat_page.composer_placeholder'),
        'status_bot'            => __('ui.webchat_page.status_bot'),
        'status_pending'        => __('ui.webchat_page.status_pending'),
        'status_assigned'       => __('ui.webchat_page.status_assigned'),
        'status_closed'         => __('ui.webchat_page.status_closed'),
        'visitor_info'          => __('ui.webchat_page.visitor_info'),
        'name'                  => __('ui.webchat_page.name'),
        'email'                 => __('ui.webchat_page.email'),
        'page'                  => __('ui.webchat_page.page'),
        'referrer'              => __('ui.webchat_page.referrer'),
        'browser'               => __('ui.webchat_page.browser'),
        'ip'                    => __('ui.webchat_page.ip'),
        'started_at'            => __('ui.webchat_page.started_at'),
        'claim_success'         => __('ui.webchat_page.claim_success'),
        'claim_race_lost'       => __('ui.webchat_page.claim_race_lost'),
        'claim_error'           => __('ui.webchat_page.claim_error'),
        'release_success'       => __('ui.webchat_page.release_success'),
        'release_error'         => __('ui.webchat_page.release_error'),
        'close_success'         => __('ui.webchat_page.close_success'),
        'close_error'           => __('ui.webchat_page.close_error'),
        'send_error'            => __('ui.webchat_page.send_error'),
        'not_your_conversation' => __('ui.webchat_page.not_your_conversation'),
        'conversation_closed'   => __('ui.webchat_page.conversation_closed'),
        'new_pending_toast'     => __('ui.webchat_page.new_pending_toast'),

        'close_modal_title'             => __('ui.conversation_show_page.close_modal_title'),
        'close_modal_intro'             => __('ui.conversation_show_page.close_modal_intro'),
        'close_modal_title_label'       => __('ui.conversation_show_page.close_modal_title_label'),
        'close_modal_title_placeholder' => __('ui.conversation_show_page.close_modal_title_placeholder'),
        'close_modal_generating'        => __('ui.conversation_show_page.close_modal_generating'),
        'close_modal_regenerate'        => __('ui.conversation_show_page.close_modal_regenerate'),
        'close_modal_close_btn'         => __('ui.conversation_show_page.close_modal_close_btn'),
        'close_modal_cancel'            => __('ui.conversation_show_page.close_modal_cancel'),
        'close_modal_generate_failed'   => __('ui.conversation_show_page.close_modal_generate_failed'),
    ];
@endphp

<div x-data="webchatInbox()" x-init="init()" x-cloak class="cw-root">

    {{-- =========================================================
         LEFT RAIL — Conversation list
    ========================================================== --}}
    <aside class="cw-rail">
        <div class="cw-rail-head">
            <div class="cw-rail-title">
                <span>{{ __('ui.webchat_page.title') }}</span>
                <span class="cw-rail-count" x-text="conversations.length"></span>
            </div>

            <div class="cw-tabs">
                <template x-for="tab in tabs" :key="tab">
                    <button type="button" @click="setFilter(tab)" :class="filter === tab ? 'active' : ''">
                        <span x-text="i18n['tab_' + tab]"></span>
                        <template x-if="tab === 'pending' && pendingCount > 0">
                            <span class="cw-tab-dot" x-text="pendingCount"></span>
                        </template>
                    </button>
                </template>
            </div>
        </div>

        <div class="cw-rail-body">
            <template x-if="loading && conversations.length === 0">
                <div class="cw-empty">
                    <div class="spinner"></div>
                </div>
            </template>

            <template x-if="!loading && conversations.length === 0">
                <div class="cw-empty">
                    <i class="ri-chat-off-line"></i>
                    <div x-text="i18n.no_conversations"></div>
                </div>
            </template>

            <template x-for="conv in conversations" :key="conv.uuid">
                <div
                    class="cw-row"
                    :class="{
                        'active': activeUuid === conv.uuid,
                    }"
                    @click="openRow(conv)">
                    <div class="cw-row-avatar" x-text="visitorInitials(conv)"></div>
                    <div class="cw-row-body">
                        <div class="cw-row-top">
                            <div class="cw-row-name" x-text="displayName(conv)"></div>
                            <div class="cw-row-time" x-text="timeAgo(conv.last_activity_at || conv.created_at)"></div>
                        </div>
                        <div class="cw-row-bottom">
                            <div class="cw-row-preview" x-text="conv.title || conv.last_message_preview || i18n.no_messages_yet"></div>
                        </div>
                        <div class="cw-row-meta">
                            <span class="cw-pill" :class="statePillClass(conv.status)" x-text="i18n['status_' + conv.status]"></span>
                            <template x-if="conv.status === 'assigned' && conv.claimer">
                                <span class="cw-row-instance" x-text="conv.claimer.id === myId ? i18n.claimed_by_you : (i18n.claimed_by_prefix + ' ' + conv.claimer.name)"></span>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </aside>

    {{-- =========================================================
         MIDDLE THREAD
    ========================================================== --}}
    <section class="cw-thread">

        <template x-if="!activeUuid">
            <div class="cw-empty" style="flex:1;">
                <i class="ri-chat-3-line"></i>
                <div x-text="i18n.select_conversation"></div>
            </div>
        </template>

        <template x-if="activeUuid">
            <div style="display:flex; flex-direction:column; min-height:0; flex:1;">

                <header class="cw-thread-head">
                    <div class="cw-thread-contact">
                        <div class="cw-avatar cw-avatar-lg" x-text="visitorInitials(active.conversation || active.visitor)"></div>
                        <div class="cw-thread-copy">
                            <div class="cw-thread-name" x-text="active.visitor?.name || i18n.anonymous"></div>
                            <div x-show="active.conversation?.title" x-cloak class="cw-thread-title"
                                 :title="active.conversation?.title" x-text="active.conversation?.title"></div>
                            <div class="cw-thread-meta">
                                <span class="cw-state-badge" :class="stateBadgeClass(active.conversation?.status)" x-text="i18n['status_' + (active.conversation?.status || 'bot')]"></span>
                                <template x-if="active.conversation?.claimer && active.conversation?.status === 'assigned'">
                                    <span class="cw-dot-sep"></span>
                                </template>
                                <template x-if="active.conversation?.claimer && active.conversation?.status === 'assigned'">
                                    <span x-text="active.conversation.claimer.id === myId ? i18n.claimed_by_you : (i18n.claimed_by_prefix + ' ' + active.conversation.claimer.name)"></span>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="cw-thread-actions">
                        <span class="cw-conn-badge" :class="wsConnected ? 'is-on' : 'is-off'">
                            <span class="cw-conn-dot"></span>
                            <span x-text="wsConnected ? i18n.online : i18n.offline"></span>
                        </span>
                        <template x-if="canManageLock()">
                            <button type="button" @click="close()" class="cw-close-btn">
                                <i class="ri-close-circle-line"></i>
                                <span x-text="i18n.close_chat"></span>
                            </button>
                        </template>
                    </div>
                </header>

                <div class="cw-stage" x-ref="thread">
                    <template x-for="msg in messages" :key="msg.id">
                        <div>
                            <template x-if="msg.sender_type === 'system'">
                                <div class="cw-date-sep"><span x-text="msg.body"></span></div>
                            </template>
                            <template x-if="msg.sender_type !== 'system'">
                                <div class="cw-msg" :class="msg.sender_type === 'visitor' ? 'cw-msg-in' : 'cw-msg-out'">
                                    <template x-if="msg.sender_type === 'visitor'">
                                        <div class="cw-msg-avatar" x-text="visitorInitials(active.conversation || active.visitor)"></div>
                                    </template>
                                    <div class="cw-msg-stack">
                                        <div class="cw-bubble" :class="msg.sender_type === 'visitor' ? 'cw-bubble-in' : 'cw-bubble-out'">
                                            <div class="cw-msg-body" x-text="msg.body"></div>
                                            <div class="cw-msg-foot">
                                                <span x-text="formatTime(msg.created_at)"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Composer / claim CTA / closed banner --}}
                <div class="cw-composer-wrap">
                    <template x-if="active.conversation?.status === 'closed'">
                        <div class="cw-closed-banner">
                            <i class="ri-lock-line"></i>
                            <span x-text="i18n.chat_closed"></span>
                        </div>
                    </template>

                    <template x-if="active.conversation?.status !== 'closed' && !isMyClaim()">
                        <div>
                            <template x-if="active.conversation?.status === 'pending' || active.conversation?.status === 'bot'">
                                <button type="button" @click="claim(active.conversation.uuid)" class="cw-claim-btn">
                                    <i class="ri-hand-heart-line"></i>
                                    <span x-text="i18n.claim_to_reply"></span>
                                </button>
                            </template>
                            <template x-if="active.conversation?.status === 'assigned'">
                                <div class="cw-closed-banner">
                                    <i class="ri-lock-line"></i>
                                    <span x-text="i18n.locked_by_agent"></span>
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="active.conversation?.status !== 'closed' && isMyClaim()">
                        <div class="cw-composer">
                            <textarea
                                x-model="composer"
                                @keydown.enter.prevent="sendMessage()"
                                :placeholder="i18n.composer_placeholder"
                                rows="1"
                                class="cw-textarea"></textarea>
                            <button type="button" @click="sendMessage()" :disabled="!composer.trim() || sending" class="cw-send">
                                <i class="ri-send-plane-2-line"></i>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </template>

    </section>

    {{-- =========================================================
         RIGHT RAIL — Visitor info
    ========================================================== --}}
    <aside class="cw-side" x-show="activeUuid" x-cloak>
        <div class="cw-profile">
            <div class="cw-profile-avatar" x-text="visitorInitials(active.conversation || active.visitor)"></div>
            <div class="cw-profile-name" x-text="active.visitor?.name || i18n.anonymous"></div>
            <template x-if="active.visitor?.email">
                <div class="cw-profile-company" x-text="active.visitor.email"></div>
            </template>
        </div>

        <div class="cw-side-card">
            <div class="cw-side-panel">
                <div style="font-size:.75rem; font-weight:800; color:#0f172a; margin-bottom:8px; letter-spacing:.03em; text-transform:uppercase;" x-text="i18n.visitor_info"></div>

                <template x-if="active.meta?.page_url">
                    <div class="cw-meta-row">
                        <span x-text="i18n.page"></span>
                        <strong style="max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" x-text="active.meta.page_url" :title="active.meta.page_url"></strong>
                    </div>
                </template>
                <template x-if="active.meta?.referrer">
                    <div class="cw-meta-row">
                        <span x-text="i18n.referrer"></span>
                        <strong style="max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" x-text="active.meta.referrer" :title="active.meta.referrer"></strong>
                    </div>
                </template>
                <template x-if="active.meta?.user_agent">
                    <div class="cw-meta-row">
                        <span x-text="i18n.browser"></span>
                        <strong style="max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" x-text="active.meta.user_agent" :title="active.meta.user_agent"></strong>
                    </div>
                </template>
                <template x-if="active.meta?.ip">
                    <div class="cw-meta-row">
                        <span x-text="i18n.ip"></span>
                        <strong x-text="active.meta.ip"></strong>
                    </div>
                </template>
                <template x-if="active.conversation?.created_at">
                    <div class="cw-meta-row">
                        <span x-text="i18n.started_at"></span>
                        <strong x-text="formatDateTime(active.conversation.created_at)"></strong>
                    </div>
                </template>
            </div>
        </div>
    </aside>

    {{-- Close-with-title modal --}}
    <template x-teleport="body">
        <div x-show="showCloseModal" x-cloak class="modal-overlay show wc-close-modal-overlay"
             @click.self="cancelCloseModal()">
            <div class="modal-box wc-close-modal-box" @click.stop>
                <div class="modal-send-icon"><i class="ri-close-circle-line"></i></div>
                <h3 x-text="i18n.close_modal_title"></h3>
                <p x-text="i18n.close_modal_intro"></p>

                <label class="form-label wc-close-modal-label" x-text="i18n.close_modal_title_label"></label>
                <div class="wc-close-modal-input-wrap">
                    <input type="text" class="form-control" maxlength="180"
                           x-model="closeTitle"
                           :placeholder="i18n.close_modal_title_placeholder"
                           :disabled="closeTitleLoading">
                    <div class="wc-close-modal-generating" x-show="closeTitleLoading" x-cloak>
                        <i class="ri-loader-4-line wc-spin"></i>
                        <span x-text="i18n.close_modal_generating"></span>
                    </div>
                </div>
                <div class="wc-close-modal-regen">
                    <button type="button" class="btn btn-outline btn-sm"
                            @click="suggestCloseTitle()" :disabled="closeTitleLoading">
                        <i class="ri-magic-line"></i>
                        <span x-text="i18n.close_modal_regenerate"></span>
                    </button>
                </div>

                <div class="modal-actions wc-close-modal-actions">
                    <button type="button" class="btn btn-outline"
                            @click="cancelCloseModal()" x-text="i18n.close_modal_cancel"></button>
                    <button type="button" class="btn btn-danger"
                            :disabled="closing" @click="submitCloseWithTitle()"
                            x-text="i18n.close_modal_close_btn"></button>
                </div>
            </div>
        </div>
    </template>

</div>

<script>
function webchatInbox() {
    return {
        i18n:           @json($i18n),
        listUrl:        @json(route($panelPrefix . '.webchat.conversations.index')),
        showUrlTpl:     @json(route($panelPrefix . '.webchat.conversations.show',    ['uuid' => '__UUID__'])),
        claimUrlTpl:    @json(route($panelPrefix . '.webchat.conversations.claim',   ['uuid' => '__UUID__'])),
        releaseUrlTpl:  @json(route($panelPrefix . '.webchat.conversations.release', ['uuid' => '__UUID__'])),
        closeUrlTpl:    @json(route($panelPrefix . '.webchat.conversations.close',   ['uuid' => '__UUID__'])),
        suggestTitleUrlTpl: @json(route($panelPrefix . '.webchat.conversations.suggest-title', ['uuid' => '__UUID__'])),
        readUrlTpl:     @json(route($panelPrefix . '.webchat.conversations.read',    ['uuid' => '__UUID__'])),
        messageUrlTpl:  @json(route($panelPrefix . '.webchat.messages.store',        ['uuid' => '__UUID__'])),
        tenantId:       @json((int) auth()->user()->tenant_id),
        myId:           @json((int) auth()->id()),
        myName:         @json((string) auth()->user()->name),
        isAdmin:        @json((bool) auth()->user()->isAdmin()),

        tabs:           ['pending', 'mine', 'all', 'closed'],
        filter:         'pending',
        conversations:  [],
        loading:        false,

        activeUuid:     null,
        active:         { conversation: null, visitor: null, widget: null, meta: null },
        messages:       [],
        composer:       '',
        sending:        false,

        wsConnected:    false,
        _pollTimer:     null,
        _threadPoll:    null,

        showCloseModal:    false,
        closeTitle:        '',
        closeTitleLoading: false,
        closing:           false,

        get pendingCount() {
            return this.conversations.filter(c => c.status === 'pending').length;
        },

        init() {
            this.loadList();
            this.subscribePresence();

            // Poll list every 15s as a broadcast fallback. When a thread is
            // open, the 6s thread poll from _startThreadPoll takes over the
            // per-conversation freshness so the list poll can stay slow.
            this._pollTimer = setInterval(() => {
                this.loadList(true);
            }, 15000);

            if (window._echoStateListeners) {
                window._echoStateListeners.push((c) => { this.wsConnected = c; });
            }
            this.wsConnected = !!window._echoConnected;

            window.addEventListener('beforeunload', () => this.cleanup());
        },

        cleanup() {
            if (this._pollTimer)  clearInterval(this._pollTimer);
            if (this._threadPoll) clearInterval(this._threadPoll);
        },

        _startThreadPoll() {
            if (this._threadPoll) return;
            this._threadPoll = setInterval(() => {
                if (this.wsConnected || !this.activeUuid) return;
                this._pollThreadDelta();
            }, 6000);
        },

        _stopThreadPoll() {
            if (this._threadPoll) { clearInterval(this._threadPoll); this._threadPoll = null; }
        },

        async _pollThreadDelta() {
            if (!this.activeUuid) return;
            const lastId = this.messages.length ? this.messages[this.messages.length - 1].id : 0;
            const url = this.showUrlTpl.replace('__UUID__', this.activeUuid) + '?after=' + lastId;
            try {
                const r = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!r.ok) return;
                const data = await r.json();
                const added = (data.messages || []).filter(m => !this.messages.some(x => x.id === m.id));
                if (added.length) {
                    this.messages.push(...added);
                    if (this.isMyClaim()) this.markRead(this.activeUuid);
                    this.$nextTick(() => this.scrollThreadBottom());
                }
                if (data.conversation && this.active.conversation && data.conversation.status !== this.active.conversation.status) {
                    this.active.conversation = data.conversation;
                }
            } catch (e) { /* silent — next tick will retry */ }
        },

        setFilter(f) {
            if (this.filter === f) return;
            this.filter = f;
            this.activeUuid = null;
            this.active = { conversation: null, visitor: null, widget: null, meta: null };
            this.messages = [];
            this.loadList();
        },

        async loadList(silent = false) {
            if (!silent) this.loading = true;
            try {
                const url = new URL(this.listUrl, window.location.origin);
                url.searchParams.set('filter', this.filter);
                const r = await fetch(url.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const data = await r.json();
                this.conversations = data.data || [];
            } catch (e) {
                console.error('[webchat] loadList failed', e);
            } finally {
                if (!silent) this.loading = false;
            }
        },

        async openRow(conv) {
            if (this.activeUuid === conv.uuid) return;
            this.activeUuid = conv.uuid;
            this.messages = [];
            this.active = { conversation: null, visitor: null, widget: null, meta: null };

            try {
                const r = await fetch(this.showUrlTpl.replace('__UUID__', conv.uuid), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const data = await r.json();
                this.active = {
                    conversation: data.conversation,
                    visitor: data.visitor,
                    widget: data.widget,
                    meta: data.meta,
                };
                this.messages = data.messages || [];

                this.subscribeThread(conv.uuid);
                this._startThreadPoll();

                if (this.isMyClaim()) this.markRead(conv.uuid);

                await this.$nextTick();
                this.scrollThreadBottom();
            } catch (e) {
                console.error('[webchat] openRow failed', e);
                this.activeUuid = null;
                window.showToast?.('error', 'Could not open conversation');
            }
        },

        async claim(uuid) {
            try {
                const r = await this.post(this.claimUrlTpl.replace('__UUID__', uuid));
                if (r.status === 409) {
                    window.showToast?.('error', this.i18n.claim_race_lost);
                    this.loadList();
                    return;
                }
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const data = await r.json();
                window.showToast?.('success', this.i18n.claim_success);

                const idx = this.conversations.findIndex(c => c.uuid === uuid);
                if (idx !== -1) {
                    this.conversations[idx] = {
                        ...this.conversations[idx],
                        status: 'assigned',
                        claimer: data.conversation.claimer,
                    };
                }
                if (this.activeUuid === uuid && this.active.conversation) {
                    this.active.conversation = { ...this.active.conversation, ...data.conversation };
                }
            } catch (e) {
                console.error('[webchat] claim failed', e);
                window.showToast?.('error', this.i18n.claim_error);
            }
        },

        async release() {
            if (!this.activeUuid) return;
            try {
                const r = await this.post(this.releaseUrlTpl.replace('__UUID__', this.activeUuid));
                if (!r.ok) throw new Error('HTTP ' + r.status);
                window.showToast?.('success', this.i18n.release_success);
                this.loadList();
            } catch (e) {
                console.error('[webchat] release failed', e);
                window.showToast?.('error', this.i18n.release_error);
            }
        },

        close() {
            if (!this.activeUuid) return;
            this.closeTitle = '';
            this.showCloseModal = true;
            this.suggestCloseTitle();
        },

        async suggestCloseTitle() {
            if (!this.activeUuid) return;
            this.closeTitleLoading = true;
            try {
                const r = await this.post(this.suggestTitleUrlTpl.replace('__UUID__', this.activeUuid));
                if (r.ok) {
                    const data = await r.json();
                    if (data.title) this.closeTitle = data.title;
                }
            } catch (e) {
                console.warn('[webchat] title suggest failed', e);
                window.showToast?.('error', this.i18n.close_modal_generate_failed);
            } finally {
                this.closeTitleLoading = false;
            }
        },

        cancelCloseModal() {
            this.showCloseModal = false;
            this.closeTitle = '';
            this.closeTitleLoading = false;
        },

        async submitCloseWithTitle() {
            if (!this.activeUuid || this.closing) return;
            this.closing = true;
            try {
                const url = this.closeUrlTpl.replace('__UUID__', this.activeUuid);
                const r = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ title: (this.closeTitle || '').trim() })
                });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const data = await r.json();
                if (this.active.conversation) {
                    this.active.conversation.title = data.conversation?.title || this.closeTitle;
                }
                window.showToast?.('success', this.i18n.close_success);
                this.showCloseModal = false;
                this.loadList(true);
            } catch (e) {
                console.error('[webchat] close failed', e);
                window.showToast?.('error', this.i18n.close_error);
            } finally {
                this.closing = false;
            }
        },

        async sendMessage() {
            const body = this.composer.trim();
            if (!body || !this.activeUuid || this.sending) return;
            if (!this.isMyClaim()) return;

            this.sending = true;
            try {
                const r = await fetch(this.messageUrlTpl.replace('__UUID__', this.activeUuid), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ body }),
                });
                if (r.status === 403) { window.showToast?.('error', this.i18n.not_your_conversation); return; }
                if (r.status === 409) { window.showToast?.('error', this.i18n.conversation_closed); return; }
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const data = await r.json();

                this.composer = '';

                if (!this.messages.some(m => m.id === data.message.id)) {
                    this.messages.push({
                        id:          data.message.id,
                        sender_type: data.message.sender_type,
                        sender_id:   data.message.sender_id,
                        sender:      { id: this.myId, name: this.myName },
                        body:        data.message.body,
                        created_at:  data.message.created_at,
                    });
                    await this.$nextTick();
                    this.scrollThreadBottom();
                }
            } catch (e) {
                console.error('[webchat] send failed', e);
                window.showToast?.('error', this.i18n.send_error);
            } finally {
                this.sending = false;
            }
        },

        async markRead(uuid) {
            try { await this.post(this.readUrlTpl.replace('__UUID__', uuid)); } catch (e) {}
        },

        subscribePresence() {
            if (!window.Echo || typeof window.Echo.join !== 'function') return;
            try {
                const ch = window.Echo.join('webchat.tenant.' + this.tenantId);
                ch.listen('.webchat.conversation.requested', (p) => this.onRequested(p));
                ch.listen('.webchat.conversation.claimed',   (p) => this.onClaimed(p));
                ch.listen('.webchat.conversation.released',  (p) => this.onReleased(p));
                ch.listen('.webchat.conversation.closed',    (p) => this.onClosed(p));
                ch.listen('.webchat.message.sent',           (p) => this.onPresenceMessage(p));
            } catch (e) {
                console.warn('[webchat] presence subscribe failed', e);
            }
        },

        subscribeThread(uuid) {
            if (!window.Echo) return;
            try {
                const ch = window.Echo.private('webchat.conversation.' + uuid);
                ch.listen('.webchat.message.sent',        (p) => this.onThreadMessage(p));
                ch.listen('.webchat.conversation.closed', (p) => this.onThreadClosed(p));
            } catch (e) {
                console.warn('[webchat] thread subscribe failed', e);
            }
        },

        onRequested(payload) {
            const conv = payload.conversation;
            const idx = this.conversations.findIndex(c => c.uuid === conv.uuid);
            if (idx !== -1) {
                this.conversations[idx].status = 'pending';
                this.conversations[idx].last_activity_at = conv.last_activity_at;
                this.conversations[idx].claimer = null;
            } else if (this.filter === 'pending' || this.filter === 'all') {
                this.conversations.unshift({
                    uuid: conv.uuid,
                    status: 'pending',
                    visitor_name: conv.visitor_name,
                    visitor_email: conv.visitor_email,
                    page_url: conv.page_url,
                    last_activity_at: conv.last_activity_at,
                    created_at: conv.created_at,
                    claimer: null,
                    last_message_preview: null,
                });
                window.showToast?.('success', this.i18n.new_pending_toast);
            }
        },

        onClaimed(payload) {
            const conv = payload.conversation;
            const agent = payload.agent;
            const idx = this.conversations.findIndex(c => c.uuid === conv.uuid);
            if (idx !== -1) {
                this.conversations[idx].status = 'assigned';
                this.conversations[idx].claimer = { id: agent.id, name: agent.name };
                this.conversations[idx].last_activity_at = conv.last_activity_at;
            }
            if (this.activeUuid === conv.uuid && this.active.conversation) {
                this.active.conversation = {
                    ...this.active.conversation,
                    status: 'assigned',
                    claimed_by: agent.id,
                    claimer: { id: agent.id, name: agent.name },
                };
            }
        },

        onReleased(payload) {
            const conv = payload.conversation;
            const idx = this.conversations.findIndex(c => c.uuid === conv.uuid);
            if (idx !== -1) {
                this.conversations[idx].status = 'pending';
                this.conversations[idx].claimer = null;
            }
            if (this.activeUuid === conv.uuid && this.active.conversation) {
                this.active.conversation = { ...this.active.conversation, status: 'pending', claimed_by: null, claimer: null };
            }
        },

        onClosed(payload) {
            const conv = payload.conversation;
            const idx = this.conversations.findIndex(c => c.uuid === conv.uuid);
            if (idx !== -1) {
                if (this.filter === 'pending' || this.filter === 'mine') {
                    this.conversations.splice(idx, 1);
                } else {
                    this.conversations[idx].status = 'closed';
                    this.conversations[idx].claimer = null;
                }
            }
            if (this.activeUuid === conv.uuid && this.active.conversation) {
                this.active.conversation = { ...this.active.conversation, status: 'closed', claimed_by: null, claimer: null };
            }
        },

        onPresenceMessage(payload) {
            const msg = payload.message;
            const conv = payload.conversation;
            const idx = this.conversations.findIndex(c => c.uuid === conv.uuid);
            if (idx !== -1) {
                this.conversations[idx].last_message_preview = (msg.body || '').slice(0, 120);
                this.conversations[idx].last_activity_at = conv.last_activity_at;
            }
        },

        onThreadMessage(payload) {
            const msg = payload.message;
            if (msg.conversation_uuid !== this.activeUuid) return;
            if (this.messages.some(m => m.id === msg.id)) return;
            this.messages.push({
                id:          msg.id,
                sender_type: msg.sender_type,
                sender_id:   msg.sender_id,
                sender:      msg.sender_type === 'agent' && msg.sender_id === this.myId
                                ? { id: this.myId, name: this.myName }
                                : null,
                body:        msg.body,
                created_at:  msg.created_at,
            });
            this.$nextTick(() => this.scrollThreadBottom());
        },

        onThreadClosed(payload) {
            if (payload.conversation.uuid !== this.activeUuid) return;
            if (this.active.conversation) {
                this.active.conversation = {
                    ...this.active.conversation,
                    status: 'closed',
                    claimed_by: null,
                    claimer: null,
                };
            }
        },

        post(url, body = {}) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            });
        },

        csrf() {
            return document.querySelector('meta[name=csrf-token]')?.content || '';
        },

        isMyClaim() {
            const c = this.active.conversation;
            if (!c || c.status !== 'assigned') return false;
            return c.claimed_by === this.myId
                || (c.claimer && c.claimer.id === this.myId);
        },

        isRowLocked(conv) {
            return conv.status === 'assigned'
                && conv.claimer
                && conv.claimer.id !== this.myId
                && !this.isAdmin;
        },

        canManageLock() {
            const c = this.active.conversation;
            if (!c || c.status === 'closed') return false;
            const isClaimer = c.claimed_by === this.myId
                || (c.claimer && c.claimer.id === this.myId);
            return isClaimer || this.isAdmin;
        },

        displayName(conv) {
            if (conv.visitor_name) return conv.visitor_name;
            return this.i18n.visitor_prefix + (conv.uuid ? conv.uuid.slice(0, 6) : '');
        },

        // Two-letter initials for the round avatar. Falls back to a globe glyph
        // when no name and no uuid are available.
        visitorInitials(src) {
            if (!src) return '·';
            const name = src.visitor_name || src.name || '';
            if (name) {
                const parts = name.trim().split(/\s+/);
                const a = parts[0]?.[0] || '';
                const b = parts.length > 1 ? parts[parts.length - 1][0] : '';
                return (a + b).toUpperCase() || '·';
            }
            const uuid = src.uuid || '';
            return uuid ? uuid.slice(0, 2).toUpperCase() : '·';
        },

        // Rail-row pill (small)
        statePillClass(status) {
            return ({
                bot:      'cw-pill-closed',
                pending:  'cw-pill-pool',
                assigned: 'cw-pill-claimed',
                closed:   'cw-pill-closed',
            })[status] || 'cw-pill-closed';
        },

        // Thread-header state badge (larger)
        stateBadgeClass(status) {
            return ({
                bot:      'cw-state-neutral',
                pending:  'cw-state-pool',
                assigned: 'cw-state-claimed',
                closed:   'cw-state-closed',
            })[status] || 'cw-state-neutral';
        },

        timeAgo(iso) {
            if (!iso) return '';
            const s = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
            if (s < 60)    return this.i18n.just_now;
            if (s < 3600)  return Math.floor(s / 60) + 'm';
            if (s < 86400) return Math.floor(s / 3600) + 'h';
            return Math.floor(s / 86400) + 'd';
        },

        formatTime(iso) {
            if (!iso) return '';
            try {
                return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            } catch (e) { return ''; }
        },

        formatDateTime(iso) {
            if (!iso) return '';
            try {
                return new Date(iso).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' });
            } catch (e) { return ''; }
        },

        scrollThreadBottom() {
            if (this.$refs.thread) {
                this.$refs.thread.scrollTop = this.$refs.thread.scrollHeight;
            }
        },
    };
}
</script>

@push('styles')
<style>
/* ============================================================
   CHAT WORKSPACE — mirrors admin/conversations/show.blade.php
   (kept inline here so the WhatsApp show page stays untouched)
============================================================ */
.cw-root {
    display: grid;
    grid-template-columns: 320px minmax(0, 1fr) 340px;
    height: calc(100vh - var(--topbar-height, 64px));
    background: #f6f7fb;
    overflow: hidden;
}

/* ---- LEFT RAIL ---- */
.cw-rail {
    display: flex;
    flex-direction: column;
    min-height: 0;
    background: #ffffff;
    border-right: 1px solid #e6e8ee;
}
.cw-rail-head {
    padding: 14px 14px 10px;
    border-bottom: 1px solid #eef0f4;
    background: #fff;
    position: sticky;
    top: 0;
    z-index: 2;
}
.cw-rail-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.cw-rail-title span:first-child {
    font-weight: 700;
    font-size: .95rem;
    color: #0f172a;
}
.cw-rail-count {
    background: #eef2ff;
    color: #4338ca;
    font-size: .7rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 999px;
}
.cw-tabs {
    display: flex;
    gap: 4px;
    background: #f1f5f9;
    padding: 3px;
    border-radius: 8px;
}
.cw-tabs button {
    flex: 1;
    border: none;
    background: transparent;
    padding: 6px 8px;
    border-radius: 6px;
    font-size: .75rem;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: all .15s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}
.cw-tabs button:hover { color: #0f172a; }
.cw-tabs button.active {
    background: #fff;
    color: #4338ca;
    box-shadow: 0 1px 3px rgba(15,23,42,.06);
}
.cw-tab-dot {
    background: #ef4444; color: #fff;
    font-size: .6rem; font-weight: 800;
    padding: 1px 6px; border-radius: 999px;
    line-height: 1;
}
.cw-rail-body {
    flex: 1;
    overflow-y: auto;
    padding: 6px 0;
}
.cw-row {
    display: flex;
    gap: 10px;
    padding: 12px 14px;
    border-bottom: 1px solid #f4f5f8;
    cursor: pointer;
    transition: background .12s;
    position: relative;
}
.cw-row:hover { background: #f8fafc; }
.cw-row.active {
    background: linear-gradient(90deg, #eef2ff, #f5f7ff);
}
.cw-row.active::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 3px;
    background: #6366f1;
}
.cw-row-avatar {
    width: 40px; height: 40px;
    border-radius: 999px;
    background: linear-gradient(135deg, #14b8a6, #0ea5e9);
    color: #fff;
    font-weight: 700;
    font-size: .8rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
}
.cw-row-body { flex: 1; min-width: 0; }
.cw-row-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 6px;
}
.cw-row-name {
    font-size: .88rem;
    font-weight: 600;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cw-row-time {
    font-size: .7rem;
    color: #94a3b8;
    flex-shrink: 0;
    font-weight: 500;
}
.cw-row-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 6px;
    margin-top: 2px;
}
.cw-row-preview {
    font-size: .78rem;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
}
.cw-row-meta {
    display: flex;
    gap: 6px;
    margin-top: 6px;
    align-items: center;
    flex-wrap: wrap;
}
.cw-pill {
    font-size: .62rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: .03em;
}
.cw-pill-pool    { background: rgba(245,158,11,.12); color: #b45309; }
.cw-pill-claimed { background: rgba(59,130,246,.12); color: #1d4ed8; }
.cw-pill-closed  { background: rgba(100,116,139,.12); color: #475569; }
.cw-row-instance {
    font-size: .68rem;
    color: #94a3b8;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cw-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 40px 12px;
    color: #94a3b8;
    text-align: center;
    font-size: .82rem;
}
.cw-empty i { font-size: 2.2rem; opacity: .5; }

/* ---- MIDDLE THREAD ---- */
.cw-thread {
    display: flex;
    flex-direction: column;
    min-height: 0;
    background: #ffffff;
    border-right: 1px solid #e6e8ee;
}
.cw-thread-head {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    border-bottom: 1px solid #eef0f4;
    background: #fff;
    flex-wrap: wrap;
}
.cw-thread-contact {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
    flex: 1;
}
.cw-avatar {
    width: 44px; height: 44px;
    border-radius: 999px;
    background: linear-gradient(135deg, #14b8a6, #059669);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: .85rem;
    flex-shrink: 0;
}
.cw-avatar-lg { width: 46px; height: 46px; }
.cw-thread-copy { min-width: 0; }
.cw-thread-name {
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cw-thread-title {
    font-size: .82rem;
    color: #475569;
    font-weight: 500;
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}
.wc-close-modal-overlay { z-index: 10000 !important; }
.wc-close-modal-box     { max-width: 480px !important; text-align: left !important; }
.wc-close-modal-label   { margin-top: 12px; display: block; font-weight: 600; font-size: 13px; }
.wc-close-modal-input-wrap { position: relative; }
.wc-close-modal-generating {
    position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
    color: #64748b; font-size: 12px; display: flex; align-items: center; gap: 6px;
}
.wc-close-modal-regen    { margin-top: 8px; }
.wc-close-modal-actions  { margin-top: 18px; }
.wc-spin                 { display: inline-block; animation: wcSpin 1s linear infinite; }
@keyframes wcSpin { from { transform: rotate(0); } to { transform: rotate(360deg); } }
.cw-thread-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: .76rem;
    color: #64748b;
    margin-top: 2px;
    flex-wrap: wrap;
}
.cw-dot-sep {
    width: 3px; height: 3px; border-radius: 999px; background: #cbd5e1; flex-shrink: 0;
}
.cw-thread-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}
.cw-state-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: .7rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: .03em;
}
.cw-state-pool    { background: rgba(245,158,11,.14); color: #b45309; }
.cw-state-claimed { background: rgba(59,130,246,.14); color: #1d4ed8; }
.cw-state-closed  { background: rgba(100,116,139,.14); color: #475569; }
.cw-state-neutral { background: rgba(100,116,139,.10); color: #64748b; }

.cw-conn-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; border-radius: 999px;
    font-size: .7rem; font-weight: 700;
}
.cw-conn-badge .cw-conn-dot {
    width: 7px; height: 7px; border-radius: 999px;
}
.cw-conn-badge.is-on  { background: rgba(34,197,94,.14);  color: #15803d; }
.cw-conn-badge.is-on  .cw-conn-dot { background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,.18); }
.cw-conn-badge.is-off { background: rgba(148,163,184,.16); color: #475569; }
.cw-conn-badge.is-off .cw-conn-dot { background: #94a3b8; }

.cw-close-btn {
    display: inline-flex; align-items: center; gap: 6px;
    border: 1px solid #fecaca; background: #fef2f2; color: #b91c1c;
    padding: 6px 12px; border-radius: 8px;
    font-size: .78rem; font-weight: 600;
    cursor: pointer; transition: all .12s;
}
.cw-close-btn:hover { background: #fee2e2; }
.cw-close-btn i { font-size: 1rem; }

/* ---- MESSAGES ---- */
.cw-stage {
    flex: 1;
    overflow-y: auto;
    padding: 18px 24px 14px;
    background:
        radial-gradient(circle at 30% 10%, rgba(99,102,241,.05), transparent 30%),
        radial-gradient(circle at 80% 90%, rgba(16,185,129,.05), transparent 30%),
        #fafbfc;
}
.cw-date-sep {
    display: flex;
    justify-content: center;
    margin: 16px 0;
}
.cw-date-sep span {
    padding: 5px 12px;
    border-radius: 999px;
    background: rgba(255,255,255,.92);
    border: 1px solid #e2e8f0;
    color: #64748b;
    font-size: .7rem;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(15,23,42,.04);
}
.cw-msg {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    margin-bottom: 6px;
}
.cw-msg-out { justify-content: flex-end; }
.cw-msg-out .cw-msg-stack { align-items: flex-end; }
.cw-msg-avatar {
    width: 28px; height: 28px;
    border-radius: 999px;
    background: linear-gradient(135deg, #14b8a6, #059669);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: .62rem; font-weight: 700;
    flex-shrink: 0;
    margin-bottom: 4px;
}
.cw-msg-stack {
    max-width: 70%;
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.cw-bubble {
    padding: 9px 13px;
    border-radius: 14px;
    box-shadow: 0 1px 2px rgba(15,23,42,.04);
    font-size: .88rem;
    line-height: 1.5;
    word-break: break-word;
}
.cw-bubble-in {
    background: #fff;
    color: #0f172a;
    border: 1px solid #e6e8ee;
    border-bottom-left-radius: 4px;
}
.cw-bubble-out {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #fff;
    border-bottom-right-radius: 4px;
}
.cw-msg-body { white-space: pre-wrap; }
.cw-msg-foot {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
    margin-top: 4px;
    font-size: .65rem;
    opacity: .75;
}

/* ---- COMPOSER ---- */
.cw-composer-wrap {
    border-top: 1px solid #eef0f4;
    background: #fff;
    padding: 12px 18px 16px;
}
.cw-composer {
    display: flex;
    gap: 10px;
    align-items: flex-end;
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 14px;
    padding: 6px 6px 6px 8px;
    transition: all .15s;
}
.cw-composer:focus-within {
    border-color: #6366f1;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(99,102,241,.12);
}
.cw-textarea {
    flex: 1;
    border: none;
    background: transparent;
    resize: none;
    padding: 10px 6px;
    font-size: .88rem;
    line-height: 1.5;
    max-height: 160px;
    outline: none;
    color: #0f172a;
    font-family: inherit;
}
.cw-textarea::placeholder { color: #94a3b8; }
.cw-send {
    width: 40px; height: 40px;
    border: none;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #fff;
    border-radius: 10px;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.05rem;
    box-shadow: 0 4px 10px rgba(99,102,241,.3);
    transition: all .15s;
}
.cw-send:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 14px rgba(99,102,241,.4); }
.cw-send:disabled { opacity: .4; cursor: not-allowed; box-shadow: none; }

.cw-closed-banner {
    padding: 14px;
    text-align: center;
    background: #f1f5f9;
    color: #475569;
    font-size: .82rem;
    font-weight: 600;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.cw-claim-btn {
    width: 100%;
    padding: 12px 16px;
    border: none;
    border-radius: 12px;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #fff;
    font-size: .9rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    box-shadow: 0 6px 16px rgba(99,102,241,.28);
    transition: all .15s;
}
.cw-claim-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(99,102,241,.35); }

/* ---- RIGHT RAIL ---- */
.cw-side {
    background: #fff;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    min-height: 0;
}
.cw-profile {
    background: linear-gradient(160deg, #4338ca 0%, #6366f1 50%, #0ea5e9 100%);
    color: #fff;
    padding: 22px 18px 18px;
    border-radius: 18px;
    text-align: center;
    box-shadow: 0 12px 30px rgba(67,56,202,.22);
}
.cw-profile-avatar {
    width: 72px; height: 72px;
    border-radius: 999px;
    background: rgba(255,255,255,.18);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; font-weight: 700;
    margin: 0 auto 10px;
    border: 2px solid rgba(255,255,255,.3);
}
.cw-profile-name {
    font-size: 1.15rem; font-weight: 800;
    margin-bottom: 2px;
}
.cw-profile-company {
    font-size: .82rem; font-weight: 600;
    opacity: .9;
    margin-bottom: 4px;
    word-break: break-word;
}

.cw-side-card {
    background: #fff;
    border: 1px solid #e6e8ee;
    border-radius: 14px;
    overflow: hidden;
}
.cw-side-panel { padding: 14px 16px; }
.cw-meta-row {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: .8rem;
}
.cw-meta-row:last-child { border-bottom: 0; }
.cw-meta-row span { color: #64748b; }
.cw-meta-row strong { color: #0f172a; text-align: right; font-weight: 600; }

/* Spinner (matches the app's utility) */
.spinner {
    width: 22px; height: 22px;
    border: 2.5px solid #e2e8f0;
    border-top-color: #6366f1;
    border-radius: 999px;
    animation: cw-spin .8s linear infinite;
}
@keyframes cw-spin { to { transform: rotate(360deg); } }

/* ---- RESPONSIVE ---- */
@media (max-width: 1280px) {
    .cw-root { grid-template-columns: 280px minmax(0, 1fr) 300px; }
}
@media (max-width: 1100px) {
    .cw-root { grid-template-columns: 260px minmax(0, 1fr); }
    .cw-side { display: none; }
}
@media (max-width: 820px) {
    .cw-root { grid-template-columns: 1fr; }
    .cw-rail { display: none; }
}
</style>
@endpush
@endsection
