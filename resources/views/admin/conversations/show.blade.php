@extends('layouts.admin')

@section('title', 'Conversation')

@section('breadcrumb')
    <a href="{{ route('admin.conversations.index') }}" style="color:var(--text-secondary);text-decoration:none">Conversations</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ $conversation->customer->displayNameOrPhone }}</span>
@endsection

@section('content')
<div x-data="conversationView()" x-init="init()" style="display:grid;grid-template-columns:1fr 320px;gap:1rem;height:calc(100vh - 8.5rem);min-height:0">

    {{-- === LEFT: Chat Panel === --}}
    <div class="card" style="padding:0;display:flex;flex-direction:column;overflow:hidden">

        {{-- Chat Header --}}
        <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--card-border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0">
            <div style="display:flex;align-items:center;gap:.875rem">
                <div style="width:2.75rem;height:2.75rem;border-radius:50%;background:linear-gradient(135deg,#10b981,#059669);color:#fff;font-size:.875rem;font-weight:600;display:flex;align-items:center;justify-content:center;text-transform:uppercase">
                    {{ strtoupper(substr($conversation->customer->displayNameOrPhone, 0, 2)) }}
                </div>
                <div>
                    <div style="font-weight:600;font-size:.9375rem">{{ $conversation->customer->displayNameOrPhone }}</div>
                    <div style="font-size:.8125rem;color:var(--text-muted)">{{ $conversation->customer->phone_e164 }} · {{ $conversation->instance->name }}</div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:.5rem">
                {{-- State Badge --}}
                <span x-show="state === 'pool'" class="badge badge-orange">Waiting in Pool</span>
                <span x-show="state === 'claimed'" class="badge badge-blue">Claimed</span>
                <span x-show="state === 'closed'" class="badge badge-gray">Closed</span>

                {{-- Action Buttons --}}
                @can('claim', $conversation)
                    <template x-if="state === 'pool'">
                        <button @click="claim()" :disabled="actionLoading" class="btn btn-primary btn-sm">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Claim
                        </button>
                    </template>
                @endcan

                <template x-if="state === 'claimed' && canAct">
                    <div style="display:flex;gap:.375rem">
                        @can('reassign', $conversation)
                        <button @click="showReassign = true" class="btn btn-outline btn-sm">Reassign</button>
                        @endcan
                        @can('release', $conversation)
                            <button @click="release()" :disabled="actionLoading" class="btn btn-outline btn-sm">Release</button>
                        @endcan
                        <button @click="close()" :disabled="actionLoading" class="btn btn-danger btn-sm">Close</button>
                    </div>
                </template>

                <template x-if="state === 'closed'">
                    <div style="color:var(--text-muted);font-size:.8125rem" x-text="closedAt ? `Closed ${timeAgo(closedAt)}` : 'Closed'"></div>
                </template>
            </div>
        </div>

        {{-- AI Suggestion Banner --}}
        <div x-show="aiSuggestion" x-transition
             style="margin:.75rem 1.25rem;padding:.75rem 1rem;background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2);border-radius:.625rem;display:flex;align-items:flex-start;gap:.75rem;flex-shrink:0">
            <svg style="color:var(--brand);flex-shrink:0;margin-top:.1rem" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
            <div style="flex:1;min-width:0">
                <div style="font-size:.75rem;font-weight:600;color:var(--brand);margin-bottom:.25rem">AI Suggestion</div>
                <div style="font-size:.8125rem;color:var(--text-secondary)" x-text="aiSuggestion"></div>
            </div>
            <div style="display:flex;gap:.375rem;flex-shrink:0">
                <button @click="useAiSuggestion()" class="btn btn-sm" style="background:var(--brand);color:#fff;padding:.25rem .625rem;font-size:.75rem">Use</button>
                <button @click="aiSuggestion = null" class="btn btn-ghost btn-sm" style="padding:.25rem .5rem;font-size:.75rem">Dismiss</button>
            </div>
        </div>

        {{-- Messages Area --}}
        <div id="messages-scroll" style="flex:1;overflow-y:auto;padding:1rem 1.25rem;display:flex;flex-direction:column;gap:.75rem"
             @scroll="onScroll">

            {{-- Load More --}}
            <div x-show="hasMoreMessages" style="text-align:center;margin-bottom:.5rem">
                <button @click="loadMoreMessages()" :disabled="loadingMessages" class="btn btn-ghost btn-sm" style="font-size:.75rem">
                    <span x-show="!loadingMessages">Load earlier messages</span>
                    <span x-show="loadingMessages">Loading…</span>
                </button>
            </div>

            <template x-for="msg in messages" :key="msg.id">
                <div :class="msgClass(msg)" style="display:flex;align-items:flex-end;gap:.5rem">

                    {{-- Inbound Avatar --}}
                    <div x-show="msg.direction === 'in'"
                         style="width:1.75rem;height:1.75rem;border-radius:50%;background:var(--card-border);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.6875rem;font-weight:600;color:var(--text-secondary)">
                        {{ strtoupper(substr($conversation->customer->displayNameOrPhone, 0, 1)) }}
                    </div>

                    {{-- Bubble --}}
                    <div :style="bubbleStyle(msg)" style="max-width:72%;border-radius:.875rem;padding:.625rem .875rem;position:relative">
                        {{-- Note label --}}
                        <div x-show="msg.ai_metadata?.is_note"
                             style="font-size:.6875rem;font-weight:600;color:#f59e0b;margin-bottom:.25rem;display:flex;align-items:center;gap:.25rem">
                            <svg width="10" height="10" fill="currentColor" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Internal Note
                        </div>
                        {{-- AI label --}}
                        <div x-show="msg.author_type === 'ai'"
                             style="font-size:.6875rem;font-weight:600;color:var(--brand);margin-bottom:.25rem;display:flex;align-items:center;gap:.25rem">
                            <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                            AI Reply
                        </div>
                        <div style="font-size:.875rem;white-space:pre-wrap;word-break:break-word" x-text="msg.body"></div>
                        <div style="font-size:.6875rem;margin-top:.375rem;opacity:.65;text-align:right" x-text="formatTime(msg.sent_at)"></div>

                        {{-- Delivery status (outbound) --}}
                        <div x-show="msg.direction === 'out'" style="position:absolute;bottom:.375rem;right:.625rem;opacity:.6">
                            <svg x-show="msg.status === 'sent'" width="12" height="12" fill="currentColor" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/></svg>
                            <svg x-show="msg.status === 'delivered'" width="14" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 12"><path d="M1 6l4 4L13 2M9 6l4 4 8-8"/></svg>
                            <svg x-show="msg.status === 'read'" width="14" height="10" fill="none" stroke="#10b981" stroke-width="2.5" viewBox="0 0 24 12"><path d="M1 6l4 4L13 2M9 6l4 4 8-8"/></svg>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Typing indicator --}}
            <div x-show="agentTyping" style="display:flex;align-items:flex-end;gap:.5rem">
                <div style="width:1.75rem;height:1.75rem;border-radius:50%;background:var(--brand-light);display:flex;align-items:center;justify-content:center;flex-shrink:0"></div>
                <div style="background:var(--page-bg);border:1px solid var(--card-border);border-radius:.875rem;padding:.625rem .875rem">
                    <div style="display:flex;gap:.25rem;align-items:center;height:1.125rem">
                        <span class="typing-dot"></span>
                        <span class="typing-dot" style="animation-delay:.2s"></span>
                        <span class="typing-dot" style="animation-delay:.4s"></span>
                    </div>
                </div>
            </div>

            <div id="scroll-anchor"></div>
        </div>

        {{-- Compose Bar --}}
        <div x-show="state !== 'closed'" style="padding:.875rem 1.25rem;border-top:1px solid var(--card-border);flex-shrink:0;background:var(--card-bg)">
            <div style="display:flex;gap:.5rem;align-items:flex-end">
                {{-- Note toggle --}}
                <button @click="isNote = !isNote"
                        :style="isNote ? 'color:#f59e0b;background:rgba(245,158,11,.1)' : 'color:var(--text-muted)'"
                        title="Toggle internal note"
                        style="padding:.5rem;border-radius:.5rem;border:none;cursor:pointer;flex-shrink:0;transition:all .15s">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </button>

                {{-- Textarea --}}
                <div style="flex:1;position:relative">
                    <textarea x-model="draft" @keydown.meta.enter.prevent="send()" @keydown.ctrl.enter.prevent="send()"
                              :placeholder="isNote ? 'Write an internal note… (Ctrl+Enter to send)' : 'Type a message… (Ctrl+Enter to send)'"
                              :style="isNote ? 'border-color:rgba(245,158,11,.4);background:rgba(245,158,11,.04)' : ''"
                              rows="1"
                              @input="autoResize($el)"
                              style="width:100%;resize:none;border:1.5px solid var(--card-border);border-radius:.75rem;padding:.625rem .875rem;font-size:.875rem;font-family:inherit;background:var(--card-bg);color:var(--text-primary);outline:none;line-height:1.5;max-height:150px;overflow-y:auto;transition:border-color .15s;box-sizing:border-box"
                              onfocus="this.style.borderColor='var(--brand)'"
                              onblur="this.style.borderColor='var(--card-border)'">
                    </textarea>
                </div>

                {{-- Send --}}
                <button @click="send()" :disabled="!draft.trim() || sending || state !== 'claimed'"
                        class="btn btn-primary btn-icon" style="flex-shrink:0;width:2.5rem;height:2.5rem;padding:0">
                    <svg x-show="!sending" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    <div x-show="sending" class="spinner" style="width:1rem;height:1rem;border-width:2px"></div>
                </button>
            </div>
            <div x-show="state === 'pool'" style="margin-top:.5rem;font-size:.75rem;color:var(--text-muted);text-align:center">
                Claim this conversation to reply
            </div>
        </div>

        {{-- Closed bar --}}
        <div x-show="state === 'closed'"
             style="padding:.875rem 1.25rem;border-top:1px solid var(--card-border);flex-shrink:0;text-align:center;background:var(--page-bg)">
            <span style="font-size:.8125rem;color:var(--text-muted)">This conversation is closed. New messages will reopen it automatically.</span>
        </div>
    </div>

    {{-- === RIGHT: Info Panel === --}}
    <div style="display:flex;flex-direction:column;gap:1rem;overflow-y:auto">

        {{-- Customer Info --}}
        <div class="card">
            <div class="card-header" style="padding-bottom:.75rem">
                <div class="card-title">Contact</div>
            </div>
            <div style="padding:0 1.25rem 1.25rem">
                <div style="text-align:center;margin-bottom:1rem">
                    <div style="width:3.5rem;height:3.5rem;border-radius:50%;background:linear-gradient(135deg,#10b981,#059669);color:#fff;font-size:1rem;font-weight:700;display:flex;align-items:center;justify-content:center;margin:0 auto .75rem;text-transform:uppercase">
                        {{ strtoupper(substr($conversation->customer->displayNameOrPhone, 0, 2)) }}
                    </div>
                    <div style="font-weight:600">{{ $conversation->customer->displayNameOrPhone }}</div>
                    <div style="font-size:.8125rem;color:var(--text-muted)">{{ $conversation->customer->phone_e164 }}</div>
                </div>

                <div style="display:flex;flex-direction:column;gap:.5rem;font-size:.8125rem">
                    <div style="display:flex;justify-content:space-between">
                        <span style="color:var(--text-muted)">First contact</span>
                        <span>{{ $conversation->customer->created_at->format('M j, Y') }}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between">
                        <span style="color:var(--text-muted)">Total conversations</span>
                        <span>{{ $customerConversationCount }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Conversation Details --}}
        <div class="card">
            <div class="card-header" style="padding-bottom:.75rem">
                <div class="card-title">Details</div>
            </div>
            <div style="padding:0 1.25rem 1.25rem;font-size:.8125rem;display:flex;flex-direction:column;gap:.5rem">
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Instance</span>
                    <span>{{ $conversation->instance->name }}</span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Team</span>
                    <span>{{ $conversation->team?->name ?? '—' }}</span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Agent</span>
                    <span x-text="agentName || '{{ $conversation->ownerAgent?->name ?? 'Unassigned' }}'"></span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Started</span>
                    <span>{{ $conversation->created_at->format('M j, g:i a') }}</span>
                </div>
                @if($conversation->claimed_at)
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Claimed</span>
                    <span>{{ $conversation->claimed_at->format('M j, g:i a') }}</span>
                </div>
                @endif
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">AI</span>
                    <span x-text="aiSuspended ? 'Suspended' : '{{ $aiMode }}'"></span>
                </div>
            </div>
        </div>

        {{-- Timeline --}}
        <div class="card">
            <div class="card-header" style="padding-bottom:.75rem">
                <div class="card-title">Timeline</div>
            </div>
            <div style="padding:0 1.25rem 1.25rem">
                @forelse($events as $event)
                <div style="display:flex;gap:.625rem;{{ $loop->last ? '' : 'padding-bottom:.75rem;border-bottom:1px solid var(--card-border);margin-bottom:.75rem' }}">
                    <div style="width:1.5rem;height:1.5rem;border-radius:50%;background:var(--page-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--text-muted)">
                        @if($event->type === 'claimed')
                            <svg width="10" height="10" fill="currentColor" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/></svg>
                        @elseif($event->type === 'closed')
                            <svg width="10" height="10" fill="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                        @elseif($event->type === 'note_added')
                            <svg width="10" height="10" fill="currentColor" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6"/></svg>
                        @else
                            <svg width="10" height="10" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/></svg>
                        @endif
                    </div>
                    <div>
                        <div style="font-size:.8125rem;font-weight:500">{{ ucfirst(str_replace('_', ' ', $event->type)) }}</div>
                        <div style="font-size:.75rem;color:var(--text-muted)">
                            {{ $event->actor?->name ?? 'System' }} · {{ $event->created_at->diffForHumans() }}
                        </div>
                    </div>
                </div>
                @empty
                <div style="color:var(--text-muted);font-size:.8125rem;text-align:center;padding:.5rem 0">No events yet</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- Reassign Modal --}}
<div x-show="showReassign" x-cloak class="modal-overlay show" @click.self="showReassign = false">
    <div class="modal-box" @click.stop style="text-align:left;padding:1.5rem">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem">
            <h3 style="font-size:1rem;font-weight:700;margin:0">Reassign Conversation</h3>
            <button @click="showReassign = false" class="btn btn-ghost btn-icon btn-sm" style="width:28px;height:28px">&times;</button>
        </div>
        <div class="form-group" style="margin-bottom:1.25rem">
            <label class="form-label">Assign to Agent</label>
            <select x-model="reassignAgentId" class="form-control" data-no-ss>
                <option value="">Select agent…</option>
                @foreach($teamAgents as $agent)
                <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="modal-actions">
            <button @click="showReassign = false" class="btn btn-outline">Cancel</button>
            <button @click="reassign()" :disabled="!reassignAgentId || actionLoading" class="btn btn-primary">Reassign</button>
        </div>
    </div>
</div>

<style>
@keyframes typingPulse {
    0%, 100% { transform: scaleY(0.4); opacity: .4; }
    50% { transform: scaleY(1); opacity: 1; }
}
.typing-dot {
    display: inline-block; width: 5px; height: 10px; border-radius: 2px;
    background: var(--text-muted); animation: typingPulse 1s infinite;
}
</style>

<script>
function conversationView() {
    return {
        conversationId: {{ $conversation->id }},
        state: '{{ $conversation->state }}',
        closedAt: '{{ $conversation->closed_at }}',
        agentName: '{{ $conversation->ownerAgent?->name }}',
        agentId: {{ $conversation->owner_agent_id ?? 'null' }},
        aiSuspended: {{ $conversation->ai_suspended ? 'true' : 'false' }},

        messages: [],
        cursor: null,
        hasMoreMessages: false,
        loadingMessages: false,
        draft: '',
        isNote: false,
        sending: false,
        aiSuggestion: null,
        agentTyping: false,
        actionLoading: false,
        showReassign: false,
        reassignAgentId: '',

        get canAct() {
            const role = '{{ auth()->user()->role }}';
            if (['admin', 'supervisor'].includes(role)) return true;
            // Agent can only act on their own claimed conversation
            return this.agentId === {{ auth()->id() }};
        },

        async init() {
            await this.loadMessages();
            this.scrollToBottom();
            this.subscribeChannel();
        },

        async loadMessages(append = false) {
            this.loadingMessages = true;
            try {
                const params = new URLSearchParams({ per_page: 40 });
                if (this.cursor) params.set('before', this.cursor);

                const res = await fetch(`/api/conversations/${this.conversationId}/messages?${params}`, {
                    credentials: 'same-origin', headers: { 'Accept': 'application/json' }
                });

                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();

                const msgs = data.data || [];

                if (append) {
                    this.messages = [...msgs, ...this.messages];
                } else {
                    this.messages = msgs;
                }

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
                const anchor = document.getElementById('scroll-anchor');
                anchor?.scrollIntoView({ behavior: 'instant' });
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
                this.state    = 'claimed';
                this.agentId  = {{ auth()->id() }};
                this.agentName = '{{ auth()->user()->name }}';
                window.showToast?.('success', 'Conversation claimed');
            } else {
                window.showToast?.('error', data.message || 'Could not claim');
            }
            this.actionLoading = false;
        },

        async release() {
            this.actionLoading = true;
            await fetch(`/api/conversations/${this.conversationId}/release`, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
            });
            window.location.href = '/admin/conversations';
        },

        async close() {
            if (!confirm('Close this conversation?')) return;
            this.actionLoading = true;
            await fetch(`/api/conversations/${this.conversationId}/close`, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
            });
            this.state = 'closed';
            this.actionLoading = false;
            window.showToast?.('success', 'Conversation closed');
        },

        async reassign() {
            if (!this.reassignAgentId) return;
            this.actionLoading = true;
            await fetch(`/api/conversations/${this.conversationId}/reassign`, {
                method: 'POST', credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({ agent_id: this.reassignAgentId })
            });
            this.showReassign = false;
            this.actionLoading = false;
            window.showToast?.('success', 'Conversation reassigned');
        },

        useAiSuggestion() {
            this.draft = this.aiSuggestion;
            this.aiSuggestion = null;
            this.$nextTick(() => {
                const ta = document.querySelector('textarea');
                if (ta) { this.autoResize(ta); ta.focus(); }
            });
        },

        subscribeChannel() {
            if (!window.Echo) return;
            const tenantId = {{ auth()->user()->tenant_id }};

            window.Echo.private(`tenant.${tenantId}.conversation.${this.conversationId}`)
                .listen('.message.received', (e) => {
                    this.messages.push(e.message);
                    this.scrollToBottom();
                    if (e.message.ai_metadata?.suggestion) {
                        this.aiSuggestion = e.message.ai_metadata.suggestion;
                    }
                })
                .listen('.message.sent', (e) => {
                    const idx = this.messages.findIndex(m => m.id === e.message.id);
                    if (idx >= 0) this.messages[idx] = e.message;
                    else { this.messages.push(e.message); this.scrollToBottom(); }
                })
                .listen('.conversation.claimed', (e) => {
                    this.state = 'claimed';
                    this.agentName = e.agent?.name;
                    this.agentId = e.agent?.id;
                    this.aiSuspended = true;
                })
                .listen('.conversation.closed', () => { this.state = 'closed'; })
                .listen('.conversation.released', () => { this.state = 'pool'; this.agentName = null; this.aiSuspended = false; });
        },

        msgClass(msg) {
            if (msg.ai_metadata?.is_note) return 'msg-note';
            return msg.direction === 'out' ? 'msg-out' : 'msg-in';
        },

        bubbleStyle(msg) {
            if (msg.ai_metadata?.is_note) {
                return 'background:rgba(245,158,11,.08);border:1px dashed rgba(245,158,11,.35);';
            }
            if (msg.direction === 'out') {
                return 'background:var(--brand);color:#fff;margin-left:auto;border-bottom-right-radius:.25rem;';
            }
            return 'background:var(--page-bg);border:1px solid var(--card-border);border-bottom-left-radius:.25rem;';
        },

        formatTime(ts) {
            if (!ts) return '';
            return new Date(ts).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },

        timeAgo(ts) {
            if (!ts) return '';
            const diff = (Date.now() - new Date(ts)) / 1000;
            if (diff < 60) return 'just now';
            if (diff < 3600) return `${Math.floor(diff/60)}m ago`;
            if (diff < 86400) return `${Math.floor(diff/3600)}h ago`;
            return `${Math.floor(diff/86400)}d ago`;
        }
    }
}
</script>
@endsection
