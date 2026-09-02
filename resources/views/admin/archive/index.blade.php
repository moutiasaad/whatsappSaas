@extends('layouts.admin')

@section('title', __('ui.archive_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.archive_page.breadcrumb') }}</span>
@endsection

@section('content')
@php
    $panelPrefix  = auth()->user()->routeNamePrefix();
    $isSuperAdmin = auth()->user()->isSuperAdmin();
@endphp

<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">{{ __('ui.archive_page.title') }}</div>
        <div class="page-subtitle">{{ __('ui.archive_page.subtitle') }}</div>
    </div>
</div>

{{-- KPI cards --}}
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-icon"><i class="ri-whatsapp-line"></i></div>
        <div class="stat-card-value">{{ number_format($stats['whatsapp_total']) }}</div>
        <div class="stat-card-label">{{ __('ui.archive_page.whatsapp_closed') }}</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-card-icon"><i class="ri-chat-3-line"></i></div>
        <div class="stat-card-value">{{ number_format($stats['webchat_total']) }}</div>
        <div class="stat-card-label">{{ __('ui.archive_page.webchat_closed') }}</div>
    </div>
</div>

{{-- Tabs --}}
<div class="table-toolbar" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-lg);margin-bottom:1rem;flex-wrap:wrap;">
    <div class="tabs" style="display:flex;gap:.25rem;">
        @php
            $qs = fn ($t) => http_build_query(array_merge(request()->except(['tab','page']), ['tab' => $t]));
        @endphp
        <a href="?{{ $qs('whatsapp') }}"
           class="btn btn-sm {{ $tab === 'whatsapp' ? 'btn-primary' : 'btn-outline' }}">
            <i class="ri-whatsapp-line"></i> {{ __('ui.archive_page.tab_whatsapp') }}
        </a>
        <a href="?{{ $qs('webchat') }}"
           class="btn btn-sm {{ $tab === 'webchat' ? 'btn-primary' : 'btn-outline' }}">
            <i class="ri-chat-3-line"></i> {{ __('ui.archive_page.tab_webchat') }}
        </a>
    </div>

    <form method="GET" action="" style="display:flex;flex:1;gap:.5rem;flex-wrap:wrap;align-items:center;margin-left:.5rem;">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <div class="filter-input-wrap">
            <i class="ri-search-line"></i>
            <input type="text" name="search" value="{{ $filters['search'] }}"
                   placeholder="{{ __('ui.archive_page.search_placeholder') }}"
                   class="filter-input">
        </div>

        @if ($isSuperAdmin)
            <select name="tenant_id" class="toolbar-select" onchange="this.form.submit()">
                <option value="">{{ __('ui.archive_page.all_tenants') }}</option>
                @foreach ($tenants as $t)
                    <option value="{{ $t->id }}" @selected((string) $filters['tenant_id'] === (string) $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
        @endif

        <select name="closed_by" class="toolbar-select" onchange="this.form.submit()">
            <option value="">{{ __('ui.archive_page.all_agents') }}</option>
            @foreach ($agents as $agent)
                <option value="{{ $agent->id }}" @selected((string) $filters['closed_by'] === (string) $agent->id)>{{ $agent->name }}</option>
            @endforeach
        </select>

        <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="toolbar-select" onchange="this.form.submit()">
        <input type="date" name="date_to"   value="{{ $filters['date_to'] }}"   class="toolbar-select" onchange="this.form.submit()">

        <button type="submit" class="btn btn-outline btn-sm">
            <i class="ri-filter-3-line"></i> {{ __('ui.archive_page.apply') }}
        </button>

        @if (array_filter($filters))
            <a href="?tab={{ $tab }}" class="btn btn-outline btn-sm">
                <i class="ri-close-line"></i> {{ __('ui.archive_page.clear') }}
            </a>
        @endif
    </form>
</div>

{{-- Table --}}
<div class="card" style="padding:0;overflow:hidden;">
    @if ($items->isEmpty())
        <div class="empty-state" style="padding:3rem 1rem;text-align:center;color:var(--text-muted);">
            <div class="empty-state-icon"><i class="ri-inbox-archive-line" style="font-size:2.5rem;"></i></div>
            <h4 style="margin:.75rem 0 .25rem;">{{ __('ui.archive_page.no_results') }}</h4>
            <p>{{ __('ui.archive_page.no_results_hint') }}</p>
        </div>
    @else
        <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('ui.archive_page.col_title') }}</th>
                        <th>{{ __('ui.archive_page.col_customer') }}</th>
                        @if ($tab === 'whatsapp')
                            <th>{{ __('ui.archive_page.col_instance') }}</th>
                        @else
                            <th>{{ __('ui.archive_page.col_widget') }}</th>
                        @endif
                        @if ($isSuperAdmin)
                            <th>{{ __('ui.archive_page.col_tenant') }}</th>
                        @endif
                        <th>{{ __('ui.archive_page.col_closed_by') }}</th>
                        <th>{{ __('ui.archive_page.col_closed_at') }}</th>
                        <th style="width:48px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td>
                                <span style="font-weight:600;color:var(--text-primary);"
                                      title="{{ $item->title }}">
                                    {{ $item->title ? \Illuminate\Support\Str::limit($item->title, 60) : __('ui.archive_page.no_title') }}
                                </span>
                            </td>
                            <td>
                                @if ($tab === 'whatsapp')
                                    <span style="font-size:.8125rem;color:var(--text-secondary);">
                                        {{ $item->customer?->display_name ?: $item->customer?->phone_e164 ?: '—' }}
                                    </span>
                                @else
                                    <span style="font-size:.8125rem;color:var(--text-secondary);">
                                        {{ $item->visitor?->name ?: $item->visitor_name ?: __('ui.archive_page.anonymous') }}
                                    </span>
                                @endif
                            </td>
                            @if ($tab === 'whatsapp')
                                <td>
                                    <span style="font-size:.8125rem;color:var(--text-muted);">
                                        {{ $item->instance?->name ?? '—' }}
                                    </span>
                                </td>
                            @else
                                <td>
                                    <span style="font-size:.8125rem;color:var(--text-muted);">
                                        {{ $item->widget?->name ?? '—' }}
                                    </span>
                                </td>
                            @endif
                            @if ($isSuperAdmin)
                                <td>
                                    <span style="font-size:.8125rem;color:var(--text-muted);">
                                        {{ $item->tenant?->name ?? '—' }}
                                    </span>
                                </td>
                            @endif
                            <td>
                                @if ($tab === 'whatsapp')
                                    <span style="font-size:.8125rem;">{{ $item->ownerAgent?->name ?? '—' }}</span>
                                @else
                                    <span style="font-size:.8125rem;">{{ $item->closer?->name ?? '—' }}</span>
                                @endif
                            </td>
                            <td>
                                <span style="font-size:.8125rem;color:var(--text-muted);"
                                      title="{{ $item->closed_at?->format('Y-m-d H:i') }}">
                                    {{ $item->closed_at?->diffForHumans() ?? '—' }}
                                </span>
                            </td>
                            <td>
                                @if ($tab === 'whatsapp')
                                    <a href="{{ route($panelPrefix . '.archive.whatsapp.show', $item) }}"
                                       class="action-btn"
                                       title="{{ __('ui.archive_page.open') }}">
                                        <i class="ri-arrow-right-up-line"></i>
                                    </a>
                                @else
                                    <a href="{{ route($panelPrefix . '.archive.webchat.show', $item->uuid) }}"
                                       class="action-btn"
                                       title="{{ __('ui.archive_page.open') }}">
                                        <i class="ri-arrow-right-up-line"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($items->hasPages())
            <div style="padding:1rem 1.25rem;border-top:1px solid var(--card-border);">
                {{ $items->links('admin.partials.pagination') }}
            </div>
        @endif
    @endif
</div>
@endsection
