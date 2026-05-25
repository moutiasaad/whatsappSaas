<div id="messages-scroll" class="conversation-stage">
    @if($hasMore)
        <div class="conversation-load-more">
            <button wire:click="loadMore" class="btn btn-ghost btn-sm">
                {{ __('ui.conversation_show_page.load_earlier') }}
            </button>
        </div>
    @endif

    @php $prevDate = null; @endphp
    @foreach($messages as $msg)
        @php
            $ts    = $msg->sent_at ?? $msg->created_at;
            $date  = $ts?->toDateString();
            $isOut = $msg->direction === 'out';
            $isNote = data_get($msg->ai_metadata, 'is_note');
            $bubbleStyle = $isNote
                ? 'background:#fffbeb;border:1px dashed #f59e0b;color:#92400e;'
                : ($isOut
                    ? 'background:var(--brand);color:#fff;border-bottom-right-radius:4px;'
                    : 'background:#fff;border:1px solid var(--card-border);border-bottom-left-radius:4px;color:var(--text-primary);');
        @endphp

        @if($date !== $prevDate)
            <div class="conversation-date-separator">
                <span>{{ $ts?->translatedFormat('d M Y') ?? '-' }}</span>
            </div>
            @php $prevDate = $date; @endphp
        @endif

        <div class="message-row {{ $isOut ? 'message-row-out' : 'message-row-in' }}">
            <div class="message-avatar {{ $isOut ? 'message-avatar-out' : 'message-avatar-in' }}">
                <span>{{ $isOut ? 'ME' : $customerInitials }}</span>
            </div>
            <div class="message-stack {{ $isOut ? 'message-stack-out' : '' }}">
                @if($isNote)
                    <div class="message-chip">Internal note</div>
                @endif
                @if($msg->author_type === 'ai')
                    <div class="message-chip message-chip-ai">AI reply</div>
                @endif
                <div class="message-bubble" style="{{ $bubbleStyle }}">
                    @if($msg->media_url && $msg->type === 'image')
                        <img src="{{ $msg->media_url }}" class="msg-media-image">
                    @elseif($msg->media_url && $msg->type === 'video')
                        <video src="{{ $msg->media_url }}" controls class="msg-media-video" preload="metadata"></video>
                    @elseif($msg->media_url && $msg->type === 'audio')
                        <audio src="{{ $msg->media_url }}" controls class="msg-media-audio" preload="metadata"></audio>
                    @elseif($msg->media_url && $msg->type === 'document')
                        <a href="{{ $msg->media_url }}" target="_blank" class="msg-media-doc">
                            <i class="ri-file-download-line"></i>
                            <span>{{ data_get($msg->ai_metadata, 'file_name') ?: 'Document' }}</span>
                        </a>
                    @endif
                    @if($msg->body)
                        <div class="message-body">{{ $msg->body }}</div>
                    @endif
                    <div class="message-time">{{ $ts?->format('H:i') ?? '' }}</div>
                </div>
            </div>
        </div>
    @endforeach

    <div id="scroll-anchor"></div>
</div>
