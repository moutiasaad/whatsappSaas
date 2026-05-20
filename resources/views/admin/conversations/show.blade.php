@extends('layouts.admin')

@section('title', 'Conversation')

@section('breadcrumb')
    @php $panelPrefix = auth()->user()->routeNamePrefix(); @endphp
    <a href="{{ route($panelPrefix . '.conversations.index') }}" style="color:var(--text-secondary);text-decoration:none">Conversations</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ $conversation->customer->displayNameOrPhone }}</span>
@endsection

@section('content')
<div x-data="conversationPro()" x-init="init()">
    <div class="page-header" style="margin-bottom:1rem;">
        <div class="page-header-left">
            <div class="page-title">Conversation #{{ $conversation->id }}</div>
            <div class="page-subtitle">{{ $conversation->customer->displayNameOrPhone }} - {{ $conversation->customer->phone_e164 }}</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route($panelPrefix . '.conversations.index') }}" class="btn btn-outline btn-sm">
                <i class="ri-arrow-left-line"></i> Back
            </a>
            <a href="{{ route($panelPrefix . '.customers.show', $conversation->customer) }}" class="btn btn-outline btn-sm">
                <i class="ri-user-line"></i> Customer
            </a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 340px;gap:1rem;height:calc(100vh - 11.5rem);min-height:0;">
        <div class="card" style="padding:0;display:flex;flex-direction:column;overflow:hidden;">
            <div style="padding:12px 16px;border-bottom:1px solid var(--card-border);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:.75rem;">
                    <div class="conv-avatar">{{ strtoupper(substr($conversation->customer->displayNameOrPhone, 0, 2)) }}</div>
                    <div>
                        <div style="font-weight:700;font-size:.95rem;">{{ $conversation->customer->displayNameOrPhone }}</div>
                        <div style="font-size:.8rem;color:var(--text-muted);">{{ $conversation->instance->name }} - {{ $conversation->team?->name ?? 'No team' }}</div>
                    </div>
                </div>

                <div style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;">
                    <span x-show="state === 'pool'" class="badge badge-orange"><i class="ri-time-line"></i> Pool</span>
                    <span x-show="state === 'claimed'" class="badge badge-blue"><i class="ri-user-line"></i> Claimed</span>
                    <span x-show="state === 'closed'" class="badge badge-gray"><i class="ri-check-double-line"></i> Closed</span>
                    <span x-show="aiSuspended" class="badge badge-gray">AI Off</span>

                    @can('claim', $conversation)
                    <template x-if="state === 'pool'">
                        <button @click="claim()" :disabled="actionLoading" class="btn btn-primary btn-sm">
                            <i class="ri-hand-coin-line"></i> Claim
                        </button>
                    </template>
                    @endcan

                    <template x-if="state === 'claimed' && canAct">
                        <div style="display:flex;align-items:center;gap:.35rem;">
                            @can('reassign', $conversation)
                            <button @click="showReassign = true" class="btn btn-outline btn-sm">
                                <i class="ri-user-settings-line"></i> Reassign
                            </button>
                            @endcan
                            @can('release', $conversation)
                            <button @click="release()" class="btn btn-outline btn-sm" :disabled="actionLoading">
                                <i class="ri-reply-line"></i> Release
                            </button>
                            @endcan
                            @can('close', $conversation)
                            <button @click="closeConv()" class="btn btn-danger btn-sm" :disabled="actionLoading">
                                <i class="ri-close-circle-line"></i> Close
                            </button>
                            @endcan
                        </div>
                    </template>

                    @can('reopen', $conversation)
                    <template x-if="state === 'closed'">
                        <button @click="reopen()" class="btn btn-outline btn-sm" :disabled="actionLoading">
                            <i class="ri-refresh-line"></i> Reopen
                        </button>
                    </template>
                    @endcan

                    @can('toggleAi', $conversation)
                    <template x-if="state !== 'closed'">
                        <button @click="toggleAi()" class="btn btn-outline btn-sm" :disabled="actionLoading">
                            <i class="ri-robot-2-line"></i>
                            <span x-text="aiSuspended ? 'Resume AI' : 'Suspend AI'"></span>
                        </button>
                    </template>
                    @endcan
                </div>
            </div>

            <div id="messages-scroll" style="flex:1;overflow-y:auto;padding:14px;background:linear-gradient(180deg,#f8fafc 0%, #f0fdf4 100%);" >
                <div x-show="hasMoreMessages" style="text-align:center;margin-bottom:.5rem;">
                    <button @click="loadMoreMessages()" :disabled="loadingMessages" class="btn btn-ghost btn-sm">
                        <span x-show="!loadingMessages">Load earlier messages</span>
                        <span x-show="loadingMessages">Loading...</span>
                    </button>
                </div>

                <template x-for="msg in messages" :key="msg.id">
                    <div style="display:flex;margin-bottom:10px;" :style="msg.direction === 'out' ? 'justify-content:flex-end;' : 'justify-content:flex-start;'">
                        <div :style="bubbleStyle(msg)" style="max-width:76%;padding:10px 12px;border-radius:14px;box-shadow:0 1px 2px rgba(0,0,0,.04);">
                            <div x-show="msg.ai_metadata?.is_note" style="font-size:.68rem;color:#b45309;font-weight:700;margin-bottom:4px;">INTERNAL NOTE</div>
                            <div x-show="msg.author_type === 'ai'" style="font-size:.68rem;color:#0f766e;font-weight:700;margin-bottom:4px;">AI REPLY</div>
                            <div style="font-size:.87rem;line-height:1.45;white-space:pre-wrap;word-break:break-word;" x-text="msg.body || '-'"></div>
                            <div style="font-size:.68rem;opacity:.7;text-align:right;margin-top:4px;" x-text="formatTime(msg.sent_at)"></div>
                        </div>
                    </div>
                </template>
                <div id="scroll-anchor"></div>
            </div>

            <div x-show="state !== 'closed'" style="padding:10px 12px;border-top:1px solid var(--card-border);background:#fff;">
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                    <button type="button" class="btn btn-ghost btn-sm" @click="setQuickReply('Hello! Thanks for reaching out. How can I help you today?')">Greeting</button>
                    <button type="button" class="btn btn-ghost btn-sm" @click="setQuickReply('Thanks for waiting. I am checking this now and will update you shortly.')">Follow-up</button>
                    <button type="button" class="btn btn-ghost btn-sm" @click="setQuickReply('This issue is now resolved. Please confirm on your side.')">Resolved</button>
                    <button type="button" class="btn btn-ghost btn-sm" @click="isNote = !isNote" :style="isNote ? 'color:#b45309;background:#fef3c7;' : ''">
                        <i class="ri-sticky-note-line"></i> Note mode
                    </button>
                </div>

                <div style="display:flex;gap:8px;align-items:flex-end;">
                    <textarea x-model="draft" rows="1" @keydown.ctrl.enter.prevent="send()" @keydown.meta.enter.prevent="send()" @input="autoResize($el)"
                              :placeholder="isNote ? 'Write internal note... (Ctrl+Enter)' : 'Type a message... (Ctrl+Enter)'"
                              style="flex:1;resize:none;border:1.5px solid var(--card-border);border-radius:10px;padding:10px 12px;font-size:.875rem;line-height:1.45;max-height:150px;outline:none;"></textarea>
                    <button @click="send()" :disabled="!draft.trim() || sending || state !== 'claimed'" class="btn btn-primary" style="height:40px;min-width:90px;">
                        <span x-show="!sending"><i class="ri-send-plane-2-line"></i> Send</span>
                        <span x-show="sending"><span class="btn-spinner"></span></span>
                    </button>
                </div>
                <div x-show="state === 'pool'" style="font-size:.75rem;color:var(--text-muted);margin-top:6px;">Claim this conversation to send replies.</div>
            </div>

            <div x-show="state === 'closed'" style="padding:10px 12px;border-top:1px solid var(--card-border);background:var(--page-bg);font-size:.8rem;color:var(--text-muted);text-align:center;">
                Conversation is closed. Reopen it to continue.
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:1rem;min-height:0;">
            <div class="card">
                <div class="card-header" style="padding-bottom:10px;">
                    <div class="card-title">Workspace</div>
                </div>
                <div style="padding:0 14px 14px;">
                    <div class="tab-nav" style="margin-bottom:12px;">
                        <button class="tab-btn" :class="{ 'active': sideTab === 'details' }" @click="sideTab = 'details'">Details</button>
                        <button class="tab-btn" :class="{ 'active': sideTab === 'timeline' }" @click="sideTab = 'timeline'">Timeline</button>
                    </div>

                    <div x-show="sideTab === 'details'" class="tab-panel">
                        <div class="meta-row"><span>Tenant</span><strong>{{ $conversation->tenant?->name ?? '-' }}</strong></div>
                        <div class="meta-row"><span>Instance</span><strong>{{ $conversation->instance->name }}</strong></div>
                        <div class="meta-row"><span>Team</span><strong>{{ $conversation->team?->name ?? '-' }}</strong></div>
                        <div class="meta-row"><span>Owner</span><strong x-text="agentName || '{{ $conversation->ownerAgent?->name ?? 'Unassigned' }}'"></strong></div>
                        <div class="meta-row"><span>Started</span><strong>{{ $conversation->created_at->format('M j, Y H:i') }}</strong></div>
                        <div class="meta-row"><span>Last activity</span><strong x-text="timeAgo('{{ $conversation->last_message_at }}')"></strong></div>
                        <div class="meta-row"><span>Total customer convos</span><strong>{{ $customerConversationCount }}</strong></div>
                        <div class="meta-row"><span>AI mode</span><strong x-text="aiSuspended ? 'Suspended' : '{{ $aiMode }}'"></strong></div>
                    </div>

                    <div x-show="sideTab === 'timeline'" class="tab-panel">
                        <div style="display:flex;flex-direction:column;gap:9px;max-height:360px;overflow:auto;">
                            @forelse($events as $event)
                                <div style="padding:8px 10px;background:var(--page-bg);border:1px solid var(--card-border);border-radius:8px;">
                                    <div style="font-size:.78rem;font-weight:700;">{{ strtoupper(str_replace('_', ' ', $event->type)) }}</div>
                                    <div style="font-size:.73rem;color:var(--text-muted);">{{ $event->actor?->name ?? 'System' }} - {{ $event->created_at->diffForHumans() }}</div>
                                </div>
                            @empty
                                <div style="font-size:.8rem;color:var(--text-muted);">No events yet.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div x-show="showReassign" x-cloak class="modal-overlay show" @click.self="showReassign = false">
        <div class="modal-box" style="max-width:420px;text-align:left;" @click.stop>
            <div class="modal-send-icon"><i class="ri-user-settings-line"></i></div>
            <h3>Reassign Conversation</h3>
            <p>Select an eligible agent for this conversation.</p>
            <div style="margin:12px 0 18px;">
                <select x-model="reassignAgentId" class="form-control" data-no-ss>
                    <option value="">Select agent...</option>
                    @foreach($teamAgents as $agent)
                        <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" @click="showReassign = false">Cancel</button>
                <button type="button" class="btn btn-primary" :disabled="!reassignAgentId || actionLoading" @click="reassign()">Confirm</button>
            </div>
        </div>
    </div>
</div>

<style>
.conv-avatar {
    width: 44px;
    height: 44px;
    border-radius: 999px;
    background: linear-gradient(135deg,#10b981,#059669);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: .82rem;
    text-transform: uppercase;
}
.meta-row {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 7px 0;
    border-bottom: 1px solid var(--card-border);
    font-size: .8rem;
}
.meta-row:last-child { border-bottom: 0; }
.meta-row span { color: var(--text-muted); }
.meta-row strong { color: var(--text-primary); text-align: right; }
</style>

<script>
function conversationPro() {
    return {
        conversationId: {{ $conversation->id }},
        state: '{{ $conversation->state }}',
        agentName: @json($conversation->ownerAgent?->name),
        agentId: {{ $conversation->owner_agent_id ?? 'null' }},
        aiSuspended: {{ $conversation->ai_suspended ? 'true' : 'false' }},

        messages: [],
        cursor: null,
        hasMoreMessages: false,
        loadingMessages: false,
        draft: '',
        isNote: false,
        sending: false,
        actionLoading: false,
        showReassign: false,
        reassignAgentId: '',
        sideTab: 'details',

        get canAct() {
            const role = '{{ auth()->user()->role }}';
            if (['admin', 'super_admin', 'supervisor'].includes(role)) return true;
            return this.agentId === {{ auth()->id() }};
        },

        async init() {
            await this.loadMessages();
            this.scrollToBottom();
            this.subscribeChannel();
        },

        setQuickReply(text) {
            this.draft = text;
            this.$nextTick(() => {
                const ta = document.querySelector('textarea[x-model="draft"]');
                if (ta) this.autoResize(ta);
            });
        },

        async loadMessages(append = false) {
            this.loadingMessages = true;
            try {
                const params = new URLSearchParams({ per_page: 40 });
                if (this.cursor) params.set('before', this.cursor);
                const res = await fetch(`/api/conversations/${this.conversationId}/messages?${params}`, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();
                const msgs = data.data || [];
                this.messages = append ? [...msgs, ...this.messages] : msgs;
                this.hasMoreMessages = !!data.next_cursor;
                this.cursor = data.next_cursor ?? null;
            } catch (e) {
                console.error('Failed to load messages:', e);
                window.showToast?.('error', 'Could not load messages');
            } finally {
                this.loadingMessages = false;
            }
        },

        async loadMoreMessages() {
            const scrollEl = document.getElementById('messages-scroll');
            const oldHeight = scrollEl.scrollHeight;
            await this.loadMessages(true);
            this.$nextTick(() => {
                scrollEl.scrollTop = scrollEl.scrollHeight - oldHeight;
            });
        },

        scrollToBottom() {
            this.$nextTick(() => {
                document.getElementById('scroll-anchor')?.scrollIntoView({ behavior: 'instant' });
            });
        },

        autoResize(el) {
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 150) + 'px';
        },

        async send() {
            if (!this.draft.trim() || this.sending || this.state !== 'claimed') return;
            this.sending = true;
            const body = this.draft.trim();
            this.draft = '';
            const endpoint = this.isNote
                ? `/api/conversations/${this.conversationId}/notes`
                : `/api/conversations/${this.conversationId}/messages`;

            try {
                const res = await fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify({ body })
                });
                if (!res.ok) {
                    const err = await res.json();
                    window.showToast?.('error', err.message || 'Failed to send');
                    this.draft = body;
                }
            } catch {
                this.draft = body;
                window.showToast?.('error', 'Network error');
            } finally {
                this.sending = false;
            }
        },

        async claim() {
            this.actionLoading = true;
            try {
                const res = await fetch(`/api/conversations/${this.conversationId}/claim`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    }
                });
                const data = await res.json();
                if (res.ok) {
                    this.state = 'claimed';
                    this.agentId = {{ auth()->id() }};
                    this.agentName = @json(auth()->user()->name);
                    this.aiSuspended = true;
                    window.showToast?.('success', 'Conversation claimed');
                } else {
                    window.showToast?.('error', data.message || 'Could not claim');
                }
            } finally {
                this.actionLoading = false;
            }
        },

        release() {
            confirmSend({
                title: 'Release conversation?',
                message: 'This will return the conversation to the pool.',
                callback: async () => {
                    this.actionLoading = true;
                    await fetch(`/api/conversations/${this.conversationId}/release`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                        }
                    });
                    window.location.href = @json(route($panelPrefix . '.conversations.index'));
                }
            });
        },

        closeConv() {
            confirmSend({
                title: 'Close conversation?',
                message: 'The conversation will move to closed state.',
                callback: async () => {
                    this.actionLoading = true;
                    await fetch(`/api/conversations/${this.conversationId}/close`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                        }
                    });
                    this.state = 'closed';
                    this.actionLoading = false;
                    window.showToast?.('success', 'Conversation closed');
                }
            });
        },

        reopen() {
            confirmSend({
                title: 'Reopen conversation?',
                message: 'The conversation will return to the pool.',
                callback: async () => {
                    this.actionLoading = true;
                    await fetch(`/api/conversations/${this.conversationId}/reopen`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                        }
                    });
                    this.state = 'pool';
                    this.agentId = null;
                    this.agentName = null;
                    this.aiSuspended = false;
                    this.actionLoading = false;
                    window.showToast?.('success', 'Conversation reopened');
                }
            });
        },

        async toggleAi() {
            this.actionLoading = true;
            const target = !this.aiSuspended;
            try {
                const res = await fetch(`/api/conversations/${this.conversationId}/toggle-ai`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify({ ai_suspended: target ? 1 : 0 })
                });
                const data = await res.json();
                if (res.ok) {
                    this.aiSuspended = !!data.ai_suspended;
                    window.showToast?.('success', data.message || 'AI status updated');
                } else {
                    window.showToast?.('error', data.message || 'Could not update AI status');
                }
            } finally {
                this.actionLoading = false;
            }
        },

        async reassign() {
            if (!this.reassignAgentId) return;
            this.actionLoading = true;
            try {
                const res = await fetch(`/api/conversations/${this.conversationId}/reassign`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify({ agent_id: this.reassignAgentId })
                });
                const data = await res.json();
                if (!res.ok) {
                    window.showToast?.('error', data.message || 'Reassign failed');
                    return;
                }
                this.agentId = parseInt(this.reassignAgentId, 10);
                const select = document.querySelector('select[x-model="reassignAgentId"]');
                const label = select?.selectedOptions?.[0]?.textContent?.trim();
                if (label) this.agentName = label;
                this.showReassign = false;
                this.reassignAgentId = '';
                window.showToast?.('success', 'Conversation reassigned');
            } finally {
                this.actionLoading = false;
            }
        },

        subscribeChannel() {
            if (!window.Echo) return;
            const tenantId = {{ $conversation->tenant_id }};
            window.Echo.private(`tenant.${tenantId}.conversation.${this.conversationId}`)
                .listen('.message.received', (e) => {
                    this.messages.push(e.message);
                    this.scrollToBottom();
                })
                .listen('.message.sent', (e) => {
                    const idx = this.messages.findIndex(m => m.id === e.message.id);
                    if (idx >= 0) this.messages[idx] = e.message;
                    else {
                        this.messages.push(e.message);
                        this.scrollToBottom();
                    }
                })
                .listen('.conversation.claimed', (e) => {
                    this.state = 'claimed';
                    this.agentId = e.agent?.id || null;
                    this.agentName = e.agent?.name || null;
                    this.aiSuspended = true;
                })
                .listen('.conversation.released', () => {
                    this.state = 'pool';
                    this.agentId = null;
                    this.agentName = null;
                    this.aiSuspended = false;
                })
                .listen('.conversation.closed', () => {
                    this.state = 'closed';
                })
                .listen('.conversation.reopened', () => {
                    this.state = 'pool';
                    this.aiSuspended = false;
                });
        },

        bubbleStyle(msg) {
            if (msg.ai_metadata?.is_note) {
                return 'background:#fffbeb;border:1px dashed #f59e0b;color:#92400e;';
            }
            if (msg.direction === 'out') {
                return 'background:var(--brand);color:#fff;border-bottom-right-radius:4px;';
            }
            return 'background:#fff;border:1px solid var(--card-border);border-bottom-left-radius:4px;color:var(--text-primary);';
        },

        formatTime(ts) {
            if (!ts) return '';
            return new Date(ts).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },

        timeAgo(ts) {
            if (!ts) return '-';
            const diff = (Date.now() - new Date(ts)) / 1000;
            if (diff < 60) return 'just now';
            if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
            if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
            return `${Math.floor(diff / 86400)}d ago`;
        }
    };
}
</script>
@endsection
