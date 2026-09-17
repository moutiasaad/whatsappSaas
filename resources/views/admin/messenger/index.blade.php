@extends('layouts.admin')

@section('title', 'Messenger')

@section('breadcrumb')
    <span>Messenger</span>
@endsection

@push('styles')
<style>
    .msg-shell { display: grid; grid-template-columns: 340px 1fr; gap: 1rem; height: calc(100vh - 220px); min-height: 500px; }
    .msg-list  { background: #fff; border: 1px solid var(--card-border, #e5e7eb); border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; }
    .msg-list-tabs { display: flex; border-bottom: 1px solid var(--card-border, #e5e7eb); }
    .msg-list-tabs button { flex: 1; padding: .75rem .5rem; border: 0; background: transparent; cursor: pointer; font-size: .8125rem; color: var(--text-muted, #64748b); border-bottom: 2px solid transparent; }
    .msg-list-tabs button.active { color: var(--brand, #0f7e7a); border-bottom-color: var(--brand, #0f7e7a); font-weight: 600; }
    .msg-list-scroll { overflow-y: auto; flex: 1; }
    .msg-row { padding: .75rem 1rem; border-bottom: 1px solid var(--card-border, #f1f5f9); cursor: pointer; display: flex; gap: .625rem; align-items: flex-start; }
    .msg-row:hover { background: #f8fafc; }
    .msg-row.active { background: #ecfdf5; border-left: 3px solid var(--brand, #0f7e7a); padding-left: calc(1rem - 3px); }
    .msg-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg,#a78bfa,#7c3aed); color: #fff; display: grid; place-items: center; font-weight: 600; font-size: .8125rem; flex-shrink: 0; overflow: hidden; }
    .msg-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .msg-meta { min-width: 0; flex: 1; }
    .msg-name { font-weight: 600; font-size: .875rem; color: var(--text-primary, #0f172a); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .msg-preview { font-size: .75rem; color: var(--text-muted, #64748b); margin-top: .125rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .msg-status { display: inline-block; padding: .0625rem .375rem; font-size: .625rem; border-radius: 999px; margin-top: .25rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }
    .msg-status.bot { background: #dbeafe; color: #1e40af; }
    .msg-status.pending { background: #fef3c7; color: #92400e; }
    .msg-status.assigned { background: #dcfce7; color: #14532d; }
    .msg-status.closed { background: #f3f4f6; color: #4b5563; }

    .msg-panel { background: #fff; border: 1px solid var(--card-border, #e5e7eb); border-radius: 12px; display: flex; flex-direction: column; overflow: hidden; }
    .msg-panel-header { padding: 1rem 1.25rem; border-bottom: 1px solid var(--card-border, #e5e7eb); display: flex; align-items: center; gap: .75rem; }
    .msg-panel-actions { margin-left: auto; display: flex; gap: .375rem; }
    .msg-panel-empty { flex: 1; display: grid; place-items: center; color: var(--text-muted, #64748b); font-size: .875rem; text-align: center; padding: 2rem; }
    .msg-thread { flex: 1; overflow-y: auto; padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: .5rem; background: #f8fafc; }
    .msg-bubble { max-width: 78%; padding: .5rem .875rem; border-radius: 14px; font-size: .875rem; line-height: 1.4; word-break: break-word; white-space: pre-wrap; }
    .msg-bubble .who { font-size: .625rem; color: var(--text-muted, #64748b); margin-bottom: .125rem; text-transform: uppercase; letter-spacing: .05em; font-weight: 600; }
    .msg-in  { align-self: flex-start; background: #fff; border: 1px solid var(--card-border, #e5e7eb); }
    .msg-out { align-self: flex-end; background: var(--brand, #0f7e7a); color: #fff; }
    .msg-out .who { color: rgba(255,255,255,.75); }
    .msg-bot { align-self: flex-end; background: #7c3aed; color: #fff; }
    .msg-bot .who { color: rgba(255,255,255,.75); }
    .msg-system { align-self: center; background: transparent; color: var(--text-muted, #64748b); font-size: .75rem; font-style: italic; padding: .125rem 0; }
    .msg-time { font-size: .6875rem; color: var(--text-muted, #64748b); margin-top: .125rem; }

    .msg-composer { padding: .75rem 1rem; border-top: 1px solid var(--card-border, #e5e7eb); display: flex; gap: .5rem; align-items: flex-end; }
    .msg-composer textarea { flex: 1; border: 1px solid var(--card-border, #d1d5db); border-radius: 10px; padding: .5rem .75rem; font-size: .875rem; font-family: inherit; resize: none; min-height: 40px; max-height: 120px; }
    .msg-composer textarea:focus { outline: none; border-color: var(--brand, #0f7e7a); }
    .msg-composer button { padding: .5rem 1rem; }
    .msg-window-warn { padding: .5rem 1rem; background: #fef3c7; color: #92400e; font-size: .75rem; text-align: center; border-top: 1px solid #fde68a; }

    @media (max-width: 900px) {
        .msg-shell { grid-template-columns: 1fr; height: auto; }
        .msg-panel { min-height: 400px; }
    }
</style>
@endpush

@section('content')
<div x-data="messengerInbox()" x-init="init()">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:.75rem">
        <div>
            <h1 style="font-size:1.375rem;font-weight:700;color:var(--text-primary)">Messenger</h1>
            <p style="font-size:.875rem;color:var(--text-muted);margin-top:.125rem">Facebook Messenger conversations</p>
        </div>
        <div style="display:flex;gap:.5rem">
            @if(auth()->user()->isAdmin())
                <a href="{{ route('tenant_admin.messenger.settings') }}" class="btn btn-outline">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:.375rem"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    Settings
                </a>
            @endif
            <button @click="reload()" class="btn btn-outline" type="button" :disabled="loading">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15A9 9 0 1 1 5.64 5.64L23 10"/></svg>
                <span x-text="loading ? 'Loading…' : 'Refresh'"></span>
            </button>
        </div>
    </div>

    <div class="msg-shell">
        <!-- Left: conversation list -->
        <div class="msg-list">
            <div class="msg-list-tabs">
                <template x-for="t in tabs" :key="t.key">
                    <button :class="{ active: filter === t.key }" @click="setFilter(t.key)" x-text="t.label"></button>
                </template>
            </div>
            <div class="msg-list-scroll">
                <template x-if="!loading && conversations.length === 0">
                    <div style="padding:2rem 1rem;text-align:center;color:var(--text-muted);font-size:.8125rem">
                        No conversations in this view yet.
                    </div>
                </template>
                <template x-for="c in conversations" :key="c.uuid">
                    <div class="msg-row" :class="{ active: current?.uuid === c.uuid }" @click="open(c.uuid)">
                        <div class="msg-avatar">
                            <template x-if="c.contact_avatar_url">
                                <img :src="c.contact_avatar_url" :alt="c.contact_name" onerror="this.style.display='none'">
                            </template>
                            <template x-if="!c.contact_avatar_url">
                                <span x-text="initials(c.contact_name)"></span>
                            </template>
                        </div>
                        <div class="msg-meta">
                            <div class="msg-name" x-text="c.contact_name"></div>
                            <div class="msg-preview" x-text="c.last_message_preview || '(no messages yet)'"></div>
                            <span class="msg-status" :class="c.status" x-text="c.status"></span>
                            <template x-if="c.claimer">
                                <span style="font-size:.625rem;color:var(--text-muted);margin-left:.375rem" x-text="'· ' + c.claimer.name"></span>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Right: current conversation panel -->
        <div class="msg-panel">
            <template x-if="!current">
                <div class="msg-panel-empty">
                    <div>
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:.3;margin:0 auto"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        <div style="margin-top:.75rem">Pick a conversation on the left</div>
                    </div>
                </div>
            </template>

            <template x-if="current">
                <div style="display:contents">
                    <div class="msg-panel-header">
                        <div class="msg-avatar" style="width:32px;height:32px;font-size:.75rem">
                            <template x-if="current.contact_avatar_url">
                                <img :src="current.contact_avatar_url" :alt="current.contact_name" onerror="this.style.display='none'">
                            </template>
                            <template x-if="!current.contact_avatar_url">
                                <span x-text="initials(current.contact_name)"></span>
                            </template>
                        </div>
                        <div style="min-width:0">
                            <div style="font-weight:600;font-size:.875rem" x-text="current.contact_name"></div>
                            <div style="font-size:.6875rem;color:var(--text-muted)" x-text="current.page?.name + ' · ' + current.status"></div>
                        </div>
                        <div class="msg-panel-actions">
                            <template x-if="current.status === 'bot' || current.status === 'pending'">
                                <button class="btn btn-sm btn-primary" @click="claim()" :disabled="acting">Claim</button>
                            </template>
                            <template x-if="current.status === 'assigned'">
                                <button class="btn btn-sm btn-outline" @click="release()" :disabled="acting">Release</button>
                            </template>
                            <template x-if="current.status !== 'closed'">
                                <button class="btn btn-sm btn-outline" @click="closeConv()" :disabled="acting">Close</button>
                            </template>
                        </div>
                    </div>

                    <div class="msg-thread" x-ref="thread">
                        <template x-for="m in messages" :key="m.id">
                            <div class="msg-bubble" :class="bubbleClass(m)">
                                <div class="who" x-text="senderLabel(m)"></div>
                                <div x-text="m.body || '(attachment)'"></div>
                                <div class="msg-time" x-text="formatTime(m.created_at)"></div>
                            </div>
                        </template>
                    </div>

                    <template x-if="current.status !== 'closed' && !current.within_window">
                        <div class="msg-window-warn">
                            Outside Meta's 24-hour messaging window. Ask the customer to send a new message before you can reply.
                        </div>
                    </template>

                    <template x-if="current.status !== 'closed' && current.within_window">
                        <form class="msg-composer" @submit.prevent="send()">
                            <textarea x-model="draft" @keydown.enter.prevent.exact="send()" placeholder="Type a reply…" rows="1" :disabled="sending"></textarea>
                            <button type="submit" class="btn btn-primary" :disabled="sending || !draft.trim()">
                                <span x-text="sending ? 'Sending…' : 'Send'"></span>
                            </button>
                        </form>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function messengerInbox() {
    return {
        conversations: [],
        current: null,
        messages: [],
        filter: 'all',
        loading: false,
        acting: false,
        sending: false,
        draft: '',
        pollTimer: null,
        tabs: [
            { key: 'all',     label: 'All' },
            { key: 'pending', label: 'Pending' },
            { key: 'mine',    label: 'Mine' },
            { key: 'closed',  label: 'Closed' },
        ],

        init() {
            this.reload();
            // Light polling — Phase 6 doesn't yet broadcast on Reverb so
            // this fills the gap for real-time. 8s balances freshness vs
            // load. Replace with pusher subscription in a later iteration.
            this.pollTimer = setInterval(() => { this.reload({ silent: true }); if (this.current) this.refreshCurrent(); }, 8000);
        },

        setFilter(key) { this.filter = key; this.reload(); },

        async reload(opts = {}) {
            if (!opts.silent) this.loading = true;
            try {
                const r = await fetch(`{{ route('tenant_admin.messenger.index') }}?filter=${encodeURIComponent(this.filter)}`, { headers: { Accept: 'application/json' }});
                const j = await r.json();
                this.conversations = j.data || [];
            } catch (e) { console.error(e); }
            this.loading = false;
        },

        async open(uuid) {
            try {
                const r = await fetch(`/tenant-admin/messenger/conversations/${uuid}`, { headers: { Accept: 'application/json' }});
                const j = await r.json();
                this.current = j.conversation;
                this.messages = j.messages;
                this.$nextTick(() => this.scrollToBottom());
                this.markRead();
            } catch (e) { console.error(e); }
        },

        async refreshCurrent() {
            if (!this.current) return;
            const lastId = this.messages.length ? this.messages[this.messages.length - 1].id : 0;
            try {
                const r = await fetch(`/tenant-admin/messenger/conversations/${this.current.uuid}?after=${lastId}`, { headers: { Accept: 'application/json' }});
                const j = await r.json();
                if (j.messages.length) {
                    this.messages.push(...j.messages);
                    this.$nextTick(() => this.scrollToBottom());
                    this.markRead();
                }
                this.current = j.conversation;
            } catch (e) { /* silent */ }
        },

        async claim() {
            if (!this.current) return;
            this.acting = true;
            try {
                const r = await fetch(`/tenant-admin/messenger/conversations/${this.current.uuid}/claim`, {
                    method: 'POST', headers: this.jsonHeaders()
                });
                if (r.ok) { await this.open(this.current.uuid); this.reload({silent: true}); }
                else alert('Could not claim — ' + (await r.text()));
            } finally { this.acting = false; }
        },

        async release() {
            if (!this.current) return;
            this.acting = true;
            try {
                await fetch(`/tenant-admin/messenger/conversations/${this.current.uuid}/release`, { method: 'POST', headers: this.jsonHeaders() });
                await this.open(this.current.uuid);
                this.reload({silent: true});
            } finally { this.acting = false; }
        },

        async closeConv() {
            if (!this.current || !confirm('Close this conversation?')) return;
            this.acting = true;
            try {
                await fetch(`/tenant-admin/messenger/conversations/${this.current.uuid}/close`, {
                    method: 'POST', headers: this.jsonHeaders(), body: JSON.stringify({})
                });
                await this.open(this.current.uuid);
                this.reload({silent: true});
            } finally { this.acting = false; }
        },

        async markRead() {
            if (!this.current) return;
            try { await fetch(`/tenant-admin/messenger/conversations/${this.current.uuid}/read`, { method: 'POST', headers: this.jsonHeaders() }); } catch {}
        },

        async send() {
            const body = this.draft.trim();
            if (!body || !this.current || this.sending) return;
            this.sending = true;
            try {
                const r = await fetch(`/tenant-admin/messenger/conversations/${this.current.uuid}/reply`, {
                    method: 'POST', headers: this.jsonHeaders(), body: JSON.stringify({ body })
                });
                if (r.ok) {
                    this.draft = '';
                    await this.refreshCurrent();
                    this.reload({silent: true});
                } else {
                    const j = await r.json().catch(() => ({}));
                    alert(j.message || 'Send failed');
                }
            } finally { this.sending = false; }
        },

        jsonHeaders() {
            return {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'X-Requested-With': 'XMLHttpRequest',
            };
        },

        initials(name) {
            if (!name) return '?';
            const parts = name.trim().split(/\s+/);
            return (parts[0]?.[0] || '') + (parts[1]?.[0] || '');
        },

        bubbleClass(m) {
            if (m.sender_type === 'visitor') return 'msg-in';
            if (m.sender_type === 'bot')     return 'msg-bot';
            if (m.sender_type === 'system')  return 'msg-system';
            return 'msg-out';
        },

        senderLabel(m) {
            if (m.sender_type === 'visitor') return this.current?.contact_name || 'Visitor';
            if (m.sender_type === 'bot')     return 'AI';
            if (m.sender_type === 'system')  return 'System';
            return m.sender?.name || 'Agent';
        },

        formatTime(iso) {
            if (!iso) return '';
            try { return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
            catch { return ''; }
        },

        scrollToBottom() {
            const t = this.$refs.thread;
            if (t) t.scrollTop = t.scrollHeight;
        },
    };
}
</script>
@endsection
