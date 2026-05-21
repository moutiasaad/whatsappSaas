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
    <div class="page-header conversation-page-header" style="margin-bottom:1rem;">
        <div class="page-header-left">
            <div class="page-title">Conversation Desk</div>
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

    <div class="conversation-shell">
        <div class="conversation-main card">
            <div class="conversation-thread-head">
                <div class="conversation-thread-contact">
                    <div class="conv-avatar conv-avatar-lg">{{ strtoupper(substr($conversation->customer->displayNameOrPhone, 0, 2)) }}</div>
                    <div class="conversation-thread-copy">
                        <div class="conversation-thread-name">{{ $conversation->customer->displayNameOrPhone }}</div>
                        <div class="conversation-thread-meta">{{ $conversation->instance->name }} - {{ $conversation->team?->name ?? 'No team' }}</div>
                        <div class="conversation-thread-state">
                            <span class="presence-dot"></span>
                            <span>Live support workspace</span>
                        </div>
                    </div>
                </div>

                <div class="conversation-thread-actions">
                    <span x-show="state === 'pool'" class="badge badge-orange"><i class="ri-time-line"></i> Pool</span>
                    <span x-show="state === 'claimed'" class="badge badge-blue"><i class="ri-user-line"></i> Claimed</span>
                    <span x-show="state === 'closed'" class="badge badge-gray"><i class="ri-check-double-line"></i> Closed</span>
                    <span x-show="aiSuspended" class="badge badge-gray">AI Off</span>

                    @can('claim', $conversation)
                    <template x-if="state === 'pool'">
                        <button @click="claim()" :disabled="actionLoading" class="btn btn-primary btn-sm conversation-cta">
                            <i class="ri-hand-coin-line"></i> Claim
                        </button>
                    </template>
                    @endcan

                    <template x-if="state === 'claimed' && canAct">
                        <div class="conversation-inline-actions">
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

            <div id="messages-scroll" class="conversation-stage">
                <div x-show="hasMoreMessages" class="conversation-load-more">
                    <button @click="loadMoreMessages()" :disabled="loadingMessages" class="btn btn-ghost btn-sm">
                        <span x-show="!loadingMessages">Load earlier messages</span>
                        <span x-show="loadingMessages">Loading...</span>
                    </button>
                </div>

                <template x-for="(msg, index) in messages" :key="msg.id">
                    <div>
                        <div x-show="showDateSeparator(index)" class="conversation-date-separator">
                            <span x-text="formatDateSeparator(msg.sort_ts)"></span>
                        </div>
                        <div class="message-row" :class="msg.direction === 'out' ? 'message-row-out' : 'message-row-in'">
                            <div class="message-avatar" :class="msg.direction === 'out' ? 'message-avatar-out' : 'message-avatar-in'">
                                <span x-text="msg.direction === 'out' ? 'ME' : '{{ strtoupper(substr($conversation->customer->displayNameOrPhone, 0, 2)) }}'"></span>
                            </div>
                            <div class="message-stack" :class="msg.direction === 'out' ? 'message-stack-out' : ''">
                                <div class="message-chip" x-show="msg.ai_metadata?.is_note">Internal note</div>
                                <div class="message-chip message-chip-ai" x-show="msg.author_type === 'ai'">AI reply</div>
                                <div class="message-bubble" :style="bubbleStyle(msg)">
                                    <div class="message-body" x-text="msg.body || '-'"></div>
                                    <div class="message-time" x-text="formatMessageStamp(msg.sort_ts)"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
                <div id="scroll-anchor"></div>
            </div>

            <div x-show="state !== 'closed'" class="conversation-composer-wrap">
                <div class="conversation-quick-replies">
                    <button type="button" class="btn btn-ghost btn-sm" @click="setQuickReply('Hello! Thanks for reaching out. How can I help you today?')">Greeting</button>
                    <button type="button" class="btn btn-ghost btn-sm" @click="setQuickReply('Thanks for waiting. I am checking this now and will update you shortly.')">Follow-up</button>
                    <button type="button" class="btn btn-ghost btn-sm" @click="setQuickReply('This issue is now resolved. Please confirm on your side.')">Resolved</button>
                    <button type="button" class="btn btn-ghost btn-sm" @click="isNote = !isNote" :style="isNote ? 'color:#b45309;background:#fef3c7;' : ''">
                        <i class="ri-sticky-note-line"></i> Note mode
                    </button>
                </div>

                <div class="conversation-composer">
                    <textarea x-model="draft" rows="1" @keydown="handleComposerKeydown($event)" @input="autoResize($el)"
                              :placeholder="isNote ? 'Write internal note... (Enter to send, Shift+Enter for newline)' : 'Type a message... (Enter to send, Shift+Enter for newline)'"
                              class="conversation-textarea"></textarea>
                    <button @click="send()" :disabled="!draft.trim() || sending || state !== 'claimed'" class="btn btn-primary conversation-send-btn">
                        <span x-show="!sending"><i class="ri-send-plane-2-line"></i> Send</span>
                        <span x-show="sending"><span class="btn-spinner"></span></span>
                    </button>
                </div>
                <div x-show="state === 'pool'" class="conversation-composer-hint">Claim this conversation to send replies.</div>
            </div>

            <div x-show="state === 'closed'" class="conversation-closed-banner">
                Conversation is closed. Reopen it to continue.
            </div>
        </div>

        <div class="conversation-side">
            <div class="conversation-profile-card">
                <div class="conversation-profile-top">
                    <div>
                        <div class="conversation-profile-name">{{ $conversation->customer->displayNameOrPhone }}</div>
                        <div class="conversation-profile-company">{{ $conversation->tenant?->name ?? 'Workspace contact' }}</div>
                        <div class="conversation-profile-role">{{ $conversation->ownerAgent?->name ?? 'Unassigned owner' }}</div>
                    </div>
                    <div class="conv-avatar conversation-profile-avatar">{{ strtoupper(substr($conversation->customer->displayNameOrPhone, 0, 2)) }}</div>
                </div>
                <div class="conversation-profile-contact">{{ $conversation->customer->phone_e164 }}</div>
                <div class="conversation-profile-icons">
                    <span><i class="ri-phone-line"></i></span>
                    <span><i class="ri-whatsapp-line"></i></span>
                    <span><i class="ri-links-line"></i></span>
                </div>
            </div>

            <div class="card conversation-side-card">
                <div class="card-header" style="padding-bottom:10px;">
                    <div class="card-title">Workspace</div>
                </div>
                <div style="padding:0 14px 14px;">
                    <div class="tab-nav conversation-side-tabs" style="margin-bottom:12px;">
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
                        <div class="conversation-timeline">
                            @forelse($events as $event)
                                <div class="conversation-timeline-item">
                                    <div class="conversation-timeline-type">{{ strtoupper(str_replace('_', ' ', $event->type)) }}</div>
                                    <div class="conversation-timeline-meta">{{ $event->actor?->name ?? 'System' }} - {{ $event->created_at->diffForHumans() }}</div>
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
.conversation-shell {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 340px;
    gap: 1rem;
    min-height: calc(100vh - 11.5rem);
}
.conversation-main {
    padding: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background:
        radial-gradient(circle at top left, rgba(16,185,129,.10), transparent 28%),
        radial-gradient(circle at top right, rgba(37,99,235,.10), transparent 24%),
        #ffffff;
}
.conversation-thread-head {
    padding: 16px 18px;
    border-bottom: 1px solid var(--card-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(248,250,252,.94));
}
.conversation-thread-contact {
    display: flex;
    align-items: center;
    gap: .9rem;
    min-width: 0;
}
.conversation-thread-copy {
    display: flex;
    flex-direction: column;
    gap: .125rem;
    min-width: 0;
}
.conversation-thread-name {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-primary);
}
.conversation-thread-meta,
.conversation-thread-state {
    font-size: .78rem;
    color: var(--text-muted);
}
.conversation-thread-state {
    display: flex;
    align-items: center;
    gap: .35rem;
}
.presence-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: #22c55e;
    box-shadow: 0 0 0 5px rgba(34,197,94,.12);
}
.conversation-thread-actions,
.conversation-inline-actions {
    display: flex;
    align-items: center;
    gap: .4rem;
    flex-wrap: wrap;
}
.conversation-stage {
    flex: 1;
    overflow-y: auto;
    padding: 18px 18px 14px;
    background:
        radial-gradient(circle at top left, rgba(14,165,233,.08), transparent 18%),
        radial-gradient(circle at bottom right, rgba(16,185,129,.08), transparent 22%),
        linear-gradient(180deg, #f7fafc 0%, #f1f8f6 100%);
}
.conversation-load-more {
    text-align: center;
    margin-bottom: .75rem;
}
.message-row {
    display: flex;
    align-items: flex-end;
    gap: .65rem;
    margin-bottom: 12px;
}
.message-row-out {
    justify-content: flex-end;
}
.message-row-out .message-avatar {
    order: 2;
}
.message-row-out .message-stack {
    order: 1;
    align-items: flex-end;
}
.message-avatar {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .68rem;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
    box-shadow: 0 6px 18px rgba(15,23,42,.12);
}
.message-avatar-in {
    background: linear-gradient(135deg, #0f172a, #334155);
}
.message-avatar-out {
    background: linear-gradient(135deg, #14b8a6, #2563eb);
}
.message-stack {
    max-width: 76%;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.message-chip {
    align-self: flex-start;
    padding: 4px 8px;
    border-radius: 999px;
    background: #fef3c7;
    color: #92400e;
    font-size: .64rem;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
}
.message-chip-ai {
    background: #ccfbf1;
    color: #0f766e;
}
.message-bubble {
    max-width: 100%;
    padding: 12px 14px 10px;
    border-radius: 18px;
    box-shadow: 0 10px 25px rgba(15,23,42,.06);
}
.message-body {
    font-size: .89rem;
    line-height: 1.55;
    white-space: pre-wrap;
    word-break: break-word;
}
.message-time {
    font-size: .66rem;
    opacity: .72;
    text-align: right;
    margin-top: 6px;
}
.conversation-date-separator {
    display: flex;
    justify-content: center;
    margin: 14px 0;
}
.conversation-date-separator span {
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(255,255,255,.86);
    border: 1px solid var(--card-border);
    color: var(--text-secondary);
    font-size: .72rem;
    font-weight: 600;
    box-shadow: 0 4px 14px rgba(15,23,42,.06);
}
.conversation-composer-wrap {
    padding: 12px 14px 14px;
    border-top: 1px solid var(--card-border);
    background: rgba(255,255,255,.96);
    backdrop-filter: blur(10px);
}
.conversation-quick-replies {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}
.conversation-composer {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}
.conversation-textarea {
    flex: 1;
    resize: none;
    border: 1.5px solid var(--card-border);
    border-radius: 14px;
    padding: 12px 14px;
    font-size: .9rem;
    line-height: 1.5;
    max-height: 160px;
    outline: none;
    background: #fff;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.7);
}
.conversation-textarea:focus {
    border-color: rgba(37,99,235,.45);
    box-shadow: 0 0 0 4px rgba(37,99,235,.08);
}
.conversation-send-btn {
    min-width: 108px;
    height: 46px;
}
.conversation-composer-hint,
.conversation-closed-banner {
    font-size: .77rem;
    color: var(--text-muted);
    margin-top: 8px;
    text-align: center;
}
.conversation-closed-banner {
    padding: 12px 14px;
    border-top: 1px solid var(--card-border);
    background: var(--page-bg);
}
.conversation-side {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.conversation-profile-card {
    padding: 18px;
    border-radius: 20px;
    background: linear-gradient(145deg, #1d4ed8 0%, #0ea5e9 100%);
    color: #fff;
    box-shadow: 0 18px 40px rgba(37,99,235,.22);
}
.conversation-profile-top {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    align-items: flex-start;
    margin-bottom: 1rem;
}
.conversation-profile-name {
    font-size: 1.55rem;
    font-weight: 800;
    line-height: 1.1;
}
.conversation-profile-company {
    margin-top: .45rem;
    font-size: 1rem;
    font-weight: 600;
    color: rgba(255,255,255,.92);
}
.conversation-profile-role,
.conversation-profile-contact {
    margin-top: .25rem;
    font-size: .82rem;
    color: rgba(255,255,255,.78);
}
.conversation-profile-avatar {
    width: 58px;
    height: 58px;
    box-shadow: 0 12px 24px rgba(15,23,42,.25);
}
.conversation-profile-icons {
    display: flex;
    gap: .65rem;
    margin-top: 1rem;
}
.conversation-profile-icons span {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    background: rgba(255,255,255,.14);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}
.conversation-side-card {
    min-height: 0;
}
.conversation-side-tabs {
    width: 100%;
}
.conversation-timeline {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 360px;
    overflow: auto;
}
.conversation-timeline-item {
    padding: 10px 11px;
    background: var(--page-bg);
    border: 1px solid var(--card-border);
    border-radius: 10px;
}
.conversation-timeline-type {
    font-size: .78rem;
    font-weight: 700;
}
.conversation-timeline-meta {
    font-size: .73rem;
    color: var(--text-muted);
    margin-top: 2px;
}
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
.conv-avatar-lg {
    width: 52px;
    height: 52px;
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
@media (max-width: 1180px) {
    .conversation-shell {
        grid-template-columns: 1fr;
        min-height: auto;
    }
}
@media (max-width: 720px) {
    .message-stack {
        max-width: 88%;
    }
    .conversation-thread-head,
    .conversation-composer-wrap {
        padding-left: 12px;
        padding-right: 12px;
    }
    .conversation-stage {
        padding-left: 12px;
        padding-right: 12px;
    }
}
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
                const msgs = (data.data || []).map((msg) => this.normalizeMessage(msg));
                this.messages = append
                    ? this.sortMessages([...msgs, ...this.messages])
                    : this.sortMessages(msgs);
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

        handleComposerKeydown(event) {
            if (event.key !== 'Enter' || event.shiftKey) return;

            event.preventDefault();
            this.send();
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
                } else {
                    const message = this.normalizeMessage(await res.json());
                    this.messages = this.sortMessages([...this.messages, message]);
                    this.scrollToBottom();
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
                    this.messages = this.sortMessages([...this.messages, this.normalizeMessage(e.message)]);
                    this.scrollToBottom();
                })
                .listen('.message.sent', (e) => {
                    const idx = this.messages.findIndex(m => m.id === e.message.id);
                    if (idx >= 0) {
                        this.messages[idx] = this.normalizeMessage(e.message);
                        this.messages = this.sortMessages([...this.messages]);
                    }
                    else {
                        this.messages = this.sortMessages([...this.messages, this.normalizeMessage(e.message)]);
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

        normalizeMessage(msg) {
            return {
                ...msg,
                sort_ts: msg.sent_at || msg.created_at || null,
            };
        },

        showDateSeparator(index) {
            if (index === 0) return true;

            const current = this.messages[index]?.sort_ts;
            const previous = this.messages[index - 1]?.sort_ts;
            if (!current || !previous) return false;

            return new Date(current).toDateString() !== new Date(previous).toDateString();
        },

        sortMessages(messages) {
            return [...messages].sort((a, b) => {
                const aTime = a.sort_ts ? new Date(a.sort_ts).getTime() : 0;
                const bTime = b.sort_ts ? new Date(b.sort_ts).getTime() : 0;

                if (aTime !== bTime) return aTime - bTime;
                return (a.id || 0) - (b.id || 0);
            });
        },

        formatTime(ts) {
            if (!ts) return '';
            return new Date(ts).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },

        formatDateSeparator(ts) {
            if (!ts) return '';
            const date = new Date(ts);
            const today = new Date();
            const isSameDay = date.toDateString() === today.toDateString();

            if (isSameDay) return 'Today';

            const yesterday = new Date();
            yesterday.setDate(today.getDate() - 1);
            if (date.toDateString() === yesterday.toDateString()) return 'Yesterday';

            return date.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
        },

        formatMessageStamp(ts) {
            if (!ts) return '';
            const date = new Date(ts);
            const today = new Date();
            const isSameDay = date.toDateString() === today.toDateString();

            if (isSameDay) {
                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }

            return date.toLocaleDateString([], { month: 'short', day: 'numeric' }) + ' ' +
                date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
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
