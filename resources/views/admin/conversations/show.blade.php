@extends('layouts.admin')

@section('title', __('ui.conversation_show_page.title'))

@push('styles')
<style>
    /* Full-bleed chat workspace */
    main.page-content { padding: 0 !important; }
</style>
@endpush

@section('content')
@php
    $panelPrefix = auth()->user()->routeNamePrefix();
    $listJson = $conversationList->map(fn($c) => [
        'id'                   => $c->id,
        'customer_name'        => $c->customer?->displayNameOrPhone ?? '—',
        'phone'                => $c->customer?->phone_e164,
        'profile_pic_url'      => $c->customer?->profile_pic_url,
        'last_message_at'      => optional($c->last_message_at)->toIso8601String(),
        'last_message_preview' => $c->last_message_preview,
        'unread_count'         => (int) $c->unread_count,
        'state'                => $c->state,
        'owner_agent_id'       => $c->owner_agent_id,
        'team_id'              => $c->team_id,
        'instance_name'        => $c->instance?->name,
        'ai_suspended'         => (bool) $c->ai_suspended,
    ]);
    $preloadedJson    = $preloadedMessages ?? [];
    $cursorJson       = $messagesCursor ?? null;
    $savedRepliesJson = ($savedReplies ?? collect())->values();
    $onlineAgentsJson = $onlineAgents ?? collect();
    $teamAgentsJson   = $teamAgents->map(function ($a) {
        return ['id' => $a->id, 'name' => $a->name];
    })->values();
    $eventsJson       = $events->map(function ($e) {
        return [
            'id'         => $e->id,
            'type'       => $e->type,
            'actor_name' => $e->actor?->name,
            'created_at' => optional($e->created_at)->toIso8601String(),
        ];
    })->values();
@endphp
<div x-data="conversationPro()" x-init="init()" class="cw-root">

    {{-- =========================================================
         LEFT RAIL — Conversation list
    ========================================================== --}}
    <aside class="cw-rail">
        <div class="cw-rail-head">
            <div class="cw-rail-title">
                <span>{{ __('ui.conversation_show_page.breadcrumb') }}</span>
                <span class="cw-rail-count" x-text="filteredList.length"></span>
            </div>
            <div class="cw-search">
                <i class="ri-search-line"></i>
                <input type="text" x-model="listQuery" @input="onListQueryInput()" placeholder="{{ __('ui.conversation_show_page.search_placeholder') ?? 'Search conversations…' }}">
                <button x-show="listQuery" @click="listQuery=''" class="cw-search-clear" title="Clear">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="cw-tabs">
                <button :class="{active: listTab==='mine'}"   @click="onListTabChange('mine')">{{ __('ui.conversations_page.my_conversations') }}</button>
                <button :class="{active: listTab==='pool'}"   @click="onListTabChange('pool')">{{ __('ui.conversations_page.pool') }}</button>
                <button :class="{active: listTab==='all'}"    @click="onListTabChange('all')">{{ __('ui.conversations_page.all') }}</button>
                <button :class="{active: listTab==='closed'}" @click="onListTabChange('closed')">{{ __('ui.conversations_page.closed') }}</button>
            </div>
        </div>

        <div class="cw-rail-body">
            <template x-for="c in filteredList" :key="c.id">
                <a :href="`{{ route($panelPrefix.'.conversations.index') }}/${c.id}`"
                   @click.prevent="switchTo(c.id)"
                   class="cw-row"
                   :class="{ active: c.id === conversationId, unread: (c.unread_count||0) > 0 }">
                    <div class="cw-row-avatar">
                        <template x-if="c.profile_pic_url">
                            <img :src="c.profile_pic_url" :alt="c.customer_name" loading="lazy">
                        </template>
                        <template x-if="!c.profile_pic_url">
                            <span x-text="initialsOf(c.customer_name)"></span>
                        </template>
                    </div>
                    <div class="cw-row-body">
                        <div class="cw-row-top">
                            <span class="cw-row-name" x-text="c.customer_name"></span>
                            <span class="cw-row-time" x-text="timeAgoShort(c.last_message_at)"></span>
                        </div>
                        <div class="cw-row-bottom">
                            <span class="cw-row-preview" x-text="c.last_message_preview || '—'"></span>
                            <template x-if="(c.unread_count||0) > 0">
                                <span class="cw-row-badge" x-text="c.unread_count > 99 ? '99+' : c.unread_count"></span>
                            </template>
                        </div>
                        <div class="cw-row-meta">
                            <span class="cw-pill" :class="`cw-pill-${c.state}`" x-text="stateLabel(c.state)"></span>
                            <span class="cw-row-instance" x-show="c.instance_name" x-text="c.instance_name"></span>
                        </div>
                    </div>
                </a>
            </template>
            <template x-if="filteredList.length === 0">
                <div class="cw-empty">
                    <i class="ri-inbox-line"></i>
                    <span x-text="i18n.no_conversations"></span>
                </div>
            </template>
        </div>
    </aside>

    {{-- =========================================================
         MIDDLE — Chat thread
    ========================================================== --}}
    <section class="cw-thread">

        <header class="cw-thread-head">
            <a href="{{ route($panelPrefix . '.conversations.index') }}" class="cw-back" title="{{ __('ui.conversation_show_page.back') }}">
                <i class="ri-arrow-left-line"></i>
            </a>
            <div class="cw-thread-contact">
                <div class="cw-avatar cw-avatar-lg">
                    <template x-if="customerProfilePic">
                        <img :src="customerProfilePic" :alt="customerName">
                    </template>
                    <template x-if="!customerProfilePic">
                        <span x-text="customerInitials"></span>
                    </template>
                </div>
                <div class="cw-thread-copy">
                    <div class="cw-thread-name" x-text="customerName"></div>
                    <div class="cw-thread-meta">
                        <span class="cw-presence" :class="wsConnected ? 'online' : 'offline'"></span>
                        <span x-show="!customerTyping" x-text="wsConnected ? i18n.live_workspace : i18n.reconnecting"></span>
                        <span x-show="customerTyping" class="cw-typing-inline" x-cloak>
                            <span class="cw-typing-dot"></span>
                            <span class="cw-typing-dot"></span>
                            <span class="cw-typing-dot"></span>
                            <span class="cw-typing-text" x-text="peerTypingName ? i18n.agent_typing.replace(':name', peerTypingName) : i18n.typing"></span>
                        </span>
                        <span class="cw-dot-sep"></span>
                        <span x-text="customerPhone"></span>
                        <span class="cw-wa-badge cw-wa-ok" x-show="numberStatus === 'exists'" :title="i18n.whatsapp"><i class="ri-whatsapp-line"></i></span>
                        <span class="cw-wa-badge cw-wa-fail" x-show="numberStatus === 'missing'" :title="i18n.not_on_whatsapp"><i class="ri-close-circle-line"></i></span>
                    </div>
                </div>
            </div>

            <div class="cw-thread-actions">
                <span x-show="state === 'pool'"    class="cw-state-badge cw-state-pool"><i class="ri-time-line"></i> <span x-text="i18n.state_pool"></span></span>
                <span x-show="state === 'claimed'" class="cw-state-badge cw-state-claimed"><i class="ri-user-line"></i> <span x-text="i18n.state_claimed"></span></span>
                <span x-show="state === 'closed'"  class="cw-state-badge cw-state-closed"><i class="ri-check-double-line"></i> <span x-text="i18n.state_closed"></span></span>
                <span x-show="aiSuspended" class="cw-state-badge cw-state-neutral">{{ __('ui.conversation_show_page.ai_off') }}</span>

                <template x-if="state === 'pool' && perms.can_claim">
                    <button @click="claim()" :disabled="actionLoading" class="btn btn-primary btn-sm">
                        <i class="ri-hand-coin-line"></i> {{ __('ui.conversation_show_page.claim') }}
                    </button>
                </template>

                <template x-if="state === 'claimed' && canAct">
                    <div class="cw-thread-actions-inline">
                        <button x-show="perms.can_reassign" @click="showReassign = true" class="btn btn-outline btn-sm" :title="@js(__('ui.conversation_show_page.reassign'))">
                            <i class="ri-user-settings-line"></i>
                        </button>
                        <button x-show="perms.can_release" @click="release()" class="btn btn-outline btn-sm" :disabled="actionLoading" :title="@js(__('ui.conversation_show_page.release'))">
                            <i class="ri-reply-line"></i>
                        </button>
                        <button x-show="perms.can_close" @click="closeConv()" class="btn btn-danger btn-sm" :disabled="actionLoading" :title="@js(__('ui.conversation_show_page.close'))">
                            <i class="ri-close-circle-line"></i>
                        </button>
                    </div>
                </template>

                <template x-if="state === 'closed' && perms.can_reopen">
                    <button @click="reopen()" class="btn btn-outline btn-sm" :disabled="actionLoading">
                        <i class="ri-refresh-line"></i> {{ __('ui.conversation_show_page.reopen') }}
                    </button>
                </template>

                <template x-if="state !== 'closed' && perms.can_toggle_ai">
                    <button @click="toggleAi()" class="btn btn-outline btn-sm" :disabled="actionLoading" :title="aiSuspended ? @js(__('ui.conversation_show_page.resume_ai')) : @js(__('ui.conversation_show_page.suspend_ai'))">
                        <i class="ri-robot-2-line"></i>
                    </button>
                </template>
            </div>
        </header>

        <div id="messages-scroll" class="cw-stage">
            <div x-show="hasMoreMessages" class="cw-load-more">
                <button @click="loadMoreMessages()" :disabled="loadingMessages" class="btn btn-ghost btn-sm">
                    <span x-show="!loadingMessages">{{ __('ui.conversation_show_page.load_earlier') }}</span>
                    <span x-show="loadingMessages"><span class="btn-spinner" style="width:14px;height:14px;border-width:2px;"></span></span>
                </button>
            </div>

            <template x-for="(msg, index) in messages" :key="msg.id">
                <div>
                    <template x-if="showDateSeparator(index)">
                        <div class="cw-date-sep"><span x-text="formatDateSeparator(msg.sort_ts)"></span></div>
                    </template>
                    <div class="cw-msg" :class="msg.direction === 'out' ? 'cw-msg-out' : 'cw-msg-in'">
                        <div class="cw-msg-avatar" x-show="msg.direction === 'in'" x-text="customerInitials"></div>
                        <div class="cw-msg-stack">
                            <template x-if="msg.ai_metadata && msg.ai_metadata.is_note">
                                <div class="cw-msg-chip cw-msg-chip-note">{{ __('ui.conversation_show_page.internal_note') ?? 'Internal note' }}</div>
                            </template>
                            <template x-if="msg.author_type === 'ai'">
                                <div class="cw-msg-chip cw-msg-chip-ai"><i class="ri-robot-2-line"></i> {{ __('ui.conversation_show_page.ai_reply') ?? 'AI reply' }}</div>
                            </template>
                            <div class="cw-bubble" :class="bubbleClass(msg)">
                                <template x-if="msg.media_url && msg.type === 'image'">
                                    <img :src="msg.media_url" class="cw-media-img" loading="lazy">
                                </template>
                                <template x-if="msg.media_url && msg.type === 'video'">
                                    <video :src="msg.media_url" controls class="cw-media-vid" preload="metadata"></video>
                                </template>
                                <template x-if="msg.media_url && msg.type === 'audio'">
                                    <audio :src="msg.media_url" controls class="cw-media-aud" preload="metadata"></audio>
                                </template>
                                <template x-if="msg.media_url && msg.type === 'document'">
                                    <a :href="msg.media_url" target="_blank" class="cw-media-doc">
                                        <i class="ri-file-download-line"></i>
                                        <span x-text="(msg.ai_metadata && msg.ai_metadata.file_name) ? msg.ai_metadata.file_name : i18n.document"></span>
                                    </a>
                                </template>
                                <div x-show="msg.body" class="cw-msg-body" x-text="msg.body"></div>
                                <div class="cw-msg-foot">
                                    <span x-text="formatTime(msg.sort_ts)"></span>
                                    <template x-if="msg.direction === 'out'">
                                        <span class="cw-ticks" :class="tickClass(msg)" x-html="ticksHtml(msg)"></span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <div id="scroll-anchor"></div>
        </div>

        <div x-show="state !== 'closed'" class="cw-composer-wrap">
            <div class="cw-quick">
                <template x-for="reply in topSavedReplies" :key="reply.id">
                    <button type="button" class="cw-quick-btn" @click="applySavedReply(reply)" :title="reply.shortcut || ''">
                        <i class="ri-flashlight-line"></i>
                        <span x-text="reply.title"></span>
                    </button>
                </template>
                <button type="button" class="cw-quick-btn" @click="openRepliesPicker()" :title="i18n.browse_replies">
                    <i class="ri-add-line"></i> <span x-text="i18n.more"></span>
                </button>
                <button type="button" class="cw-quick-btn cw-quick-toggle" @click="isNote = !isNote" :class="{active: isNote}">
                    <i class="ri-sticky-note-line"></i> {{ __('ui.conversation_show_page.note_mode') }}
                </button>
            </div>

            <div x-show="showRepliesPicker" x-cloak class="cw-replies-pop" @click.outside="showRepliesPicker = false">
                <div class="cw-replies-pop-head">
                    <i class="ri-search-line"></i>
                    <input type="text" x-model="replySearch" x-ref="replySearchInput" :placeholder="i18n.search_replies_placeholder">
                    <button type="button" class="cw-replies-pop-close" @click="showRepliesPicker = false"><i class="ri-close-line"></i></button>
                </div>
                <div class="cw-replies-pop-body">
                    <template x-for="(reply, idx) in filteredReplies" :key="reply.id">
                        <button type="button" class="cw-reply-item" :class="{ active: idx === replyHighlight }"
                                @click="applySavedReply(reply); showRepliesPicker = false"
                                @mouseenter="replyHighlight = idx">
                            <div class="cw-reply-item-head">
                                <span class="cw-reply-item-title" x-text="reply.title"></span>
                                <span x-show="reply.shortcut" class="cw-reply-item-shortcut" x-text="reply.shortcut"></span>
                                <span class="cw-reply-item-scope" :class="`cw-scope-${reply.scope}`" x-text="reply.scope"></span>
                            </div>
                            <div class="cw-reply-item-body" x-text="reply.body"></div>
                        </button>
                    </template>
                    <template x-if="filteredReplies.length === 0">
                        <div class="cw-empty-soft" x-text="i18n.no_replies_match"></div>
                    </template>
                </div>
            </div>

            <div x-show="mediaAttachment || mediaUploading" class="cw-attach-card" :class="`cw-attach-${(mediaAttachment?.type || 'document')}`" x-cloak>
                <div class="cw-attach-thumb">
                    <template x-if="mediaAttachment?.type === 'image'">
                        <img :src="mediaAttachment.url" :alt="mediaAttachment.file_name">
                    </template>
                    <template x-if="mediaAttachment?.type !== 'image'">
                        <i :class="mediaIconFor(mediaAttachment?.type)"></i>
                    </template>
                </div>
                <div class="cw-attach-info">
                    <div class="cw-attach-name" x-text="mediaAttachment?.file_name || i18n.uploading + '…'"></div>
                    <div class="cw-attach-sub">
                        <span class="cw-attach-type" x-text="(mediaAttachment?.type || '').toUpperCase()"></span>
                        <span x-show="mediaAttachment?.size_label" x-text="mediaAttachment?.size_label"></span>
                        <span x-show="mediaUploading" class="cw-attach-uploading" x-text="`· ${i18n.uploading} ${uploadProgress}%`"></span>
                    </div>
                    <div class="cw-attach-bar" x-show="mediaUploading || uploadProgress > 0 && uploadProgress < 100">
                        <div class="cw-attach-bar-fill" :style="`width:${uploadProgress}%`"></div>
                    </div>
                </div>
                <button type="button" class="cw-attach-close" @click="clearMedia()" :title="i18n.remove">
                    <i class="ri-close-line"></i>
                </button>
            </div>

            <div class="cw-composer" :class="{ 'cw-composer-note': isNote, 'cw-composer-drop': isDragging }"
                 @dragenter.prevent="onDragEnter($event)"
                 @dragover.prevent="onDragOver($event)"
                 @dragleave.prevent="onDragLeave($event)"
                 @drop.prevent="onDrop($event)">
                <input type="file" x-ref="fileInput" style="display:none"
                       accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip"
                       @change="onFileSelected($event)">

                <div class="cw-attach-group" :class="{ 'is-disabled': state !== 'claimed' || isNote || mediaUploading }">
                    <button type="button" class="cw-attach-btn cw-attach-main"
                            :disabled="state !== 'claimed' || isNote || mediaUploading"
                            @click="$refs.fileInput.click()" :title="i18n.attach_file">
                        <span x-show="!mediaUploading"><i class="ri-attachment-2"></i></span>
                        <span x-show="mediaUploading"><span class="btn-spinner"></span></span>
                    </button>
                    <div class="cw-attach-fly" x-show="state === 'claimed' && !isNote">
                        <button type="button" class="cw-attach-fly-btn" @click="$refs.fileInput.click()" :title="i18n.attach_image">
                            <i class="ri-image-line"></i>
                        </button>
                        <button type="button" class="cw-attach-fly-btn" @click="$refs.fileInput.click()" :title="i18n.attach_document">
                            <i class="ri-file-text-line"></i>
                        </button>
                        <button type="button" class="cw-attach-fly-btn" @click="$refs.fileInput.click()" :title="i18n.attach_audio">
                            <i class="ri-mic-line"></i>
                        </button>
                    </div>
                </div>

                <textarea x-model="draft" rows="1" @keydown="handleComposerKeydown($event)" @input="autoResize($el); onTypingInput();"
                          :placeholder="mediaAttachment ? i18n.caption_placeholder : (isNote ? @js(__('ui.conversation_show_page.note_placeholder')) : @js(__('ui.conversation_show_page.message_placeholder')))"
                          class="cw-textarea"></textarea>
                <button @click="send()" :disabled="(!draft.trim() && !mediaAttachment) || sending || state !== 'claimed' || mediaUploading" class="cw-send">
                    <span x-show="!sending"><i class="ri-send-plane-2-fill"></i></span>
                    <span x-show="sending"><span class="btn-spinner"></span></span>
                </button>

                <div class="cw-drop-overlay" x-show="isDragging" x-cloak>
                    <i class="ri-upload-cloud-2-line"></i>
                    <div class="cw-drop-title" x-text="i18n.drop_to_send"></div>
                    <div class="cw-drop-hint" x-text="i18n.drop_hint"></div>
                </div>
            </div>
            <div x-show="state === 'pool'" class="cw-composer-hint">
                <i class="ri-information-line"></i> {{ __('ui.conversation_show_page.claim_hint') }}
            </div>
        </div>

        <div x-show="state === 'closed'" class="cw-closed-banner">
            <i class="ri-lock-line"></i> {{ __('ui.conversation_show_page.closed_banner') }}
        </div>
    </section>

    {{-- =========================================================
         RIGHT RAIL — Customer profile + workspace
    ========================================================== --}}
    <aside class="cw-side">
        <div class="cw-profile">
            <div class="cw-profile-avatar">
                <template x-if="customerProfilePic">
                    <img :src="customerProfilePic" :alt="customerName">
                </template>
                <template x-if="!customerProfilePic">
                    <span x-text="customerInitials"></span>
                </template>
            </div>
            <div class="cw-profile-name" x-text="customerName"></div>
            <div class="cw-profile-company" x-text="tenantName || @js(__('ui.conversation_show_page.workspace_contact'))"></div>
            <div class="cw-profile-phone">
                <i class="ri-phone-line"></i> <span x-text="customerPhone"></span>
            </div>
            <div class="cw-profile-actions">
                <a :href="customerProfileUrl" class="cw-profile-btn" :title="i18n.profile">
                    <i class="ri-user-line"></i>
                </a>
                <a :href="`https://wa.me/${(customerPhone||'').replace(/^\+/, '')}`" target="_blank" class="cw-profile-btn" :title="i18n.whatsapp">
                    <i class="ri-whatsapp-line"></i>
                </a>
                <a :href="`tel:${customerPhone}`" class="cw-profile-btn" :title="i18n.call">
                    <i class="ri-phone-line"></i>
                </a>
            </div>
        </div>

        <div class="cw-side-card cw-roster" x-show="onlineAgents.length > 0">
            <div class="cw-roster-head">
                <span><i class="ri-team-line"></i> <span x-text="i18n.team_online"></span></span>
                <span class="cw-roster-count" x-text="`${onlineCount}/${onlineAgents.length}`"></span>
            </div>
            <div class="cw-roster-body">
                <template x-for="agent in onlineAgents" :key="agent.id">
                    <div class="cw-roster-row" :class="{ 'is-self': agent.id === _meId }">
                        <div class="cw-roster-avatar">
                            <span x-text="initialsOf(agent.name)"></span>
                            <span class="cw-roster-dot" :class="agent.online ? 'on' : 'off'"></span>
                        </div>
                        <div class="cw-roster-meta">
                            <div class="cw-roster-name" x-text="agent.name"></div>
                            <div class="cw-roster-role" x-text="agent.role"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="cw-side-card">
            <div class="cw-side-tabs">
                <button :class="{active: sideTab==='details'}"  @click="sideTab='details'">{{ __('ui.conversation_show_page.details') }}</button>
                <button :class="{active: sideTab==='timeline'}" @click="sideTab='timeline'">{{ __('ui.conversation_show_page.timeline') }}</button>
            </div>

            <div x-show="sideTab === 'details'" class="cw-side-panel">
                <div class="cw-meta-row"><span>{{ __('ui.conversation_show_page.tenant') }}</span><strong x-text="tenantName || '—'"></strong></div>
                <div class="cw-meta-row"><span>{{ __('ui.conversation_show_page.instance') }}</span><strong x-text="instanceName || '—'"></strong></div>
                <div class="cw-meta-row"><span>{{ __('ui.conversation_show_page.team') }}</span><strong x-text="teamName || '—'"></strong></div>
                <div class="cw-meta-row"><span>{{ __('ui.conversation_show_page.owner') }}</span><strong x-text="agentName || i18n.unassigned"></strong></div>
                <div class="cw-meta-row"><span>{{ __('ui.conversation_show_page.started') }}</span><strong x-text="formatStarted(conversationCreatedAt)"></strong></div>
                <div class="cw-meta-row"><span>{{ __('ui.conversation_show_page.last_activity') }}</span><strong x-text="timeAgo(lastMessageAt)"></strong></div>
                <div class="cw-meta-row"><span>{{ __('ui.conversation_show_page.total_customer_convos') }}</span><strong x-text="customerConvosCount"></strong></div>
                <div class="cw-meta-row"><span>{{ __('ui.conversation_show_page.ai_mode') }}</span><strong x-text="aiSuspended ? i18n.suspended : aiMode"></strong></div>
            </div>

            <div x-show="sideTab === 'timeline'" class="cw-side-panel">
                <div class="cw-timeline">
                    <template x-for="ev in events" :key="ev.id">
                        <div class="cw-timeline-item">
                            <div class="cw-timeline-dot"></div>
                            <div class="cw-timeline-body">
                                <div class="cw-timeline-type" x-text="(ev.type || '').replace(/_/g, ' ').toUpperCase()"></div>
                                <div class="cw-timeline-meta"><span x-text="ev.actor_name || i18n.system"></span> · <span x-text="timeAgo(ev.created_at)"></span></div>
                            </div>
                        </div>
                    </template>
                    <template x-if="events.length === 0">
                        <div class="cw-empty-soft">{{ __('ui.conversation_show_page.no_events') }}</div>
                    </template>
                </div>
            </div>
        </div>
    </aside>

    <div x-show="showReassign" x-cloak class="modal-overlay show" @click.self="showReassign = false">
        <div class="modal-box" style="max-width:420px;text-align:left;" @click.stop>
            <div class="modal-send-icon"><i class="ri-user-settings-line"></i></div>
            <h3>{{ __('ui.conversation_show_page.reassign_title') }}</h3>
            <p>{{ __('ui.conversation_show_page.reassign_desc') }}</p>
            <div style="margin:12px 0 18px;">
                <select x-model="reassignAgentId" class="form-control" data-no-ss>
                    <option value="">{{ __('ui.conversation_show_page.select_agent') }}</option>
                    <template x-for="agent in teamAgents" :key="agent.id">
                        <option :value="agent.id" x-text="agent.name"></option>
                    </template>
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
/* ============================================================
   CHAT WORKSPACE (Intercom-style 3-pane)
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
.cw-search {
    position: relative;
    margin-bottom: 10px;
}
.cw-search i.ri-search-line {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: .95rem;
}
.cw-search input {
    width: 100%;
    padding: 8px 30px 8px 32px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: .85rem;
    background: #f8fafc;
    outline: none;
    transition: all .15s;
}
.cw-search input:focus {
    border-color: #6366f1;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(99,102,241,.12);
}
.cw-search-clear {
    position: absolute;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
    border: none;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    padding: 4px;
    border-radius: 6px;
}
.cw-search-clear:hover { background: #f1f5f9; color: #475569; }
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
}
.cw-tabs button:hover { color: #0f172a; }
.cw-tabs button.active {
    background: #fff;
    color: #4338ca;
    box-shadow: 0 1px 3px rgba(15,23,42,.06);
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
    text-decoration: none;
    color: inherit;
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
.cw-row.unread .cw-row-name { font-weight: 800; }
.cw-row.unread .cw-row-preview { color: #0f172a; font-weight: 600; }
.cw-row-avatar {
    width: 40px;
    height: 40px;
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
.cw-row-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
.cw-avatar img, .cw-profile-avatar img {
    width: 100%; height: 100%; border-radius: inherit; object-fit: cover; display: block;
}
.cw-avatar { overflow: hidden; }
.cw-profile-avatar { overflow: hidden; }
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
.cw-row-badge {
    background: #ef4444;
    color: #fff;
    font-size: .65rem;
    font-weight: 800;
    padding: 1px 7px;
    border-radius: 999px;
    min-width: 18px;
    text-align: center;
}
.cw-row-meta {
    display: flex;
    gap: 6px;
    margin-top: 6px;
    align-items: center;
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
.cw-back {
    display: none;
    width: 36px; height: 36px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #475569;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-size: 1.05rem;
}
.cw-back:hover { background: #e2e8f0; }
.cw-thread-contact {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
    flex: 1;
}
.cw-avatar {
    width: 44px;
    height: 44px;
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
.cw-thread-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: .76rem;
    color: #64748b;
    margin-top: 2px;
    flex-wrap: wrap;
}
.cw-presence {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    flex-shrink: 0;
}
.cw-presence.online  { background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,.18); }
.cw-presence.offline { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,.18); animation: cw-pulse 1.6s ease-in-out infinite; }
@keyframes cw-pulse {
    0%,100% { box-shadow: 0 0 0 3px rgba(245,158,11,.2); }
    50%     { box-shadow: 0 0 0 6px rgba(245,158,11,.05); }
}
.cw-dot-sep {
    width: 3px; height: 3px; border-radius: 999px; background: #cbd5e1; flex-shrink: 0;
}
.cw-wa-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: .65rem;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 999px;
}
.cw-wa-ok { background: rgba(34,197,94,.14); color: #15803d; }
.cw-wa-fail { background: rgba(239,68,68,.12); color: #b91c1c; }

.cw-thread-actions {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
}
.cw-thread-actions-inline { display: flex; gap: 6px; }
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
.cw-load-more {
    text-align: center;
    margin-bottom: 10px;
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
    width: 28px;
    height: 28px;
    border-radius: 999px;
    background: linear-gradient(135deg, #0f172a, #334155);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .62rem;
    font-weight: 700;
    flex-shrink: 0;
    margin-bottom: 4px;
}
.cw-msg-stack {
    max-width: 70%;
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.cw-msg-chip {
    align-self: flex-start;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: .62rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    display: inline-flex;
    gap: 4px;
    align-items: center;
}
.cw-msg-chip-note { background: #fef3c7; color: #92400e; }
.cw-msg-chip-ai   { background: #ccfbf1; color: #0f766e; }
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
.cw-bubble-note {
    background: #fffbeb;
    border: 1px dashed #f59e0b;
    color: #92400e;
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
.cw-ticks { font-size: .78rem; line-height: 1; }
.cw-tick-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    animation: cw-tick-pop .25s cubic-bezier(.34,1.56,.64,1) both;
}
@keyframes cw-tick-pop {
    from { opacity: 0; transform: scale(.5); }
    to   { opacity: 1; transform: scale(1); }
}
.cw-tick-wrap i { font-size: .82rem; }
.cw-tick-clock i { color: rgba(255,255,255,.65); }
.cw-tick-blue i  { color: #38bdf8; }
.cw-ticks-sent     .cw-tick-wrap i { color: rgba(255,255,255,.65); }
.cw-ticks-failed   .cw-tick-wrap i { color: #fca5a5; }
.cw-ticks-pending  .cw-tick-wrap i { color: rgba(255,255,255,.55); }
.cw-media-img {
    max-width: 260px;
    max-height: 220px;
    border-radius: 10px;
    display: block;
    margin-bottom: 6px;
    object-fit: cover;
    cursor: pointer;
}
.cw-media-vid {
    max-width: 280px;
    border-radius: 10px;
    display: block;
    margin-bottom: 6px;
}
.cw-media-aud { width: 240px; margin-bottom: 6px; }
.cw-media-doc {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    border-radius: 8px;
    background: rgba(0,0,0,.06);
    text-decoration: none;
    color: inherit;
    font-weight: 600;
    font-size: .82rem;
    margin-bottom: 6px;
}
.cw-bubble-out .cw-media-doc { background: rgba(255,255,255,.18); color: #fff; }
.cw-media-doc i { font-size: 1.15rem; }

/* ---- COMPOSER ---- */
.cw-composer-wrap {
    border-top: 1px solid #eef0f4;
    background: #fff;
    padding: 12px 18px 16px;
}
.cw-quick {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-bottom: 8px;
}
.cw-quick-btn {
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #475569;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: .76rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all .15s;
}
.cw-quick-btn:hover { background: #fff; border-color: #cbd5e1; color: #0f172a; }
.cw-quick-toggle.active {
    background: #fef3c7;
    border-color: #fcd34d;
    color: #92400e;
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
.cw-composer-note { background: #fffbeb; border-color: #fcd34d; }
.cw-composer-btn {
    width: 38px;
    height: 38px;
    border: none;
    background: transparent;
    color: #64748b;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}
.cw-composer-btn:hover:not(:disabled) { background: #fff; color: #4338ca; }
.cw-composer-btn:disabled { opacity: .4; cursor: not-allowed; }
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
    width: 40px;
    height: 40px;
    border: none;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #fff;
    border-radius: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    box-shadow: 0 4px 10px rgba(99,102,241,.3);
    transition: all .15s;
}
.cw-send:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 14px rgba(99,102,241,.4); }
.cw-send:disabled { opacity: .4; cursor: not-allowed; box-shadow: none; }
.cw-composer-hint {
    text-align: center;
    font-size: .75rem;
    color: #94a3b8;
    margin-top: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
}
.cw-closed-banner {
    padding: 16px;
    text-align: center;
    background: #f1f5f9;
    color: #475569;
    font-size: .82rem;
    font-weight: 600;
    border-top: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
/* ---- PRO UPLOAD CARD ---- */
.cw-attach-card {
    display: flex;
    align-items: center;
    gap: 12px;
    background: linear-gradient(180deg, #ffffff, #f8fafc);
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 10px 12px;
    margin-bottom: 10px;
    box-shadow: 0 6px 18px rgba(15, 23, 42, .05);
    position: relative;
    overflow: hidden;
}
.cw-attach-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, #6366f1, #8b5cf6, #ec4899);
}
.cw-attach-image  .cw-attach-thumb { background: linear-gradient(135deg, #22c55e, #16a34a); }
.cw-attach-video  .cw-attach-thumb { background: linear-gradient(135deg, #ef4444, #dc2626); }
.cw-attach-audio  .cw-attach-thumb { background: linear-gradient(135deg, #f59e0b, #d97706); }
.cw-attach-document .cw-attach-thumb { background: linear-gradient(135deg, #6366f1, #4338ca); }

.cw-attach-thumb {
    width: 52px; height: 52px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1.4rem;
    flex-shrink: 0;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(15, 23, 42, .12);
}
.cw-attach-thumb img { width: 100%; height: 100%; object-fit: cover; }

.cw-attach-info { flex: 1; min-width: 0; }
.cw-attach-name {
    font-size: .87rem; font-weight: 700; color: #0f172a;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.cw-attach-sub {
    display: flex; gap: 6px; align-items: center;
    margin-top: 3px;
    font-size: .7rem; color: #64748b; font-weight: 600;
}
.cw-attach-type {
    background: #eef2ff; color: #4338ca;
    padding: 1px 7px; border-radius: 999px;
    text-transform: uppercase; letter-spacing: .04em;
    font-size: .62rem;
}
.cw-attach-uploading { color: #4338ca; font-weight: 700; }
.cw-attach-bar {
    margin-top: 6px;
    height: 4px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
}
.cw-attach-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #6366f1, #4f46e5);
    border-radius: inherit;
    transition: width .2s ease-out;
    background-size: 200% 100%;
    animation: cw-shimmer 1.4s linear infinite;
}
@keyframes cw-shimmer {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
.cw-attach-close {
    width: 30px; height: 30px;
    border-radius: 999px; border: none;
    background: rgba(239,68,68,.12); color: #dc2626;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    transition: all .15s;
}
.cw-attach-close:hover { background: rgba(239,68,68,.22); transform: scale(1.06); }

/* Attach button group with fly-out */
.cw-attach-group { position: relative; display: flex; }
.cw-attach-btn {
    width: 40px; height: 40px;
    border: none;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    color: #475569; border-radius: 12px;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.15rem;
    transition: all .18s cubic-bezier(.34,1.56,.64,1);
    box-shadow: 0 1px 3px rgba(15,23,42,.08);
}
.cw-attach-btn:hover:not(:disabled) {
    background: linear-gradient(135deg, #eef2ff, #e0e7ff);
    color: #4338ca;
    transform: rotate(-10deg) scale(1.08);
    box-shadow: 0 4px 12px rgba(99,102,241,.2);
}
.cw-attach-btn:disabled { opacity: .35; cursor: not-allowed; }
.cw-attach-fly {
    position: absolute;
    bottom: calc(100% + 10px); left: 50%;
    transform: translateX(-50%) translateY(8px);
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 6px;
    display: flex; gap: 4px;
    box-shadow: 0 12px 32px rgba(15, 23, 42, .14), 0 2px 8px rgba(15,23,42,.06);
    opacity: 0;
    pointer-events: none;
    transition: all .18s cubic-bezier(.34,1.56,.64,1);
    white-space: nowrap;
}
.cw-attach-fly::after {
    content: '';
    position: absolute;
    top: 100%; left: 50%;
    transform: translateX(-50%);
    border: 6px solid transparent;
    border-top-color: #fff;
    filter: drop-shadow(0 1px 1px rgba(0,0,0,.06));
}
.cw-attach-group:hover .cw-attach-fly,
.cw-attach-group:focus-within .cw-attach-fly {
    opacity: 1; pointer-events: auto; transform: translateX(-50%) translateY(0);
}
.cw-attach-group.is-disabled .cw-attach-fly { display: none; }
.cw-attach-fly-btn {
    width: 40px; height: 40px;
    border: none; background: transparent;
    color: #475569; border-radius: 10px;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    transition: all .15s;
    flex-direction: column;
    gap: 2px;
}
.cw-attach-fly-btn:hover { background: #eef2ff; color: #4338ca; transform: translateY(-2px); }
.cw-attach-fly-btn i { font-size: 1.2rem; }

/* Drag-drop overlay */
.cw-composer-drop {
    border-color: #6366f1 !important;
    background: rgba(99, 102, 241, .06) !important;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, .14) !important;
}
.cw-drop-overlay {
    position: absolute;
    inset: 0;
    background: rgba(248, 250, 252, .95);
    backdrop-filter: blur(4px);
    border-radius: 14px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    pointer-events: none;
    z-index: 10;
    color: #4338ca;
}
.cw-composer { position: relative; }
.cw-drop-overlay i {
    font-size: 2.2rem;
    margin-bottom: 4px;
    animation: cw-bounce 1.4s ease-in-out infinite;
}
@keyframes cw-bounce {
    0%, 100% { transform: translateY(0); }
    50%      { transform: translateY(-6px); }
}
.cw-drop-title { font-size: .95rem; font-weight: 700; color: #4338ca; }
.cw-drop-hint  { font-size: .75rem; color: #64748b; }

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
    backdrop-filter: blur(8px);
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
    margin-bottom: 8px;
}
.cw-profile-phone {
    font-size: .8rem;
    opacity: .85;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.cw-profile-actions {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 14px;
}
.cw-profile-btn {
    width: 36px; height: 36px;
    border-radius: 999px;
    background: rgba(255,255,255,.18);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    text-decoration: none;
    font-size: 1rem;
    transition: all .15s;
}
.cw-profile-btn:hover { background: rgba(255,255,255,.28); transform: translateY(-1px); }

.cw-side-card {
    background: #fff;
    border: 1px solid #e6e8ee;
    border-radius: 14px;
    overflow: hidden;
}
.cw-side-tabs {
    display: flex;
    border-bottom: 1px solid #eef0f4;
    background: #f8fafc;
}
.cw-side-tabs button {
    flex: 1;
    border: none;
    background: transparent;
    padding: 10px;
    font-size: .8rem;
    font-weight: 700;
    color: #64748b;
    cursor: pointer;
    position: relative;
}
.cw-side-tabs button:hover { color: #0f172a; }
.cw-side-tabs button.active { color: #4338ca; background: #fff; }
.cw-side-tabs button.active::after {
    content: '';
    position: absolute;
    left: 16px; right: 16px; bottom: 0;
    height: 2px;
    background: #6366f1;
    border-radius: 2px 2px 0 0;
}
.cw-side-panel { padding: 12px 16px 14px; }
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

.cw-timeline { display: flex; flex-direction: column; gap: 4px; }
.cw-timeline-item {
    display: flex;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid #f1f5f9;
}
.cw-timeline-item:last-child { border-bottom: 0; }
.cw-timeline-dot {
    width: 8px; height: 8px;
    border-radius: 999px;
    background: #6366f1;
    margin-top: 6px;
    box-shadow: 0 0 0 3px rgba(99,102,241,.15);
    flex-shrink: 0;
}
.cw-timeline-body { flex: 1; min-width: 0; }
.cw-timeline-type {
    font-size: .72rem; font-weight: 800;
    color: #0f172a;
    letter-spacing: .03em;
}
.cw-timeline-meta {
    font-size: .7rem;
    color: #94a3b8;
    margin-top: 2px;
}
.cw-empty-soft {
    font-size: .78rem;
    color: #94a3b8;
    text-align: center;
    padding: 20px 0;
}

/* ---- TYPING INDICATOR ---- */
.cw-typing-inline {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #15803d;
    font-weight: 600;
}
.cw-typing-dot {
    width: 5px; height: 5px;
    border-radius: 999px;
    background: #22c55e;
    animation: cw-typing-bounce 1.2s infinite ease-in-out;
}
.cw-typing-dot:nth-child(2) { animation-delay: .15s; }
.cw-typing-dot:nth-child(3) { animation-delay: .3s; }
.cw-typing-text { margin-left: 3px; font-size: .72rem; }
@keyframes cw-typing-bounce {
    0%, 80%, 100% { transform: translateY(0); opacity: .5; }
    40%           { transform: translateY(-3px); opacity: 1; }
}

/* ---- SAVED REPLIES POPOVER ---- */
.cw-replies-pop {
    position: relative;
    margin-bottom: 8px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(15, 23, 42, .08);
    max-height: 320px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.cw-replies-pop-head {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 10px;
    border-bottom: 1px solid #eef0f4;
    background: #f8fafc;
}
.cw-replies-pop-head i.ri-search-line { color: #94a3b8; }
.cw-replies-pop-head input {
    flex: 1;
    border: none;
    background: transparent;
    outline: none;
    font-size: .85rem;
    color: #0f172a;
}
.cw-replies-pop-close {
    border: none;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    border-radius: 6px;
    padding: 2px 4px;
}
.cw-replies-pop-close:hover { background: #f1f5f9; color: #475569; }
.cw-replies-pop-body { overflow-y: auto; padding: 4px; }
.cw-reply-item {
    width: 100%;
    text-align: left;
    border: none;
    background: transparent;
    padding: 8px 10px;
    border-radius: 8px;
    cursor: pointer;
    display: block;
}
.cw-reply-item:hover, .cw-reply-item.active { background: #eef2ff; }
.cw-reply-item-head {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 2px;
}
.cw-reply-item-title { font-size: .82rem; font-weight: 700; color: #0f172a; }
.cw-reply-item-shortcut {
    font-size: .65rem;
    font-weight: 700;
    background: #e0e7ff;
    color: #4338ca;
    padding: 1px 6px;
    border-radius: 999px;
    font-family: 'JetBrains Mono', ui-monospace, monospace;
}
.cw-reply-item-scope {
    margin-left: auto;
    font-size: .6rem;
    font-weight: 700;
    text-transform: uppercase;
    padding: 1px 7px;
    border-radius: 999px;
    letter-spacing: .04em;
}
.cw-scope-tenant   { background: #dcfce7; color: #15803d; }
.cw-scope-personal { background: #f1f5f9; color: #475569; }
.cw-reply-item-body {
    font-size: .76rem;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ---- ROSTER ---- */
.cw-roster { padding: 0; }
.cw-roster-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    background: #f8fafc;
    border-bottom: 1px solid #eef0f4;
    font-size: .78rem;
    font-weight: 700;
    color: #0f172a;
}
.cw-roster-head i { margin-right: 4px; color: #4338ca; }
.cw-roster-count {
    background: #ecfdf5;
    color: #047857;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: .68rem;
}
.cw-roster-body {
    padding: 6px 8px 10px;
    max-height: 220px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.cw-roster-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 6px;
    border-radius: 8px;
}
.cw-roster-row:hover { background: #f8fafc; }
.cw-roster-row.is-self { background: #eef2ff; }
.cw-roster-avatar {
    position: relative;
    width: 30px; height: 30px;
    border-radius: 999px;
    background: linear-gradient(135deg, #64748b, #334155);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: .65rem; font-weight: 700;
    flex-shrink: 0;
}
.cw-roster-dot {
    position: absolute;
    bottom: -1px; right: -1px;
    width: 10px; height: 10px;
    border-radius: 999px;
    border: 2px solid #fff;
}
.cw-roster-dot.on  { background: #22c55e; }
.cw-roster-dot.off { background: #cbd5e1; }
.cw-roster-meta { flex: 1; min-width: 0; }
.cw-roster-name {
    font-size: .8rem; font-weight: 600; color: #0f172a;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.cw-roster-role {
    font-size: .65rem; color: #94a3b8;
    text-transform: capitalize;
}

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
    .cw-back { display: inline-flex; }
}
</style>

<script>
function conversationPro() {
    return {
        i18n: @json($i18n),
        _role: '{{ auth()->user()->role }}',
        _basePath: @json(route($panelPrefix.'.conversations.index')),

        conversationId: {{ $conversation->id }},
        tenantId: {{ $conversation->tenant_id }},
        state: '{{ $conversation->state }}',
        agentName: @json($conversation->ownerAgent?->name),
        agentId: {{ $conversation->owner_agent_id ?? 'null' }},
        aiSuspended: {{ $conversation->ai_suspended ? 'true' : 'false' }},
        aiMode: @json($aiMode),

        // Customer + workspace meta (reactive across AJAX switches)
        customerId: {{ $conversation->customer_id ?? 'null' }},
        customerName: @json($conversation->customer->displayNameOrPhone),
        customerPhone: @json($conversation->customer->phone_e164),
        customerProfilePic: @json($conversation->customer->profile_pic_url ?? null),
        customerInitials: '{{ strtoupper(substr($conversation->customer->displayNameOrPhone, 0, 2)) }}',
        tenantName: @json($conversation->tenant?->name),
        instanceName: @json($conversation->instance?->name),
        teamName: @json($conversation->team?->name),
        teamAgents: @json($teamAgentsJson),
        events: @json($eventsJson),
        conversationCreatedAt: @json(optional($conversation->created_at)->toIso8601String()),
        lastMessageAt: @json(optional($conversation->last_message_at)->toIso8601String()),
        customerConvosCount: {{ (int) $customerConversationCount }},

        // Chat
        messages: (@json($preloadedJson)).map(m => ({ ...m, sort_ts: m.sent_at || m.created_at || null })),
        cursor: @json($cursorJson),
        hasMoreMessages: @json($cursorJson) !== null,
        loadingMessages: false,
        draft: '',
        isNote: false,
        sending: false,
        actionLoading: false,
        showReassign: false,
        reassignAgentId: '',
        sideTab: 'details',
        wsConnected: false,
        customerTyping: false,
        peerTypingName: '',
        _pollTimer: null,
        _presenceState: null,
        _presenceTimer: null,
        _customerTypingTimer: null,
        _heartbeatTimer: null,
        _rosterTimer: null,
        mediaAttachment: null,
        mediaUploading: false,
        uploadProgress: 0,
        numberStatus: null,
        isDragging: false,
        _dragCounter: 0,
        _echoChannel: null,
        _switching: false,

        // Saved replies
        savedReplies: @json($savedRepliesJson),
        showRepliesPicker: false,
        replySearch: '',
        replyHighlight: 0,

        // Online agents
        onlineAgents: @json($onlineAgentsJson),

        // Left rail
        list: @json($listJson),
        listQuery: '',
        listTab: 'mine',
        _listTimer: null,
        _listSearchTimer: null,
        _meId: {{ auth()->id() }},

        get canAct() {
            if (['admin', 'super_admin', 'supervisor'].includes(this._role)) return true;
            return this.agentId === this._meId;
        },

        get perms() {
            const isPriv  = ['admin', 'super_admin', 'supervisor'].includes(this._role);
            const isOwner = this.agentId === this._meId;
            return {
                can_claim:     this.state === 'pool'    && (isPriv || this._role === 'agent'),
                can_reassign:  this.state === 'claimed' && (isPriv),
                can_release:   this.state === 'claimed' && (isPriv || isOwner),
                can_close:     this.state === 'claimed' && (isPriv || isOwner),
                can_reopen:    this.state === 'closed'  && (isPriv),
                can_toggle_ai: this.state !== 'closed'  && (isPriv || isOwner),
            };
        },

        get customerProfileUrl() {
            return this.customerId ? `${this._basePath.replace('/conversations', '/customers')}/${this.customerId}` : '#';
        },

        get filteredList() {
            const q = this.listQuery.trim().toLowerCase();
            return this.list.filter(c => {
                if (this.listTab === 'mine'   && (c.state !== 'claimed' || c.owner_agent_id !== this._meId)) return false;
                if (this.listTab === 'pool'   && c.state !== 'pool') return false;
                if (this.listTab === 'closed' && c.state !== 'closed') return false;
                if (this.listTab === 'all'    && c.state === 'closed') return false;
                if (!q) return true;
                const hay = `${c.customer_name||''} ${c.phone||''} ${c.last_message_preview||''} ${c.instance_name||''}`.toLowerCase();
                return hay.includes(q);
            });
        },

        get topSavedReplies() {
            return (this.savedReplies || []).slice(0, 3);
        },

        get filteredReplies() {
            const q = this.replySearch.trim().toLowerCase();
            const all = this.savedReplies || [];
            if (!q) return all;
            return all.filter(r => {
                const hay = `${r.title||''} ${r.shortcut||''} ${r.body||''}`.toLowerCase();
                return hay.includes(q);
            });
        },

        get onlineCount() {
            return (this.onlineAgents || []).filter(a => a.online).length;
        },

        async init() {
            // Messages are preloaded — no fetch needed.
            this.scrollToBottom();
            this.subscribeChannel();
            this.markRead();
            this.setupWsTracking();
            this.checkCustomerNumber();
            this.startListPolling();
            this.startHeartbeat();
            this.startRosterPolling();
            this.refreshSavedReplies();
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    this.markRead();
                    this.sendHeartbeat();
                }
            });
            document.addEventListener('keydown', (e) => this.handleGlobalKey(e));
            window.addEventListener('popstate', (e) => this.onPopState(e));
        },

        async switchTo(id) {
            if (!id || id === this.conversationId || this._switching) return;
            this._switching = true;
            try {
                const res = await fetch(`/api/conversations/${id}/workspace`, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) {
                    let msg = `HTTP ${res.status}`;
                    try { const e = await res.json(); msg = e.message || msg; } catch {}
                    console.error('[switchTo] server error:', msg);
                    window.showToast?.('error', `${this.i18n.load_workspace_failed} (${msg})`);
                    return;
                }
                const data = await res.json();
                try {
                    this.applyWorkspace(data);
                } catch (applyErr) {
                    console.error('[switchTo] applyWorkspace error:', applyErr);
                    window.showToast?.('error', this.i18n.load_workspace_failed);
                    return;
                }
                history.pushState({ conversationId: id }, '', `${this._basePath}/${id}`);
            } catch (e) {
                console.error('[switchTo] network error:', e);
                window.showToast?.('error', this.i18n.network_error);
            } finally {
                this._switching = false;
            }
        },

        onPopState(e) {
            const id = e.state?.conversationId;
            if (id) this.switchTo(id);
        },

        applyWorkspace(data) {
            const conv = data.conversation || {};
            this.conversationId   = conv.id;
            this.tenantId         = conv.tenant_id;
            this.state            = conv.state;
            this.agentId          = data.owner_agent?.id ?? null;
            this.agentName        = data.owner_agent?.name ?? null;
            this.aiSuspended      = !!conv.ai_suspended;
            this.aiMode           = data.ai_mode || 'off';

            this.customerId         = data.customer?.id ?? null;
            this.customerName       = data.customer?.display_name || '';
            this.customerPhone      = data.customer?.phone_e164 || '';
            this.customerProfilePic = data.customer?.profile_pic_url || null;
            this.customerInitials   = (this.customerName || '??').substring(0, 2).toUpperCase();

            this.tenantName    = data.tenant?.name ?? null;
            this.instanceName  = data.instance?.name ?? null;
            this.teamName      = data.team?.name ?? null;
            this.teamAgents    = data.team_agents || [];
            this.events        = data.events || [];

            this.conversationCreatedAt = conv.created_at || null;
            this.lastMessageAt         = conv.last_message_at || null;
            this.customerConvosCount   = data.customer_conversation_count || 0;

            this.messages = (data.messages?.data || []).map(m => ({ ...m, sort_ts: m.sent_at || m.created_at || null }));
            this.cursor          = data.messages?.next_cursor ?? null;
            this.hasMoreMessages = this.cursor !== null;

            this.draft = '';
            this.isNote = false;
            this.clearMedia();
            this.customerTyping = false;
            this.peerTypingName = '';
            this.numberStatus = null;
            this.sideTab = 'details';
            this.showRepliesPicker = false;

            this.$nextTick(() => this.scrollToBottom());
            this.subscribeChannel();
            this.markRead();
            this.checkCustomerNumber();

            // Update active row badge in left rail
            const row = this.list.find(c => c.id === this.conversationId);
            if (row) row.unread_count = 0;
        },

        handleGlobalKey(e) {
            if (e.key === '/' && document.activeElement?.tagName !== 'TEXTAREA' && document.activeElement?.tagName !== 'INPUT') {
                e.preventDefault();
                this.openRepliesPicker();
            }
            if (e.key === 'Escape' && this.showRepliesPicker) {
                this.showRepliesPicker = false;
            }
        },

        startListPolling() {
            if (this._listTimer) clearInterval(this._listTimer);
            this._listTimer = setInterval(() => this._refreshList(), 5000);
        },

        startRosterPolling() {
            if (this._rosterTimer) clearInterval(this._rosterTimer);
            this._rosterTimer = setInterval(() => this._refreshRoster(), 20000);
        },

        startHeartbeat() {
            this.sendHeartbeat();
            if (this._heartbeatTimer) clearInterval(this._heartbeatTimer);
            this._heartbeatTimer = setInterval(() => this.sendHeartbeat(), 30000);
        },

        sendHeartbeat() {
            fetch('/api/agents/heartbeat', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? ''
                }
            }).catch(() => {});
        },

        async _refreshRoster() {
            try {
                const res = await fetch('/api/agents/online', {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) return;
                const data = await res.json();
                this.onlineAgents = data.data || [];
            } catch {}
        },

        async refreshSavedReplies() {
            try {
                const res = await fetch('/api/saved-replies', {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) return;
                const data = await res.json();
                this.savedReplies = data.data || [];
            } catch {}
        },

        applySavedReply(reply) {
            if (!reply) return;
            const ta = document.querySelector('.cw-textarea');
            const insert = reply.body || '';
            if (ta && this.draft) {
                this.draft = `${this.draft.replace(/\/\S*$/, '')}${insert}`;
            } else {
                this.draft = insert;
            }
            this.$nextTick(() => {
                const el = document.querySelector('.cw-textarea');
                if (el) { this.autoResize(el); el.focus(); }
            });
        },

        openRepliesPicker() {
            this.showRepliesPicker = true;
            this.replySearch = '';
            this.replyHighlight = 0;
            this.$nextTick(() => this.$refs.replySearchInput?.focus());
        },

        onListQueryInput() {
            clearTimeout(this._listSearchTimer);
            this._listSearchTimer = setTimeout(() => this._refreshList(true), 250);
        },

        onListTabChange(tab) {
            this.listTab = tab;
            this._refreshList(true);
        },

        async _refreshList(server = false) {
            try {
                const params = new URLSearchParams({
                    tab: this.listTab === 'all' ? 'all' : (this.listTab === 'mine' ? 'mine' : (this.listTab === 'pool' ? 'pool' : 'closed')),
                    per_page: 80,
                });
                if (this.listQuery.trim()) params.set('search', this.listQuery.trim());
                const res = await fetch(`{{ route($panelPrefix.'.conversations.index') }}?${params}`, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) return;
                const data = await res.json();
                const items = (data.data || []).map(c => ({
                    id: c.id,
                    customer_name: c.customer?.display_name || c.customer?.phone_e164 || '—',
                    phone: c.customer?.phone_e164,
                    profile_pic_url: c.customer?.profile_pic_url,
                    last_message_at: c.last_message_at,
                    last_message_preview: c.last_message_preview,
                    unread_count: c.unread_count || 0,
                    state: c.state,
                    owner_agent_id: c.owner_agent_id,
                    team_id: c.team_id,
                    instance_name: c.instance?.name,
                    ai_suspended: !!c.ai_suspended,
                }));
                this.list = items;
            } catch {}
        },

        initialsOf(name) {
            if (!name) return '??';
            const parts = name.trim().split(/\s+/);
            return ((parts[0]?.[0] || '?') + (parts[1]?.[0] || '')).toUpperCase();
        },

        stateLabel(state) {
            return {
                pool:    this.i18n.state_pool,
                claimed: this.i18n.state_claimed,
                closed:  this.i18n.state_closed,
            }[state] || state;
        },

        timeAgoShort(ts) {
            if (!ts) return '';
            const s = (Date.now() - new Date(ts)) / 1000;
            if (s < 60) return 'now';
            if (s < 3600) return Math.floor(s / 60) + 'm';
            if (s < 86400) return Math.floor(s / 3600) + 'h';
            if (s < 604800) return Math.floor(s / 86400) + 'd';
            return new Date(ts).toLocaleDateString([], { month: 'short', day: 'numeric' });
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
            // Also zero out unread badge in left rail
            const row = this.list.find(c => c.id === this.conversationId);
            if (row) row.unread_count = 0;
        },

        setupWsTracking() {
            this.wsConnected = window._echoConnected === true;
            const self = this;
            window._echoStateListeners = window._echoStateListeners || [];
            window._echoStateListeners.push(function(connected) {
                self.wsConnected = connected;
            });
            this.startPolling();
        },

        async _refreshMessages() {
            try {
                const res = await fetch(`/api/conversations/${this.conversationId}/messages?per_page=20`, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) return;
                const data = await res.json();
                const incoming = (data.data || []).map(m => this.normalizeMessage(m));
                const maxId = this.messages.reduce((mx, m) => Math.max(mx, m.id || 0), 0);

                // Patch status on existing messages that progressed (pending→sent→delivered→read)
                const statusMap = new Map(incoming.map(m => [m.id, m.status]));
                let patched = false;
                const patchedMessages = this.messages.map(m => {
                    const incoming_status = statusMap.get(m.id);
                    if (incoming_status && incoming_status !== m.status) {
                        patched = true;
                        return { ...m, status: incoming_status };
                    }
                    return m;
                });
                if (patched) this.messages = patchedMessages;

                // Append genuinely new messages
                const fresh = incoming.filter(m => (m.id || 0) > maxId);
                if (fresh.length > 0) {
                    this.messages = this.sortMessages([...(patched ? patchedMessages : this.messages), ...fresh]);
                    this.$nextTick(() => this.scrollToBottom());
                    if (fresh.some(m => m.direction === 'in')) this.markRead();
                }
            } catch(e) {}
        },

        startPolling() {
            this.stopPolling();
            this._pollTimer = setInterval(() => this._refreshMessages(), 3000);
        },

        stopPolling() {
            if (this._pollTimer) { clearInterval(this._pollTimer); this._pollTimer = null; }
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
            if (file) await this.uploadFile(file);
            if (event.target) event.target.value = '';
        },

        async uploadFile(file) {
            if (!file) return;
            const MAX = 25 * 1024 * 1024;
            if (file.size > MAX) {
                window.showToast?.('error', this.i18n.file_too_large);
                return;
            }
            this.mediaUploading  = true;
            this.uploadProgress  = 0;
            this.mediaAttachment = {
                type: this.guessType(file),
                file_name: file.name,
                size_label: this.formatBytes(file.size),
                url: null,
            };
            const formData = new FormData();
            formData.append('file', file);
            try {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '/api/media/upload');
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name=csrf-token]').content);
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) this.uploadProgress = Math.round((e.loaded / e.total) * 100);
                });
                const result = await new Promise((resolve, reject) => {
                    xhr.onload = () => {
                        if (xhr.status >= 200 && xhr.status < 300) resolve(JSON.parse(xhr.responseText));
                        else reject(new Error(xhr.responseText));
                    };
                    xhr.onerror = () => reject(new Error('Upload failed'));
                    xhr.send(formData);
                });
                this.mediaAttachment = {
                    ...result,
                    size_label: this.formatBytes(file.size),
                };
                this.uploadProgress = 100;
            } catch (e) {
                window.showToast?.('error', this.i18n.upload_failed);
                this.clearMedia();
            } finally {
                this.mediaUploading = false;
            }
        },

        guessType(file) {
            const mime = (file.type || '').toLowerCase();
            if (mime.startsWith('image/')) return 'image';
            if (mime.startsWith('video/')) return 'video';
            if (mime.startsWith('audio/')) return 'audio';
            return 'document';
        },

        mediaIconFor(type) {
            return {
                image:    'ri-image-line',
                video:    'ri-video-line',
                audio:    'ri-music-2-line',
                document: 'ri-file-text-line',
            }[type] || 'ri-file-line';
        },

        formatBytes(bytes) {
            if (!bytes && bytes !== 0) return '';
            if (bytes < 1024) return `${bytes} B`;
            if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
            if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
            return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`;
        },

        onDragEnter(e) {
            if (this.state !== 'claimed' || this.isNote) return;
            this._dragCounter++;
            this.isDragging = true;
        },
        onDragOver(e) {
            if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
        },
        onDragLeave(e) {
            this._dragCounter = Math.max(0, this._dragCounter - 1);
            if (this._dragCounter === 0) this.isDragging = false;
        },
        async onDrop(e) {
            this._dragCounter = 0;
            this.isDragging = false;
            if (this.state !== 'claimed' || this.isNote) return;
            const file = e.dataTransfer?.files?.[0];
            if (file) await this.uploadFile(file);
        },

        formatStarted(ts) {
            if (!ts) return '—';
            const d = new Date(ts);
            try {
                return d.toLocaleString([], { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
            } catch { return d.toISOString(); }
        },

        setQuickReply(text) {
            this.draft = text;
            this.$nextTick(() => {
                const ta = document.querySelector('.cw-textarea');
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
                window.showToast?.('error', this.i18n.load_messages_failed);
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
            const body  = this.draft.trim();
            const media = this.mediaAttachment;
            this.draft  = '';
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
                    window.showToast?.('error', err.message || this.i18n.send_failed);
                    this.draft = body;
                } else {
                    const msgData = await res.json();
                    if (msgData && msgData.id) {
                        const normalized = this.normalizeMessage(msgData);
                        if (!this.messages.some(m => m.id === normalized.id)) {
                            this.messages = this.sortMessages([...this.messages, normalized]);
                        }
                    }
                    this.$nextTick(() => this.scrollToBottom());
                    this.sendPresence('available');
                }
            } catch {
                this.draft = body;
                window.showToast?.('error', this.i18n.network_error);
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
                    this.agentId = this._meId;
                    this.agentName = @json(auth()->user()->name);
                    this.aiSuspended = true;
                    window.showToast?.('success', this.i18n.claim_success);
                } else {
                    window.showToast?.('error', data.message || this.i18n.claim_error);
                }
            } finally {
                this.actionLoading = false;
            }
        },

        release() {
            confirmSend({
                title: this.i18n.release_title,
                message: this.i18n.release_desc,
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
                    this.state = 'pool';
                    this.agentId = null;
                    this.agentName = null;
                    this.aiSuspended = false;
                    this.actionLoading = false;
                }
            });
        },

        closeConv() {
            confirmSend({
                title: this.i18n.close_title,
                message: this.i18n.close_desc,
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
                    window.showToast?.('success', this.i18n.close_success);
                }
            });
        },

        reopen() {
            confirmSend({
                title: this.i18n.reopen_title,
                message: this.i18n.reopen_desc,
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
                    window.showToast?.('success', this.i18n.reopen_success);
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
                    window.showToast?.('success', data.message || this.i18n.ai_updated);
                } else {
                    window.showToast?.('error', data.message || this.i18n.ai_update_failed);
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
                    window.showToast?.('error', data.message || this.i18n.reassign_failed);
                    return;
                }
                this.agentId = parseInt(this.reassignAgentId, 10);
                const picked = this.teamAgents.find(a => a.id === this.agentId);
                if (picked) this.agentName = picked.name;
                this.showReassign = false;
                this.reassignAgentId = '';
                window.showToast?.('success', this.i18n.reassign_success);
            } finally {
                this.actionLoading = false;
            }
        },

        subscribeChannel() {
            if (!window.Echo) return;
            if (this._echoChannel) {
                try { window.Echo.leave(this._echoChannel); } catch (e) {}
            }
            this._echoChannel = `tenant.${this.tenantId}.conversation.${this.conversationId}`;
            window.Echo.private(this._echoChannel)
                .listen('.message.received', () => { this._refreshMessages(); this.markRead(); this.setCustomerTyping(false); })
                .listen('.message.sent', () => { this._refreshMessages(); })
                .listen('.agent.typing', (e) => {
                    const presence = (e?.presence || '').toLowerCase();
                    const name     = e?.agent?.name || 'A colleague';
                    if (presence === 'composing' || presence === 'recording') {
                        this.peerTypingName = name;
                        this.setCustomerTyping(true);
                    } else {
                        this.setCustomerTyping(false);
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
                .listen('.conversation.closed', () => { this.state = 'closed'; })
                .listen('.conversation.reopened', () => { this.state = 'pool'; this.aiSuspended = false; });
        },

        setCustomerTyping(isTyping) {
            this.customerTyping = !!isTyping;
            clearTimeout(this._customerTypingTimer);
            if (isTyping) {
                this._customerTypingTimer = setTimeout(() => { this.customerTyping = false; }, 6000);
            }
        },

        bubbleClass(msg) {
            if (msg.ai_metadata?.is_note) return 'cw-bubble-note';
            return msg.direction === 'out' ? 'cw-bubble-out' : 'cw-bubble-in';
        },

        tickClass(msg) {
            const s = (msg.status || '').toLowerCase();
            if (s === 'read')      return 'cw-ticks-read';
            if (s === 'delivered') return 'cw-ticks-delivered';
            if (s === 'failed')    return 'cw-ticks-failed';
            if (s === 'pending')   return 'cw-ticks-pending';
            return 'cw-ticks-sent';
        },

        ticksHtml(msg) {
            const s = (msg.status || '').toLowerCase();
            if (s === 'failed')
                return '<span class="cw-tick-wrap"><i class="ri-close-circle-line"></i></span>';
            if (s === 'pending')
                return '<span class="cw-tick-wrap cw-tick-clock"><i class="ri-time-line"></i></span>';
            if (s === 'sent')
                return '<span class="cw-tick-wrap"><i class="ri-check-line"></i></span>';
            // delivered or read — blue double-check
            return '<span class="cw-tick-wrap cw-tick-blue"><i class="ri-check-double-line"></i></span>';
        },

        normalizeMessage(msg) {
            return { ...msg, sort_ts: msg.sent_at || msg.created_at || null };
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
            if (date.toDateString() === today.toDateString()) return 'Today';
            const yesterday = new Date(); yesterday.setDate(today.getDate() - 1);
            if (date.toDateString() === yesterday.toDateString()) return 'Yesterday';
            return date.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
        },

        timeAgo(ts) {
            if (!ts) return '—';
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(function () {
        document.getElementById('scroll-anchor')?.scrollIntoView({ behavior: 'instant' });
    }, 600);
});
</script>
@endpush
