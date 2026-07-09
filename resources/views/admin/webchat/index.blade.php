@extends('layouts.admin')

@section('title', __('ui.webchat_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.webchat_page.title') }}</span>
@endsection

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
    ];
@endphp

<div x-data="webchatInbox()" x-init="init()" x-cloak class="wci">

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.webchat_page.title') }}</div>
            <div class="page-subtitle">{{ __('ui.webchat_page.subtitle') }}</div>
        </div>
        <div class="page-header-actions">
            <span class="wci-conn" :class="wsConnected ? 'wci-conn-on' : 'wci-conn-off'">
                <span class="wci-conn-dot"></span>
                <span x-text="wsConnected ? i18n.online : i18n.offline"></span>
            </span>
        </div>
    </div>

    <div class="wci-grid">

        {{-- ── LEFT: filter tabs + list ──────────────────────────────── --}}
        <div class="wci-col wci-col-list">
            <div class="wci-tabs">
                <template x-for="tab in tabs" :key="tab">
                    <button
                        type="button"
                        @click="setFilter(tab)"
                        :class="filter === tab ? 'active' : ''"
                        class="wci-tab">
                        <span x-text="i18n['tab_' + tab]"></span>
                        <template x-if="tab === 'pending' && pendingCount > 0">
                            <span class="wci-tab-badge" x-text="pendingCount"></span>
                        </template>
                    </button>
                </template>
            </div>

            <div class="wci-list" x-ref="list">
                <template x-if="loading && conversations.length === 0">
                    <div class="wci-empty">
                        <div class="spinner"></div>
                    </div>
                </template>

                <template x-if="!loading && conversations.length === 0">
                    <div class="wci-empty">
                        <i class="ri-chat-off-line wci-empty-icon"></i>
                        <div x-text="i18n.no_conversations"></div>
                    </div>
                </template>

                <template x-for="conv in conversations" :key="conv.uuid">
                    <div
                        class="wci-row"
                        :class="{
                            'wci-row-active': activeUuid === conv.uuid,
                            'wci-row-locked': isRowLocked(conv),
                        }"
                        @click="openRow(conv)">
                        <div class="wci-row-head">
                            <div class="wci-row-name" x-text="displayName(conv)"></div>
                            <div class="wci-row-time" x-text="timeAgo(conv.last_activity_at || conv.created_at)"></div>
                        </div>
                        <div class="wci-row-preview" x-text="conv.last_message_preview || i18n.no_messages_yet"></div>
                        <div class="wci-row-foot">
                            <span class="wci-badge" :class="statusBadgeClass(conv.status)" x-text="i18n['status_' + conv.status]"></span>
                            <template x-if="conv.status === 'pending'">
                                <button type="button" @click.stop="claim(conv.uuid)" class="wci-btn wci-btn-primary wci-btn-xs" x-text="i18n.claim_btn"></button>
                            </template>
                            <template x-if="conv.status === 'assigned' && conv.claimer">
                                <span class="wci-row-claimer" x-text="conv.claimer.id === myId ? i18n.claimed_by_you : (i18n.claimed_by_prefix + ' ' + conv.claimer.name)"></span>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ── CENTER: thread + composer ─────────────────────────────── --}}
        <div class="wci-col wci-col-thread">
            <template x-if="!activeUuid">
                <div class="wci-empty-thread">
                    <i class="ri-chat-3-line wci-empty-icon"></i>
                    <div x-text="i18n.select_conversation"></div>
                </div>
            </template>

            <template x-if="activeUuid">
                <div class="wci-thread-wrap">
                    <div class="wci-thread-head">
                        <div>
                            <div class="wci-thread-name" x-text="active.visitor?.name || i18n.anonymous"></div>
                            <div class="wci-thread-meta">
                                <span class="wci-badge" :class="statusBadgeClass(active.conversation?.status)" x-text="i18n['status_' + (active.conversation?.status || 'bot')]"></span>
                                <template x-if="active.conversation?.claimer && active.conversation?.status === 'assigned'">
                                    <span class="wci-thread-claimer" x-text="active.conversation.claimer.id === myId ? i18n.claimed_by_you : (i18n.claimed_by_prefix + ' ' + active.conversation.claimer.name)"></span>
                                </template>
                            </div>
                        </div>
                        <div class="wci-thread-actions">
                            <template x-if="canManageLock()">
                                <button type="button" @click="close()" class="wci-btn wci-btn-outline wci-btn-sm">
                                    <i class="ri-close-circle-line"></i>
                                    <span x-text="i18n.close_chat"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="wci-thread" x-ref="thread">
                        <template x-for="msg in messages" :key="msg.id">
                            <div class="wci-msg" :class="'wci-msg-' + msg.sender_type">
                                <template x-if="msg.sender_type === 'system'">
                                    <div class="wci-msg-system" x-text="msg.body"></div>
                                </template>
                                <template x-if="msg.sender_type !== 'system'">
                                    <div class="wci-msg-bubble">
                                        <div class="wci-msg-body" x-text="msg.body"></div>
                                        <div class="wci-msg-time" x-text="formatTime(msg.created_at)"></div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>

                    <div class="wci-composer">
                        <template x-if="!isMyClaim()">
                            <div class="wci-composer-locked">
                                <template x-if="active.conversation?.status === 'pending' || active.conversation?.status === 'bot'">
                                    <button type="button" @click="claim(active.conversation.uuid)" class="wci-btn wci-btn-primary">
                                        <i class="ri-hand-heart-line"></i>
                                        <span x-text="i18n.claim_to_reply"></span>
                                    </button>
                                </template>
                                <template x-if="active.conversation?.status === 'assigned'">
                                    <div class="wci-composer-locked-msg">
                                        <i class="ri-lock-line"></i>
                                        <span x-text="i18n.locked_by_agent"></span>
                                    </div>
                                </template>
                                <template x-if="active.conversation?.status === 'closed'">
                                    <div class="wci-composer-locked-msg">
                                        <i class="ri-close-circle-line"></i>
                                        <span x-text="i18n.chat_closed"></span>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <template x-if="isMyClaim()">
                            <div class="wci-composer-wrap">
                                <textarea
                                    x-model="composer"
                                    @keydown.enter.prevent="sendMessage()"
                                    :placeholder="i18n.composer_placeholder"
                                    rows="1"
                                    class="wci-composer-input"></textarea>
                                <button type="button" @click="sendMessage()" :disabled="!composer.trim() || sending" class="wci-btn wci-btn-primary wci-composer-send">
                                    <i class="ri-send-plane-2-line"></i>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        {{-- ── RIGHT: visitor info panel ─────────────────────────────── --}}
        <template x-if="activeUuid">
            <div class="wci-col wci-col-info">
                <div class="wci-info-title" x-text="i18n.visitor_info"></div>
                <template x-if="active.visitor?.name">
                    <div class="wci-info-row">
                        <div class="wci-info-label" x-text="i18n.name"></div>
                        <div class="wci-info-value" x-text="active.visitor.name"></div>
                    </div>
                </template>
                <template x-if="active.visitor?.email">
                    <div class="wci-info-row">
                        <div class="wci-info-label" x-text="i18n.email"></div>
                        <div class="wci-info-value" x-text="active.visitor.email"></div>
                    </div>
                </template>
                <template x-if="active.meta?.page_url">
                    <div class="wci-info-row">
                        <div class="wci-info-label" x-text="i18n.page"></div>
                        <div class="wci-info-value wci-info-truncate" x-text="active.meta.page_url" :title="active.meta.page_url"></div>
                    </div>
                </template>
                <template x-if="active.meta?.referrer">
                    <div class="wci-info-row">
                        <div class="wci-info-label" x-text="i18n.referrer"></div>
                        <div class="wci-info-value wci-info-truncate" x-text="active.meta.referrer" :title="active.meta.referrer"></div>
                    </div>
                </template>
                <template x-if="active.meta?.user_agent">
                    <div class="wci-info-row">
                        <div class="wci-info-label" x-text="i18n.browser"></div>
                        <div class="wci-info-value wci-info-truncate" x-text="active.meta.user_agent" :title="active.meta.user_agent"></div>
                    </div>
                </template>
                <template x-if="active.meta?.ip">
                    <div class="wci-info-row">
                        <div class="wci-info-label" x-text="i18n.ip"></div>
                        <div class="wci-info-value" x-text="active.meta.ip"></div>
                    </div>
                </template>
                <template x-if="active.conversation?.created_at">
                    <div class="wci-info-row">
                        <div class="wci-info-label" x-text="i18n.started_at"></div>
                        <div class="wci-info-value" x-text="formatDateTime(active.conversation.created_at)"></div>
                    </div>
                </template>
            </div>
        </template>

    </div>
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

        get pendingCount() {
            return this.conversations.filter(c => c.status === 'pending').length;
        },

        init() {
            this.loadList();
            this.subscribePresence();

            // Poll list every 8s when WebSocket is down, 30s when it's up.
            // The tick body reads the current wsConnected each firing so a
            // reconnect naturally throttles this back down.
            this._pollTimer = setInterval(() => {
                this.loadList(true);
            }, 8000);

            // Track echo connection state
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

        // Start a 5s poll of the currently-open thread when the WebSocket
        // is not connected. Stops itself as soon as Echo comes back online.
        _startThreadPoll() {
            if (this._threadPoll) return;
            this._threadPoll = setInterval(() => {
                if (this.wsConnected || !this.activeUuid) return;
                this._pollThreadDelta();
            }, 5000);
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
                // Sync status changes too (e.g. someone else closed it)
                if (data.conversation && this.active.conversation && data.conversation.status !== this.active.conversation.status) {
                    this.active.conversation = data.conversation;
                }
            } catch (e) { /* silent — next tick will retry */ }
        },

        // ─── list ──────────────────────────────────────────────────────
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

        // ─── open + fetch full conversation ────────────────────────────
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

                // Subscribe to the private thread channel — visitor auth for
                // widget side happens through /api/webchat/broadcasting/auth;
                // agents authorize via the framework's /broadcasting/auth.
                this.subscribeThread(conv.uuid);

                // HTTP-poll fallback for missed messages when Reverb is down.
                // The loop itself checks wsConnected on every tick, so it goes
                // idle automatically once WebSocket subscribes.
                this._startThreadPoll();

                // Mark visitor messages as read (only for the claimer)
                if (this.isMyClaim()) this.markRead(conv.uuid);

                await this.$nextTick();
                this.scrollThreadBottom();
            } catch (e) {
                console.error('[webchat] openRow failed', e);
                this.activeUuid = null;
                window.showToast?.('error', 'Could not open conversation');
            }
        },

        // ─── mutations ─────────────────────────────────────────────────
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

                // Patch list row locally
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

        async close() {
            if (!this.activeUuid) return;
            if (!window.confirm(this.i18n.close_confirm)) return;

            try {
                const r = await this.post(this.closeUrlTpl.replace('__UUID__', this.activeUuid));
                if (!r.ok) throw new Error('HTTP ' + r.status);
                window.showToast?.('success', this.i18n.close_success);
                // The Closed broadcast + MessageSent will patch the UI. Refresh list too.
                this.loadList(true);
            } catch (e) {
                console.error('[webchat] close failed', e);
                window.showToast?.('error', this.i18n.close_error);
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

                // Append locally with sender name so it renders instantly.
                // The MessageSent broadcast that follows will be de-duplicated
                // in handleThreadMessage by id.
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

        // ─── real-time subscriptions ───────────────────────────────────
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

        // ─── broadcast handlers ────────────────────────────────────────
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

        // ─── helpers ───────────────────────────────────────────────────
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

        statusBadgeClass(status) {
            return ({
                bot:      'wci-badge-gray',
                pending:  'wci-badge-orange',
                assigned: 'wci-badge-blue',
                closed:   'wci-badge-gray',
            })[status] || 'wci-badge-gray';
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
    /* ── WebChat inbox (wci-) scoped styles ─────────────────────────── */
    .wci { display: flex; flex-direction: column; height: 100%; }

    .wci-conn {
        display: inline-flex; align-items: center; gap: .5rem;
        padding: .25rem .625rem; border-radius: 999px;
        font-size: .75rem; font-weight: 600;
    }
    .wci-conn-dot { width: 8px; height: 8px; border-radius: 50%; }
    .wci-conn-on  { background: rgba(16,185,129,.1); color: var(--green); }
    .wci-conn-on .wci-conn-dot  { background: var(--green); box-shadow: 0 0 0 3px rgba(16,185,129,.15); }
    .wci-conn-off { background: rgba(107,114,128,.1); color: var(--text-muted); }
    .wci-conn-off .wci-conn-dot { background: var(--text-muted); }

    .wci-grid {
        display: grid;
        grid-template-columns: 340px 1fr 300px;
        gap: 1rem;
        margin-top: 1rem;
        min-height: calc(100vh - 220px);
    }
    @media (max-width: 1280px) { .wci-grid { grid-template-columns: 320px 1fr; } .wci-col-info { display: none; } }
    @media (max-width: 900px)  { .wci-grid { grid-template-columns: 1fr; } }

    .wci-col {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: var(--radius-lg);
        display: flex; flex-direction: column;
        overflow: hidden;
    }

    /* Tabs */
    .wci-tabs {
        display: flex; gap: .25rem;
        padding: .625rem; border-bottom: 1px solid var(--card-border);
        background: var(--page-bg);
    }
    .wci-tab {
        flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: .375rem;
        padding: .5rem .75rem; border-radius: var(--radius);
        font-size: .8125rem; font-weight: 500; color: var(--text-secondary);
        background: transparent; border: none; cursor: pointer;
        transition: var(--transition);
    }
    .wci-tab:hover  { background: var(--card-bg); color: var(--text-primary); }
    .wci-tab.active { background: var(--brand); color: #fff; }
    .wci-tab-badge {
        background: rgba(255,255,255,.25); color: inherit;
        padding: 1px 6px; border-radius: 999px; font-size: .6875rem; font-weight: 700;
        min-width: 1.25rem; text-align: center;
    }
    .wci-tab:not(.active) .wci-tab-badge { background: var(--orange-bg); color: var(--orange); }

    /* List */
    .wci-list {
        flex: 1; overflow-y: auto; padding: .25rem;
    }
    .wci-row {
        padding: .75rem .875rem; border-radius: var(--radius);
        cursor: pointer; transition: var(--transition);
        border: 1px solid transparent;
    }
    .wci-row:hover        { background: var(--page-bg); }
    .wci-row-active       { background: var(--brand-xlight); border-color: rgba(16,185,129,.25); }
    .wci-row-active:hover { background: var(--brand-xlight); }
    .wci-row-locked       { opacity: .65; cursor: not-allowed; }

    .wci-row-head    { display: flex; justify-content: space-between; align-items: center; gap: .5rem; }
    .wci-row-name    { font-weight: 600; font-size: .875rem; color: var(--text-primary); flex: 1; min-width: 0;
                       overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
    .wci-row-time    { font-size: .6875rem; color: var(--text-muted); flex-shrink: 0; }
    .wci-row-preview { font-size: .8125rem; color: var(--text-secondary); margin: .25rem 0;
                       overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
    .wci-row-foot    { display: flex; align-items: center; gap: .5rem; }
    .wci-row-claimer { font-size: .75rem; color: var(--text-muted); }

    /* Badges */
    .wci-badge {
        display: inline-flex; align-items: center;
        padding: 2px 8px; border-radius: 999px;
        font-size: .6875rem; font-weight: 600;
    }
    .wci-badge-gray   { background: var(--gray-bg);   color: var(--gray); }
    .wci-badge-orange { background: var(--orange-bg); color: var(--orange); }
    .wci-badge-blue   { background: var(--blue-bg);   color: var(--blue); }
    .wci-badge-green  { background: var(--green-bg);  color: var(--green); }

    /* Empty states */
    .wci-empty, .wci-empty-thread {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: .75rem; padding: 2rem 1rem; color: var(--text-muted);
        flex: 1; text-align: center;
    }
    .wci-empty-icon { font-size: 2.5rem; opacity: .35; }

    /* Thread */
    .wci-thread-wrap { display: flex; flex-direction: column; height: 100%; min-height: 0; }
    .wci-thread-head {
        display: flex; align-items: center; justify-content: space-between; gap: 1rem;
        padding: .875rem 1.125rem; border-bottom: 1px solid var(--card-border);
        background: var(--card-bg);
    }
    .wci-thread-name    { font-size: .9375rem; font-weight: 600; color: var(--text-primary); }
    .wci-thread-meta    { display: flex; align-items: center; gap: .5rem; margin-top: .25rem; }
    .wci-thread-claimer { font-size: .75rem; color: var(--text-muted); }
    .wci-thread-actions { display: flex; gap: .5rem; }

    .wci-thread {
        flex: 1; overflow-y: auto;
        padding: 1rem 1.25rem;
        display: flex; flex-direction: column; gap: .5rem;
        background: var(--page-bg);
    }
    .wci-msg              { display: flex; }
    .wci-msg-visitor      { justify-content: flex-start; }
    .wci-msg-agent        { justify-content: flex-end; }
    .wci-msg-system       { justify-content: center; }
    .wci-msg-system > *   { }
    .wci-msg-system-single-msg { }

    .wci-msg-bubble {
        max-width: 70%;
        padding: .5rem .75rem;
        border-radius: 12px;
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        box-shadow: var(--card-shadow);
    }
    .wci-msg-agent .wci-msg-bubble {
        background: var(--brand);
        color: #fff;
        border-color: var(--brand);
    }
    .wci-msg-body    { font-size: .875rem; line-height: 1.4; white-space: pre-wrap; word-break: break-word; }
    .wci-msg-time    { font-size: .6875rem; opacity: .7; margin-top: .25rem; text-align: right; }
    .wci-msg-system > div, .wci-msg-system-single-msg {
        font-size: .75rem; color: var(--text-muted);
        background: var(--card-bg); padding: .25rem .625rem; border-radius: 999px;
        border: 1px dashed var(--card-border);
    }
    .wci-msg-system { align-items: center; }

    /* Composer */
    .wci-composer {
        border-top: 1px solid var(--card-border);
        padding: .75rem 1rem;
        background: var(--card-bg);
    }
    .wci-composer-wrap {
        display: flex; gap: .5rem; align-items: flex-end;
    }
    .wci-composer-input {
        flex: 1;
        min-height: 40px; max-height: 120px;
        padding: .5rem .75rem;
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        font-family: inherit; font-size: .875rem;
        resize: none; outline: none;
    }
    .wci-composer-input:focus { border-color: var(--brand); }
    .wci-composer-send { padding: .5rem .875rem; }
    .wci-composer-locked {
        display: flex; align-items: center; justify-content: center; gap: .5rem;
        padding: .375rem;
    }
    .wci-composer-locked-msg {
        display: inline-flex; align-items: center; gap: .5rem;
        color: var(--text-muted); font-size: .8125rem;
        padding: .5rem;
    }

    /* Info panel */
    .wci-col-info { padding: 1rem 1.125rem; }
    .wci-info-title {
        font-size: .75rem; font-weight: 700; text-transform: uppercase;
        color: var(--text-muted); letter-spacing: .5px; margin-bottom: .75rem;
    }
    .wci-info-row { padding: .5rem 0; border-bottom: 1px solid var(--card-border); }
    .wci-info-row:last-child { border-bottom: none; }
    .wci-info-label {
        font-size: .6875rem; font-weight: 600; color: var(--text-muted);
        text-transform: uppercase; letter-spacing: .3px; margin-bottom: .125rem;
    }
    .wci-info-value    { font-size: .8125rem; color: var(--text-primary); }
    .wci-info-truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    /* Buttons — self-contained; the app's .btn selectors aren't guaranteed to load here */
    .wci-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: .375rem;
        padding: .5rem .875rem; border-radius: var(--radius);
        font-size: .8125rem; font-weight: 500;
        border: 1px solid transparent; cursor: pointer; transition: var(--transition);
    }
    .wci-btn:disabled { opacity: .5; cursor: not-allowed; }
    .wci-btn-primary { background: var(--brand); color: #fff; }
    .wci-btn-primary:hover:not(:disabled) { background: var(--brand-dark); }
    .wci-btn-outline { background: transparent; color: var(--text-primary); border-color: var(--card-border); }
    .wci-btn-outline:hover:not(:disabled) { background: var(--page-bg); }
    .wci-btn-xs { padding: .25rem .625rem; font-size: .6875rem; }
    .wci-btn-sm { padding: .375rem .75rem; font-size: .75rem; }
</style>
@endpush
@endsection
