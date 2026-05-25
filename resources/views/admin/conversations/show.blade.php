@extends('layouts.admin')

@section('title', __('ui.conversation_show_page.title'))

@section('breadcrumb')
    @php $panelPrefix = auth()->user()->routeNamePrefix(); @endphp
    <a href="{{ route($panelPrefix . '.conversations.index') }}" style="color:var(--text-secondary);text-decoration:none">{{ __('ui.conversation_show_page.breadcrumb') }}</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ $conversation->customer->displayNameOrPhone }}</span>
@endsection

@section('content')
<div x-data="conversationPro()" x-init="init()">
    <div class="page-header conversation-page-header" style="margin-bottom:1rem;">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.conversation_show_page.page_title') }}</div>
            <div class="page-subtitle">{{ $conversation->customer->displayNameOrPhone }} - {{ $conversation->customer->phone_e164 }}</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route($panelPrefix . '.conversations.index') }}" class="btn btn-outline btn-sm">
                <i class="ri-arrow-left-line"></i> {{ __('ui.conversation_show_page.back') }}
            </a>
            <a href="{{ route($panelPrefix . '.customers.show', $conversation->customer) }}" class="btn btn-outline btn-sm">
                <i class="ri-user-line"></i> {{ __('ui.conversation_show_page.customer') }}
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
                        <div class="conversation-thread-meta">{{ $conversation->instance->name }} - {{ $conversation->team?->name ?? __('ui.conversation_show_page.no_team') }}</div>
                        <div class="conversation-thread-state">
                            <span class="presence-dot" :class="wsConnected ? '' : 'presence-dot-reconnecting'"></span>
                            <span x-text="wsConnected ? @js(__('ui.conversation_show_page.live_workspace')) : 'Reconnexion...'"></span>
                            <span class="wa-number-badge wa-number-checking" x-show="numberStatus === 'checking'" title="Checking WhatsApp number…">
                                <span class="btn-spinner" style="width:10px;height:10px;border-width:1.5px;"></span>
                            </span>
                            <span class="wa-number-badge wa-number-ok" x-show="numberStatus === 'exists'" title="Registered on WhatsApp">
                                <i class="ri-whatsapp-line"></i> WhatsApp ✓
                            </span>
                            <span class="wa-number-badge wa-number-fail" x-show="numberStatus === 'missing'" title="This number is not registered on WhatsApp">
                                <i class="ri-whatsapp-line"></i> Not on WhatsApp
                            </span>
                        </div>
                    </div>
                </div>

                <div class="conversation-thread-actions">
                    <span x-show="state === 'pool'" class="badge badge-orange"><i class="ri-time-line"></i> {{ __('ui.conversation_show_page.pool') }}</span>
                    <span x-show="state === 'claimed'" class="badge badge-blue"><i class="ri-user-line"></i> {{ __('ui.conversation_show_page.claimed') }}</span>
                    <span x-show="state === 'closed'" class="badge badge-gray"><i class="ri-check-double-line"></i> {{ __('ui.conversation_show_page.closed') }}</span>
                    <span x-show="aiSuspended" class="badge badge-gray">{{ __('ui.conversation_show_page.ai_off') }}</span>

                    @can('claim', $conversation)
                    <template x-if="state === 'pool'">
                        <button @click="claim()" :disabled="actionLoading" class="btn btn-primary btn-sm conversation-cta">
                            <i class="ri-hand-coin-line"></i> {{ __('ui.conversation_show_page.claim') }}
                        </button>
                    </template>
                    @endcan

                    <template x-if="state === 'claimed' && canAct">
                        <div class="conversation-inline-actions">
                            @can('reassign', $conversation)
                            <button @click="showReassign = true" class="btn btn-outline btn-sm">
                                <i class="ri-user-settings-line"></i> {{ __('ui.conversation_show_page.reassign') }}
                            </button>
                            @endcan
                            @can('release', $conversation)
                            <button @click="release()" class="btn btn-outline btn-sm" :disabled="actionLoading">
                                <i class="ri-reply-line"></i> {{ __('ui.conversation_show_page.release') }}
                            </button>
                            @endcan
                            @can('close', $conversation)
                            <button @click="closeConv()" class="btn btn-danger btn-sm" :disabled="actionLoading">
                                <i class="ri-close-circle-line"></i> {{ __('ui.conversation_show_page.close') }}
                            </button>
                            @endcan
                        </div>
                    </template>

                    @can('reopen', $conversation)
                    <template x-if="state === 'closed'">
                        <button @click="reopen()" class="btn btn-outline btn-sm" :disabled="actionLoading">
                            <i class="ri-refresh-line"></i> {{ __('ui.conversation_show_page.reopen') }}
                        </button>
                    </template>
                    @endcan

                    @can('toggleAi', $conversation)
                    <template x-if="state !== 'closed'">
                        <button @click="toggleAi()" class="btn btn-outline btn-sm" :disabled="actionLoading">
                            <i class="ri-robot-2-line"></i>
                            <span x-text="aiSuspended ? @js(__('ui.conversation_show_page.resume_ai')) : @js(__('ui.conversation_show_page.suspend_ai'))"></span>
                        </button>
                    </template>
                    @endcan
                </div>
            </div>

            <livewire:conversation-messages
                :conversation-id="$conversation->id"
                :customer-initials="strtoupper(substr($conversation->customer->displayNameOrPhone, 0, 2))"
            />

            <div x-show="state !== 'closed'" class="conversation-composer-wrap">
                <div class="conversation-quick-replies">
                    <button type="button" class="btn btn-ghost btn-sm" @click="setQuickReply('Hello! Thanks for reaching out. How can I help you today?')">{{ __('ui.conversation_show_page.greeting') }}</button>
                    <button type="button" class="btn btn-ghost btn-sm" @click="setQuickReply('Thanks for waiting. I am checking this now and will update you shortly.')">{{ __('ui.conversation_show_page.follow_up') }}</button>
                    <button type="button" class="btn btn-ghost btn-sm" @click="setQuickReply('This issue is now resolved. Please confirm on your side.')">{{ __('ui.conversation_show_page.resolved') }}</button>
                    <button type="button" class="btn btn-ghost btn-sm" @click="isNote = !isNote" :style="isNote ? 'color:#b45309;background:#fef3c7;' : ''">
                        <i class="ri-sticky-note-line"></i> {{ __('ui.conversation_show_page.note_mode') }}
                    </button>
                </div>

                {{-- Media attachment preview --}}
                <div x-show="mediaAttachment" class="media-preview-bar" x-cloak>
                    <div class="media-preview-inner">
                        <template x-if="mediaAttachment?.type === 'image'">
                            <img :src="mediaAttachment.url" class="media-preview-thumb" alt="preview">
                        </template>
                        <template x-if="mediaAttachment?.type !== 'image'">
                            <div class="media-preview-icon">
                                <i :class="{
                                    'ri-video-line': mediaAttachment?.type === 'video',
                                    'ri-music-2-line': mediaAttachment?.type === 'audio',
                                    'ri-file-line': mediaAttachment?.type === 'document',
                                }"></i>
                            </div>
                        </template>
                        <div class="media-preview-meta">
                            <div class="media-preview-name" x-text="mediaAttachment?.file_name"></div>
                            <div class="media-preview-type" x-text="mediaAttachment?.type"></div>
                        </div>
                        <button type="button" class="media-preview-remove" @click="clearMedia()" title="Remove">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>
                    <div x-show="mediaUploading" class="media-upload-progress">
                        <div class="media-upload-bar" :style="`width:${uploadProgress}%`"></div>
                    </div>
                </div>

                <div class="conversation-composer">
                    <input type="file" x-ref="fileInput" class="hidden"
                           accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip"
                           @change="onFileSelected($event)">
                    <button type="button" class="btn btn-ghost composer-attach-btn"
                            :disabled="state !== 'claimed' || isNote || mediaUploading"
                            @click="$refs.fileInput.click()"
                            title="Attach file">
                        <span x-show="!mediaUploading"><i class="ri-attachment-2"></i></span>
                        <span x-show="mediaUploading"><span class="btn-spinner"></span></span>
                    </button>
                    <textarea x-model="draft" rows="1" @keydown="handleComposerKeydown($event)" @input="autoResize($el); onTypingInput();"
                              :placeholder="mediaAttachment ? 'Add a caption (optional)…' : (isNote ? @js(__('ui.conversation_show_page.note_placeholder')) : @js(__('ui.conversation_show_page.message_placeholder')))"
                              class="conversation-textarea"></textarea>
                    <button @click="send()" :disabled="(!draft.trim() && !mediaAttachment) || sending || state !== 'claimed' || mediaUploading" class="btn btn-primary conversation-send-btn">
                        <span x-show="!sending"><i class="ri-send-plane-2-line"></i> {{ __('ui.conversation_show_page.send') }}</span>
                        <span x-show="sending"><span class="btn-spinner"></span></span>
                    </button>
                </div>
                <div x-show="state === 'pool'" class="conversation-composer-hint">{{ __('ui.conversation_show_page.claim_hint') }}</div>
            </div>

            <div x-show="state === 'closed'" class="conversation-closed-banner">
                {{ __('ui.conversation_show_page.closed_banner') }}
            </div>
        </div>

        <div class="conversation-side">
            <div class="conversation-profile-card">
                <div class="conversation-profile-top">
                    <div>
                        <div class="conversation-profile-name">{{ $conversation->customer->displayNameOrPhone }}</div>
                        <div class="conversation-profile-company">{{ $conversation->tenant?->name ?? __('ui.conversation_show_page.workspace_contact') }}</div>
                        <div class="conversation-profile-role">{{ $conversation->ownerAgent?->name ?? __('ui.conversation_show_page.unassigned_owner') }}</div>
                    </div>
                    <div class="conv-avatar conversation-profile-avatar">{{ strtoupper(substr($conversation->customer->displayNameOrPhone, 0, 2)) }}</div>
                </div>
                <div class="conversation-profile-contact">
                    {{ $conversation->customer->phone_e164 }}
                    <span class="wa-profile-badge wa-number-ok" x-show="numberStatus === 'exists'"><i class="ri-whatsapp-line"></i></span>
                    <span class="wa-profile-badge wa-number-fail" x-show="numberStatus === 'missing'"><i class="ri-close-circle-line"></i></span>
                </div>
                <div class="conversation-profile-icons">
                    <span><i class="ri-phone-line"></i></span>
                    <span><i class="ri-whatsapp-line"></i></span>
                    <span><i class="ri-links-line"></i></span>
                </div>
            </div>

            <div class="card conversation-side-card">
                <div class="card-header" style="padding-bottom:10px;">
                    <div class="card-title">{{ __('ui.conversation_show_page.workspace') }}</div>
                </div>
                <div style="padding:0 14px 14px;">
                    <div class="tab-nav conversation-side-tabs" style="margin-bottom:12px;">
                        <button class="tab-btn" :class="{ 'active': sideTab === 'details' }" @click="sideTab = 'details'">{{ __('ui.conversation_show_page.details') }}</button>
                        <button class="tab-btn" :class="{ 'active': sideTab === 'timeline' }" @click="sideTab = 'timeline'">{{ __('ui.conversation_show_page.timeline') }}</button>
                    </div>

                    <div x-show="sideTab === 'details'" class="tab-panel">
                        <div class="meta-row"><span>{{ __('ui.conversation_show_page.tenant') }}</span><strong>{{ $conversation->tenant?->name ?? '-' }}</strong></div>
                        <div class="meta-row"><span>{{ __('ui.conversation_show_page.instance') }}</span><strong>{{ $conversation->instance->name }}</strong></div>
                        <div class="meta-row"><span>{{ __('ui.conversation_show_page.team') }}</span><strong>{{ $conversation->team?->name ?? '-' }}</strong></div>
                        <div class="meta-row"><span>{{ __('ui.conversation_show_page.owner') }}</span><strong x-text="agentName || '{{ $conversation->ownerAgent?->name ?? 'Unassigned' }}'"></strong></div>
                        <div class="meta-row"><span>{{ __('ui.conversation_show_page.started') }}</span><strong>{{ $conversation->created_at->format('M j, Y H:i') }}</strong></div>
                        <div class="meta-row"><span>{{ __('ui.conversation_show_page.last_activity') }}</span><strong x-text="timeAgo('{{ $conversation->last_message_at }}')"></strong></div>
                        <div class="meta-row"><span>{{ __('ui.conversation_show_page.total_customer_convos') }}</span><strong>{{ $customerConversationCount }}</strong></div>
                        <div class="meta-row"><span>{{ __('ui.conversation_show_page.ai_mode') }}</span><strong x-text="aiSuspended ? @js(__('ui.conversation_show_page.suspended')) : '{{ $aiMode }}'"></strong></div>
                    </div>

                    <div x-show="sideTab === 'timeline'" class="tab-panel">
                        <div class="conversation-timeline">
                            @forelse($events as $event)
                                <div class="conversation-timeline-item">
                                    <div class="conversation-timeline-type">{{ strtoupper(str_replace('_', ' ', $event->type)) }}</div>
                                    <div class="conversation-timeline-meta">{{ $event->actor?->name ?? 'System' }} - {{ $event->created_at->diffForHumans() }}</div>
                                </div>
                            @empty
                                <div style="font-size:.8rem;color:var(--text-muted);">{{ __('ui.conversation_show_page.no_events') }}</div>
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
            <h3>{{ __('ui.conversation_show_page.reassign_title') }}</h3>
            <p>{{ __('ui.conversation_show_page.reassign_desc') }}</p>
            <div style="margin:12px 0 18px;">
                <select x-model="reassignAgentId" class="form-control" data-no-ss>
                    <option value="">{{ __('ui.conversation_show_page.select_agent') }}</option>
                    @foreach($teamAgents as $agent)
                        <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" @click="showReassign = false">{{ __('ui.conversation_show_page.cancel') }}</button>
                <button type="button" class="btn btn-primary" :disabled="!reassignAgentId || actionLoading" @click="reassign()">{{ __('ui.conversation_show_page.confirm') }}</button>
            </div>
        </div>
    </div>
</div>

<style>
.conversation-shell {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 340px;
    gap: 1rem;
    height: calc(100vh - 11.5rem);
}
.conversation-main {
    padding: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-height: 0;
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
    transition: background .3s, box-shadow .3s;
}
.presence-dot-reconnecting {
    background: #f59e0b;
    box-shadow: 0 0 0 5px rgba(245,158,11,.14);
    animation: pulse-amber 1.4s ease-in-out infinite;
}
@keyframes pulse-amber {
    0%, 100% { box-shadow: 0 0 0 3px rgba(245,158,11,.16); }
    50%       { box-shadow: 0 0 0 7px rgba(245,158,11,.04); }
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
.msg-media-image {
    display: block;
    max-width: 240px;
    max-height: 200px;
    border-radius: 10px;
    cursor: pointer;
    margin-bottom: 6px;
    object-fit: cover;
}
.msg-media-video {
    display: block;
    max-width: 280px;
    border-radius: 10px;
    margin-bottom: 6px;
}
.msg-media-audio {
    display: block;
    width: 100%;
    min-width: 200px;
    margin-bottom: 6px;
}
.msg-media-doc {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    border-radius: 8px;
    background: rgba(0,0,0,.06);
    text-decoration: none;
    font-size: .82rem;
    font-weight: 600;
    color: inherit;
    margin-bottom: 6px;
}
.msg-media-doc i { font-size: 1.2rem; }
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
.wa-number-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: .68rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 999px;
    letter-spacing: .03em;
}
.wa-number-checking {
    background: rgba(100,116,139,.12);
    color: var(--text-muted);
}
.wa-number-ok {
    background: rgba(34,197,94,.12);
    color: #15803d;
}
.wa-number-fail {
    background: rgba(239,68,68,.10);
    color: #b91c1c;
}
.wa-profile-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    border-radius: 999px;
    font-size: .75rem;
    vertical-align: middle;
    margin-left: 4px;
}
.wa-profile-badge.wa-number-ok { background: rgba(34,197,94,.25); color: #15803d; }
.wa-profile-badge.wa-number-fail { background: rgba(239,68,68,.18); color: #b91c1c; }
.composer-attach-btn {
    width: 40px;
    height: 40px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 10px;
    font-size: 1.1rem;
    color: var(--text-secondary);
}
.composer-attach-btn:hover:not(:disabled) {
    color: var(--brand);
    background: rgba(37,99,235,.08);
}
.media-preview-bar {
    margin-bottom: 10px;
    border-radius: 12px;
    border: 1.5px solid var(--card-border);
    overflow: hidden;
    background: #f8fafc;
}
.media-preview-inner {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
}
.media-preview-thumb {
    width: 52px;
    height: 52px;
    object-fit: cover;
    border-radius: 8px;
    flex-shrink: 0;
}
.media-preview-icon {
    width: 52px;
    height: 52px;
    border-radius: 8px;
    background: linear-gradient(135deg,#e0f2fe,#bfdbfe);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #1d4ed8;
    flex-shrink: 0;
}
.media-preview-meta {
    flex: 1;
    min-width: 0;
}
.media-preview-name {
    font-size: .82rem;
    font-weight: 600;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.media-preview-type {
    font-size: .72rem;
    color: var(--text-muted);
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: .04em;
    margin-top: 2px;
}
.media-preview-remove {
    width: 28px;
    height: 28px;
    border-radius: 999px;
    border: none;
    background: rgba(239,68,68,.1);
    color: #dc2626;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
    font-size: .9rem;
}
.media-preview-remove:hover { background: rgba(239,68,68,.18); }
.media-upload-progress {
    height: 3px;
    background: #e5e7eb;
}
.media-upload-bar {
    height: 100%;
    background: var(--brand);
    transition: width .2s ease;
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
    min-height: 0;
    overflow-y: auto;
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
        height: auto;
        min-height: calc(100vh - 11.5rem);
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
        wsConnected: false,
        _pollTimer: null,
        _presenceState: null,
        _presenceTimer: null,

        mediaAttachment: null,
        mediaUploading: false,
        uploadProgress: 0,
        numberStatus: null,

        get canAct() {
            const role = '{{ auth()->user()->role }}';
            if (['admin', 'super_admin', 'supervisor'].includes(role)) return true;
            return this.agentId === {{ auth()->id() }};
        },

        async init() {
            this.subscribeChannel();
            this.markRead();
            this.setupWsTracking();
            this.checkCustomerNumber();
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) this.markRead();
            });
        },

        async checkCustomerNumber() {
            this.numberStatus = 'checking';
            try {
                const res = await fetch(`/api/conversations/${this.conversationId}/check-number`, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) { this.numberStatus = null; return; }
                const data = await res.json();
                if (data.exists === true)       this.numberStatus = 'exists';
                else if (data.exists === false) this.numberStatus = 'missing';
                else                            this.numberStatus = null;
            } catch {
                this.numberStatus = null;
            }
        },

        markRead() {
            fetch(`/api/conversations/${this.conversationId}/read`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? ''
                }
            }).catch(() => {});
        },

        setupWsTracking() {
            this.wsConnected = window._echoConnected === true;
            const self = this;
            window._echoStateListeners = window._echoStateListeners || [];
            window._echoStateListeners.push(function(connected) {
                self.wsConnected = connected;
                if (connected) {
                    self.pollNewMessages();
                }
            });
            // Always poll — WebSocket adds messages instantly when working;
            // polling is the guaranteed fallback regardless of WS state.
            this.startPolling();
        },

        startPolling() {
            this.stopPolling();
            this._pollTimer = setInterval(() => this.pollNewMessages(), 4000);
        },

        stopPolling() {
            if (this._pollTimer) { clearInterval(this._pollTimer); this._pollTimer = null; }
        },

        async pollNewMessages() {
            try {
                const res = await fetch(`/api/conversations/${this.conversationId}/messages?per_page=20`, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) return;
                const data = await res.json();
                const fresh = (data.data || []).map(m => this.normalizeMessage(m));
                const existingIds = new Set(this.messages.map(m => m.id));
                const added = fresh.filter(m => !existingIds.has(m.id));
                if (added.length > 0) {
                    this.messages = this.sortMessages([...this.messages, ...added]);
                    this.$nextTick(() => this.scrollToBottom());
                    this.markRead();
                }
            } catch(e) {}
        },

        sendPresence(presence) {
            if (this._presenceState === presence) return;
            this._presenceState = presence;
            fetch(`/api/conversations/${this.conversationId}/presence`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? ''
                },
                body: JSON.stringify({ presence })
            }).catch(() => {});
        },

        onTypingInput() {
            if (this.state !== 'claimed' || this.isNote) return;
            if (this._presenceState !== 'composing') this.sendPresence('composing');
            clearTimeout(this._presenceTimer);
            this._presenceTimer = setTimeout(() => this.sendPresence('paused'), 3000);
        },

        clearMedia() {
            this.mediaAttachment = null;
            this.uploadProgress  = 0;
            if (this.$refs.fileInput) this.$refs.fileInput.value = '';
        },

        async onFileSelected(event) {
            const file = event.target.files?.[0];
            if (!file) return;

            this.mediaUploading  = true;
            this.uploadProgress  = 0;

            const formData = new FormData();
            formData.append('file', file);

            try {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '/api/media/upload');
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name=csrf-token]').content);

                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        this.uploadProgress = Math.round((e.loaded / e.total) * 100);
                    }
                });

                const result = await new Promise((resolve, reject) => {
                    xhr.onload = () => {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            resolve(JSON.parse(xhr.responseText));
                        } else {
                            reject(new Error(xhr.responseText));
                        }
                    };
                    xhr.onerror = () => reject(new Error('Upload failed'));
                    xhr.send(formData);
                });

                this.mediaAttachment = result;
                this.uploadProgress  = 100;
            } catch (e) {
                window.showToast?.('error', 'File upload failed');
                this.clearMedia();
            } finally {
                this.mediaUploading = false;
            }
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
            const hasText  = this.draft.trim();
            const hasMedia = !!this.mediaAttachment;
            if ((!hasText && !hasMedia) || this.sending || this.state !== 'claimed' || this.mediaUploading) return;

            this.sending = true;
            clearTimeout(this._presenceTimer);
            const body      = this.draft.trim();
            const media     = this.mediaAttachment;
            this.draft      = '';
            this.clearMedia();

            const endpoint = this.isNote
                ? `/api/conversations/${this.conversationId}/notes`
                : `/api/conversations/${this.conversationId}/messages`;

            const payload = { body: body || null };
            if (media && !this.isNote) {
                payload.media_url  = media.url;
                payload.type       = media.type;
                payload.file_name  = media.file_name;
                payload.media_path = media.media_path;
            }

            try {
                const res = await fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify(payload)
                });
                if (!res.ok) {
                    const err = await res.json();
                    window.showToast?.('error', err.message || 'Failed to send');
                    this.draft = body;
                } else {
                    await res.json();
                    Livewire.dispatch('messages-refresh');
                    this.sendPresence('available');
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
                .listen('.message.received', () => {
                    Livewire.dispatch('messages-refresh');
                    this.markRead();
                })
                .listen('.message.sent', () => {
                    Livewire.dispatch('messages-refresh');
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
