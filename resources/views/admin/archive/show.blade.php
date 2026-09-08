@extends('layouts.admin')

@section('title', ($meta['title'] ?: __('ui.archive_page.no_title')) . ' — ' . __('ui.archive_page.ticket'))

@section('breadcrumb')
    <a href="{{ route(auth()->user()->routeNamePrefix() . '.archive.index', ['tab' => $channel]) }}">
        {{ __('ui.archive_page.breadcrumb') }}
    </a>
    <span class="crumb-sep">/</span>
    <span>{{ $meta['display_id'] }}</span>
@endsection

@section('content')
@php
    $panelPrefix = auth()->user()->routeNamePrefix();
    $isWebchat   = $channel === 'webchat';
    $duration    = null;
    if ($meta['created_at'] && $meta['closed_at']) {
        $duration = $meta['created_at']->diffForHumans($meta['closed_at'], ['parts' => 2, 'short' => true, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]);
    }
@endphp

<div class="tkt-root">

    {{-- Header --}}
    <div class="tkt-header">
        <div class="tkt-header-left">
            <a href="{{ route($panelPrefix . '.archive.index', ['tab' => $channel]) }}" class="tkt-back">
                <i class="ri-arrow-left-line"></i>
            </a>
            <div class="tkt-header-copy">
                <div class="tkt-header-line">
                    <span class="tkt-chip {{ $isWebchat ? 'tkt-chip-blue' : 'tkt-chip-green' }}">
                        <i class="ri-{{ $isWebchat ? 'chat-3' : 'whatsapp' }}-line"></i>
                        {{ $isWebchat ? __('ui.archive_page.tab_webchat') : __('ui.archive_page.tab_whatsapp') }}
                    </span>
                    <span class="tkt-chip tkt-chip-slate">
                        <i class="ri-lock-line"></i>
                        {{ __('ui.archive_page.status_closed') }}
                    </span>
                    <span class="tkt-id">{{ $meta['display_id'] }}</span>
                </div>
                <h1 class="tkt-title">
                    {{ $meta['title'] ?: __('ui.archive_page.no_title') }}
                </h1>
                <div class="tkt-header-meta">
                    <span><i class="ri-user-3-line"></i> {{ $meta['contact_name'] }}</span>
                    @if ($meta['contact_sub'])
                        <span class="tkt-dot"></span>
                        <span>{{ $meta['contact_sub'] }}</span>
                    @endif
                    @if ($meta['closed_by'])
                        <span class="tkt-dot"></span>
                        <span>{{ __('ui.archive_page.col_closed_by') }}: <strong>{{ $meta['closed_by'] }}</strong></span>
                    @endif
                    @if ($meta['closed_at'])
                        <span class="tkt-dot"></span>
                        <span title="{{ $meta['closed_at']->format('Y-m-d H:i') }}">{{ $meta['closed_at']->diffForHumans() }}</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="tkt-header-right">
            @if (! $isWebchat)
                <a href="{{ route($panelPrefix . '.conversations.show', $conversation) }}" class="btn btn-outline btn-sm">
                    <i class="ri-external-link-line"></i>
                    {{ __('ui.archive_page.open_in_inbox') }}
                </a>
            @endif
        </div>
    </div>

    {{-- Two-column body --}}
    <div class="tkt-grid">

        {{-- Transcript --}}
        <section class="tkt-transcript-card">
            <header class="tkt-card-head">
                <span><i class="ri-chat-quote-line"></i> {{ __('ui.archive_page.transcript') }}</span>
                <span class="tkt-card-count">{{ number_format($stats['messages_total']) }}</span>
            </header>

            @if ($messages->isEmpty())
                <div class="tkt-empty">
                    <i class="ri-chat-off-line"></i>
                    <p>{{ __('ui.archive_page.no_messages') }}</p>
                </div>
            @else
                <div class="tkt-thread">
                    @php $lastDate = null; @endphp
                    @foreach ($messages as $m)
                        @php
                            if ($isWebchat) {
                                $side  = $m->sender_type === \App\Models\WebChat\Message::SENDER_VISITOR ? 'in' : 'out';
                                $who   = match ($m->sender_type) {
                                    \App\Models\WebChat\Message::SENDER_VISITOR => $meta['contact_name'],
                                    \App\Models\WebChat\Message::SENDER_AGENT   => $m->sender?->name ?? __('ui.archive_page.agent'),
                                    \App\Models\WebChat\Message::SENDER_BOT     => __('ui.archive_page.ai'),
                                    default                                     => __('ui.archive_page.system'),
                                };
                                $isSys = $m->sender_type === \App\Models\WebChat\Message::SENDER_SYSTEM;
                                $ts    = $m->created_at;
                                $body  = $m->body;
                            } else {
                                $side  = $m->direction === 'in' ? 'in' : 'out';
                                $who   = match (true) {
                                    $m->direction === 'in'         => $meta['contact_name'],
                                    $m->author_type === 'ai'       => __('ui.archive_page.ai'),
                                    $m->author?->name              => $m->author->name,
                                    default                        => __('ui.archive_page.agent'),
                                };
                                $isSys = false;
                                $ts    = $m->sent_at ?: $m->created_at;
                                $body  = $m->body;
                            }
                            $dayKey = optional($ts)->format('Y-m-d');
                        @endphp

                        @if ($dayKey && $dayKey !== $lastDate)
                            <div class="tkt-day">
                                <span>{{ optional($ts)->translatedFormat('l, j F Y') }}</span>
                            </div>
                            @php $lastDate = $dayKey; @endphp
                        @endif

                        @if ($isSys)
                            <div class="tkt-sys">
                                <span>{{ $body }}</span>
                            </div>
                        @else
                            <div class="tkt-msg tkt-msg-{{ $side }}">
                                <div class="tkt-msg-avatar tkt-msg-avatar-{{ $side }}">
                                    {{ mb_strtoupper(mb_substr($who, 0, 1)) }}
                                </div>
                                <div class="tkt-msg-body">
                                    <div class="tkt-msg-head">
                                        <span class="tkt-msg-who">{{ $who }}</span>
                                        <span class="tkt-msg-time">{{ optional($ts)->format('H:i') }}</span>
                                    </div>
                                    <div class="tkt-msg-bubble tkt-msg-bubble-{{ $side }}">{{ $body }}</div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Sidebar --}}
        <aside class="tkt-side">

            {{-- Contact card --}}
            <div class="tkt-card">
                <header class="tkt-card-head">
                    <span><i class="ri-user-heart-line"></i> {{ __('ui.archive_page.contact') }}</span>
                </header>
                <div class="tkt-contact">
                    <div class="tkt-contact-avatar">
                        {{ mb_strtoupper(mb_substr($meta['contact_name'], 0, 2)) }}
                    </div>
                    <div class="tkt-contact-body">
                        <div class="tkt-contact-name">{{ $meta['contact_name'] }}</div>
                        @if ($meta['contact_sub'])
                            <div class="tkt-contact-sub">{{ $meta['contact_sub'] }}</div>
                        @endif
                    </div>
                </div>
                @if (! $isWebchat && $conversation->customer_id)
                    <a href="{{ route($panelPrefix . '.customers.show', $conversation->customer_id) }}" class="tkt-side-link">
                        {{ __('ui.archive_page.view_customer_profile') }} <i class="ri-arrow-right-line"></i>
                    </a>
                @endif
            </div>

            {{-- Meta --}}
            <div class="tkt-card">
                <header class="tkt-card-head">
                    <span><i class="ri-information-line"></i> {{ __('ui.archive_page.details') }}</span>
                </header>
                <dl class="tkt-dl">
                    <dt>{{ __('ui.archive_page.channel') }}</dt>
                    <dd>{{ $isWebchat ? __('ui.archive_page.tab_webchat') : __('ui.archive_page.tab_whatsapp') }}</dd>

                    @if ($meta['source_label'])
                        <dt>{{ $isWebchat ? __('ui.archive_page.col_widget') : __('ui.archive_page.col_instance') }}</dt>
                        <dd>{{ $meta['source_label'] }}</dd>
                    @endif

                    @if ($meta['team_name'])
                        <dt>{{ __('ui.archive_page.team') }}</dt>
                        <dd>{{ $meta['team_name'] }}</dd>
                    @endif

                    @if ($meta['source_url'])
                        <dt>{{ __('ui.archive_page.page') }}</dt>
                        <dd><a href="{{ $meta['source_url'] }}" target="_blank" rel="noopener" class="tkt-side-link tkt-side-link-inline">{{ \Illuminate\Support\Str::limit($meta['source_url'], 42) }}</a></dd>
                    @endif

                    <dt>{{ __('ui.archive_page.opened') }}</dt>
                    <dd title="{{ $meta['created_at']?->format('Y-m-d H:i') }}">{{ $meta['created_at']?->diffForHumans() ?? '—' }}</dd>

                    @if ($meta['claimed_at'])
                        <dt>{{ __('ui.archive_page.claimed') }}</dt>
                        <dd title="{{ $meta['claimed_at']->format('Y-m-d H:i') }}">
                            {{ $meta['claimed_at']->diffForHumans() }}
                            @if ($meta['claimed_by']) · {{ $meta['claimed_by'] }} @endif
                        </dd>
                    @endif

                    <dt>{{ __('ui.archive_page.closed') }}</dt>
                    <dd title="{{ $meta['closed_at']?->format('Y-m-d H:i') }}">
                        {{ $meta['closed_at']?->diffForHumans() ?? '—' }}
                        @if ($meta['closed_by']) · {{ $meta['closed_by'] }} @endif
                    </dd>

                    @if ($duration)
                        <dt>{{ __('ui.archive_page.duration') }}</dt>
                        <dd>{{ $duration }}</dd>
                    @endif
                </dl>
            </div>

            {{-- Message breakdown --}}
            <div class="tkt-card">
                <header class="tkt-card-head">
                    <span><i class="ri-bar-chart-2-line"></i> {{ __('ui.archive_page.messages_breakdown') }}</span>
                </header>
                <div class="tkt-metrics">
                    <div class="tkt-metric">
                        <span class="tkt-metric-value">{{ $stats['messages_total'] }}</span>
                        <span class="tkt-metric-label">{{ __('ui.archive_page.total') }}</span>
                    </div>
                    <div class="tkt-metric">
                        <span class="tkt-metric-value">{{ $isWebchat ? $stats['messages_visitor'] : $stats['messages_customer'] }}</span>
                        <span class="tkt-metric-label">{{ $isWebchat ? __('ui.archive_page.visitor') : __('ui.archive_page.customer') }}</span>
                    </div>
                    <div class="tkt-metric">
                        <span class="tkt-metric-value">{{ $stats['messages_agent'] }}</span>
                        <span class="tkt-metric-label">{{ __('ui.archive_page.agent') }}</span>
                    </div>
                    <div class="tkt-metric">
                        <span class="tkt-metric-value">{{ $stats['messages_ai'] }}</span>
                        <span class="tkt-metric-label">{{ __('ui.archive_page.ai') }}</span>
                    </div>
                </div>
            </div>

            {{-- Timeline (WhatsApp only, from events) --}}
            @if (! $isWebchat && $events->isNotEmpty())
                <div class="tkt-card">
                    <header class="tkt-card-head">
                        <span><i class="ri-history-line"></i> {{ __('ui.archive_page.timeline') }}</span>
                    </header>
                    <ul class="tkt-timeline">
                        @foreach ($events as $ev)
                            <li>
                                <span class="tkt-timeline-dot"></span>
                                <div>
                                    <div class="tkt-timeline-title">
                                        {{ __('ui.archive_page.event_' . $ev->type) ?? $ev->type }}
                                        @if ($ev->actor?->name)
                                            · {{ $ev->actor->name }}
                                        @endif
                                    </div>
                                    <div class="tkt-timeline-time" title="{{ $ev->created_at?->format('Y-m-d H:i') }}">
                                        {{ $ev->created_at?->diffForHumans() }}
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Related tickets --}}
            @if ($related->isNotEmpty())
                <div class="tkt-card">
                    <header class="tkt-card-head">
                        <span><i class="ri-links-line"></i> {{ __('ui.archive_page.related_tickets') }}</span>
                        <span class="tkt-card-count">{{ $related->count() }}</span>
                    </header>
                    <ul class="tkt-related">
                        @foreach ($related as $r)
                            @php
                                $rClosed = $isWebchat
                                    ? ($r->status === \App\Models\WebChat\Conversation::STATUS_CLOSED)
                                    : ($r->state === 'closed');
                                $href = $isWebchat
                                    ? ($rClosed ? route($panelPrefix . '.archive.webchat.show', $r->uuid) : '#')
                                    : ($rClosed ? route($panelPrefix . '.archive.whatsapp.show', $r->id) : route($panelPrefix . '.conversations.show', $r->id));
                            @endphp
                            <li>
                                <a href="{{ $href }}">
                                    <div class="tkt-related-title">
                                        {{ $r->title ? \Illuminate\Support\Str::limit($r->title, 42) : __('ui.archive_page.no_title') }}
                                    </div>
                                    <div class="tkt-related-meta">
                                        <span>#{{ $r->id }}</span>
                                        <span class="tkt-dot"></span>
                                        <span class="{{ $rClosed ? 'tkt-badge-slate' : 'tkt-badge-blue' }}">
                                            {{ $rClosed ? __('ui.archive_page.status_closed') : __('ui.archive_page.status_open') }}
                                        </span>
                                        @if ($r->closed_at)
                                            <span class="tkt-dot"></span>
                                            <span title="{{ $r->closed_at->format('Y-m-d H:i') }}">{{ $r->closed_at->diffForHumans() }}</span>
                                        @endif
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>
</div>

@push('styles')
<style>
    main.page-content { max-width: 1280px; }

    /* Header */
    .tkt-root { display: block; }
    .tkt-header {
        display: flex; align-items: flex-start; justify-content: space-between;
        gap: 1rem; padding: 1.25rem 1.5rem;
        background: var(--card-bg); border: 1px solid var(--card-border);
        border-radius: var(--radius-lg); margin-bottom: 1rem;
    }
    .tkt-header-left { display: flex; gap: .875rem; min-width: 0; flex: 1; }
    .tkt-back {
        width: 36px; height: 36px; border-radius: 10px;
        background: var(--card-bg); border: 1px solid var(--card-border);
        display: inline-flex; align-items: center; justify-content: center;
        color: var(--text-muted); text-decoration: none; flex-shrink: 0;
        transition: all .15s ease;
    }
    .tkt-back:hover { color: var(--text-primary); border-color: var(--text-muted); }
    .tkt-header-copy { min-width: 0; flex: 1; }
    .tkt-header-line { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; margin-bottom: .25rem; }
    .tkt-id { font-size: .8125rem; color: var(--text-muted); font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    .tkt-title {
        font-size: 1.375rem; font-weight: 700; color: var(--text-primary);
        margin: 0 0 .5rem; line-height: 1.3; word-break: break-word;
    }
    .tkt-header-meta {
        display: flex; gap: .5rem; align-items: center; flex-wrap: wrap;
        font-size: .8125rem; color: var(--text-secondary);
    }
    .tkt-header-meta i { margin-inline-end: .25rem; }
    .tkt-header-right { flex-shrink: 0; }

    .tkt-dot { width: 3px; height: 3px; border-radius: 999px; background: #cbd5e1; }

    .tkt-chip {
        display: inline-flex; align-items: center; gap: .375rem;
        font-size: .75rem; font-weight: 600;
        padding: .25rem .625rem; border-radius: 999px;
    }
    .tkt-chip-green { background: #d1fae5; color: #047857; }
    .tkt-chip-blue  { background: #dbeafe; color: #1d4ed8; }
    .tkt-chip-slate { background: #e2e8f0; color: #475569; }

    /* Grid */
    .tkt-grid { display: grid; grid-template-columns: minmax(0,1fr) 340px; gap: 1rem; align-items: start; }
    @media (max-width: 1024px) { .tkt-grid { grid-template-columns: 1fr; } }

    /* Cards */
    .tkt-card, .tkt-transcript-card {
        background: var(--card-bg); border: 1px solid var(--card-border);
        border-radius: var(--radius-lg); overflow: hidden;
    }
    .tkt-side { display: flex; flex-direction: column; gap: 1rem; }
    .tkt-card-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: .875rem 1rem; border-bottom: 1px solid var(--card-border);
        font-size: .8125rem; font-weight: 600; color: var(--text-primary);
    }
    .tkt-card-head i { margin-inline-end: .375rem; color: var(--text-muted); }
    .tkt-card-count {
        font-size: .75rem; font-weight: 600; color: var(--text-muted);
        background: rgba(100,116,139,.1); padding: .125rem .5rem; border-radius: 999px;
    }

    /* Contact */
    .tkt-contact { display: flex; align-items: center; gap: .75rem; padding: 1rem; }
    .tkt-contact-avatar {
        width: 44px; height: 44px; border-radius: 50%;
        background: linear-gradient(135deg, #0f7e7a, #15b6a8);
        color: #fff; font-weight: 700; font-size: .875rem;
        display: inline-flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .tkt-contact-body { min-width: 0; flex: 1; }
    .tkt-contact-name { font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tkt-contact-sub { font-size: .8125rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tkt-side-link {
        display: block; padding: .625rem 1rem; border-top: 1px solid var(--card-border);
        color: var(--accent, #0a5e5b); text-decoration: none; font-size: .8125rem; font-weight: 500;
    }
    .tkt-side-link:hover { background: rgba(10,94,91,.05); }
    .tkt-side-link-inline { display: inline; padding: 0; border: none; }

    /* Details list */
    .tkt-dl { display: grid; grid-template-columns: 100px 1fr; gap: .5rem .75rem; padding: 1rem; margin: 0; font-size: .8125rem; }
    .tkt-dl dt { color: var(--text-muted); font-weight: 500; }
    .tkt-dl dd { color: var(--text-primary); margin: 0; word-break: break-word; }

    /* Metrics */
    .tkt-metrics { display: grid; grid-template-columns: 1fr 1fr; gap: 1px; background: var(--card-border); }
    .tkt-metric { padding: .875rem; background: var(--card-bg); display: flex; flex-direction: column; align-items: flex-start; }
    .tkt-metric-value { font-size: 1.25rem; font-weight: 700; color: var(--text-primary); line-height: 1; }
    .tkt-metric-label { font-size: .6875rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em; margin-top: .25rem; }

    /* Timeline */
    .tkt-timeline { list-style: none; margin: 0; padding: 1rem; display: flex; flex-direction: column; gap: .75rem; }
    .tkt-timeline li { display: flex; gap: .625rem; align-items: flex-start; }
    .tkt-timeline-dot {
        width: 8px; height: 8px; border-radius: 999px;
        background: linear-gradient(135deg, #0f7e7a, #15b6a8);
        margin-top: 6px; flex-shrink: 0; box-shadow: 0 0 0 3px rgba(15,126,122,.15);
    }
    .tkt-timeline-title { font-size: .8125rem; color: var(--text-primary); font-weight: 500; }
    .tkt-timeline-time { font-size: .75rem; color: var(--text-muted); margin-top: .125rem; }

    /* Related */
    .tkt-related { list-style: none; margin: 0; padding: 0; }
    .tkt-related li { border-bottom: 1px solid var(--card-border); }
    .tkt-related li:last-child { border-bottom: none; }
    .tkt-related a { display: block; padding: .75rem 1rem; color: inherit; text-decoration: none; transition: background .15s ease; }
    .tkt-related a:hover { background: rgba(100,116,139,.04); }
    .tkt-related-title { font-size: .8125rem; font-weight: 600; color: var(--text-primary); margin-bottom: .25rem; }
    .tkt-related-meta { display: flex; gap: .375rem; align-items: center; flex-wrap: wrap; font-size: .75rem; color: var(--text-muted); }
    .tkt-badge-slate { background: #e2e8f0; color: #475569; padding: .0625rem .375rem; border-radius: 4px; font-size: .6875rem; font-weight: 600; }
    .tkt-badge-blue  { background: #dbeafe; color: #1d4ed8; padding: .0625rem .375rem; border-radius: 4px; font-size: .6875rem; font-weight: 600; }

    /* Transcript */
    .tkt-thread { padding: 1.25rem 1.5rem; max-height: 720px; overflow-y: auto; }
    .tkt-day {
        text-align: center; margin: 1rem 0 .75rem;
    }
    .tkt-day span {
        display: inline-block; font-size: .6875rem; font-weight: 600;
        color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em;
        background: rgba(100,116,139,.08); padding: .25rem .625rem; border-radius: 999px;
    }
    .tkt-sys { text-align: center; margin: .75rem 0; }
    .tkt-sys span {
        display: inline-block; font-size: .75rem; color: var(--text-muted);
        background: transparent; padding: .25rem .75rem; border-radius: 999px;
        border: 1px dashed var(--card-border);
    }

    .tkt-msg { display: flex; gap: .625rem; margin-bottom: 1rem; }
    .tkt-msg-in  { justify-content: flex-start; }
    .tkt-msg-out { justify-content: flex-end; flex-direction: row-reverse; }

    .tkt-msg-avatar {
        width: 32px; height: 32px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: .75rem; color: #fff; flex-shrink: 0;
    }
    .tkt-msg-avatar-in  { background: linear-gradient(135deg, #64748b, #475569); }
    .tkt-msg-avatar-out { background: linear-gradient(135deg, #0a5e5b, #0d9488); }

    .tkt-msg-body { min-width: 0; max-width: 72%; }
    .tkt-msg-out .tkt-msg-body { text-align: end; }
    .tkt-msg-head { display: flex; gap: .5rem; align-items: baseline; font-size: .75rem; color: var(--text-muted); margin-bottom: .25rem; }
    .tkt-msg-out .tkt-msg-head { justify-content: flex-end; }
    .tkt-msg-who { font-weight: 600; color: var(--text-primary); }

    .tkt-msg-bubble {
        display: inline-block; padding: .625rem .875rem; border-radius: 14px;
        font-size: .875rem; line-height: 1.55; white-space: pre-wrap; word-break: break-word;
        text-align: start;
    }
    .tkt-msg-bubble-in  { background: #f1f5f9; color: var(--text-primary); border: 1px solid var(--card-border); border-top-inline-start-radius: 4px; }
    .tkt-msg-bubble-out { background: linear-gradient(135deg, #0a5e5b, #0d9488); color: #fff; border-top-inline-end-radius: 4px; }

    /* RTL fine-tune */
    [dir="rtl"] .tkt-msg-bubble-in  { border-top-right-radius: 4px; border-top-left-radius: 14px; }
    [dir="rtl"] .tkt-msg-bubble-out { border-top-left-radius: 4px; border-top-right-radius: 14px; }

    .tkt-empty { padding: 3rem 1rem; text-align: center; color: var(--text-muted); }
    .tkt-empty i { font-size: 2rem; display: block; margin-bottom: .5rem; }

    .crumb-sep { margin: 0 .375rem; color: var(--text-muted); }
</style>
@endpush
@endsection
