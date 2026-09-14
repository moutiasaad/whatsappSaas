@extends('layouts.admin')

@section('title', __('ui.plan_modules.' . $module))

@section('breadcrumb')
    <span>{{ __('ui.plan_modules.' . $module) }}</span>
@endsection

@section('content')
@php
    $u          = auth()->user();
    $moduleName = __('ui.plan_modules.' . $module);
    $icon       = config("plan_modules.$module.icon", 'ri-lock-2-line');
    $benefits   = (array) __('ui.module_locked.benefits.' . $module);
    // A missing benefits list comes back as the key itself, never an array of
    // lines — fall back to no bullets rather than printing "ui.module_locked…".
    if (count($benefits) === 1 && is_string(reset($benefits)) && str_starts_with(reset($benefits), 'ui.')) {
        $benefits = [];
    }

    // The blurred backdrop is decoration: enough shape to read as "this is the
    // real page", never real data. Column headers and stat labels come from the
    // module's own vocabulary so the preview matches what unlocking delivers.
    $preview = [
        'teams' => [
            'stats'   => ['ui.module_locked.preview.teams_stat_1', 'ui.module_locked.preview.teams_stat_2', 'ui.module_locked.preview.teams_stat_3'],
            'values'  => ['4', '12', '3'],
            'columns' => ['ui.module_locked.preview.col_name', 'ui.module_locked.preview.col_members', 'ui.module_locked.preview.col_status'],
            'rows'    => 6,
        ],
        'reservations' => [
            'stats'   => ['ui.module_locked.preview.reservations_stat_1', 'ui.module_locked.preview.reservations_stat_2', 'ui.module_locked.preview.reservations_stat_3'],
            'values'  => ['28', '9', '96%'],
            'columns' => ['ui.module_locked.preview.col_customer', 'ui.module_locked.preview.col_slot', 'ui.module_locked.preview.col_status'],
            'rows'    => 6,
        ],
        'webchat' => [
            'stats'   => ['ui.module_locked.preview.webchat_stat_1', 'ui.module_locked.preview.webchat_stat_2', 'ui.module_locked.preview.webchat_stat_3'],
            'values'  => ['143', '18', '1m 24s'],
            'columns' => ['ui.module_locked.preview.col_visitor', 'ui.module_locked.preview.col_page', 'ui.module_locked.preview.col_status'],
            'rows'    => 6,
        ],
    ][$module] ?? [
        'stats'   => ['ui.module_locked.preview.generic_stat_1', 'ui.module_locked.preview.generic_stat_2', 'ui.module_locked.preview.generic_stat_3'],
        'values'  => ['—', '—', '—'],
        'columns' => ['ui.module_locked.preview.col_name', 'ui.module_locked.preview.col_detail', 'ui.module_locked.preview.col_status'],
        'rows'    => 5,
    ];
@endphp

<div class="locked-stage">

    {{-- Blurred mock of the page behind the paywall. Inert and hidden from
         assistive tech: it carries no information, only the shape of the page. --}}
    <div class="locked-preview" aria-hidden="true">
        <div class="page-header">
            <div class="page-header-left">
                <div class="page-title">{{ $moduleName }}</div>
                <div class="page-subtitle">{{ __('ui.module_locked.preview.subtitle') }}</div>
            </div>
            <div class="page-header-actions">
                <span class="btn btn-primary btn-sm"><i class="ri-add-line"></i> {{ __('ui.module_locked.preview.new') }}</span>
            </div>
        </div>

        <div class="stats-grid">
            @foreach($preview['stats'] as $s => $statKey)
            <div class="stat-card {{ ['', 'blue', 'purple'][$s] ?? '' }}">
                <div class="stat-card-icon"><i class="{{ $icon }}"></i></div>
                <div>
                    <div class="stat-card-value">{{ $preview['values'][$s] ?? '—' }}</div>
                    <div class="stat-card-label">{{ __($statKey) }}</div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="table-wrap">
            <div class="table-toolbar">
                <div class="filter-input-wrap">
                    <i class="ri-search-line"></i>
                    <span class="filter-input" style="display:inline-flex;align-items:center;color:var(--text-muted)">{{ __('ui.module_locked.preview.search') }}</span>
                </div>
                <span class="toolbar-select" style="display:inline-flex;align-items:center">{{ __('ui.module_locked.preview.all') }}</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        @foreach($preview['columns'] as $colKey)
                        <th>{{ __($colKey) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @for($r = 0; $r < $preview['rows']; $r++)
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <span class="lk-dot"></span>
                                <span class="lk-bar" style="width:{{ 96 + (($r * 37) % 74) }}px"></span>
                            </div>
                        </td>
                        <td><span class="lk-bar" style="width:{{ 54 + (($r * 23) % 52) }}px"></span></td>
                        <td><span class="badge badge-green">{{ __('ui.module_locked.preview.active') }}</span></td>
                    </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </div>

    <div class="locked-scrim" aria-hidden="true"></div>

    {{-- The offer itself. --}}
    <div class="locked-panel" role="region" aria-label="{{ __('ui.module_locked.title', ['module' => $moduleName]) }}">
        <div class="locked-icon">
            <i class="{{ $icon }}"></i>
            <span class="locked-icon-lock"><i class="ri-lock-2-fill"></i></span>
        </div>

        <div class="locked-eyebrow">{{ __('ui.module_locked.eyebrow') }}</div>
        <h2 class="locked-title">{{ __('ui.module_locked.title', ['module' => $moduleName]) }}</h2>
        <p class="locked-lede">{{ __('ui.module_locked.lede', ['module' => $moduleName]) }}</p>

        @if($benefits)
        <ul class="locked-benefits">
            @foreach($benefits as $benefit)
            <li><i class="ri-check-line"></i><span>{{ $benefit }}</span></li>
            @endforeach
        </ul>
        @endif

        @if($upgradePlan)
            <form method="POST" action="{{ route('payment.upgrade') }}" class="locked-cta">
                @csrf
                <input type="hidden" name="plan_id" value="{{ $upgradePlan->id }}">
                <button type="submit" class="btn btn-primary">
                    <i class="ri-arrow-up-circle-line"></i>
                    {{ $upgradePlan->hasTrial() && !$u->tenant?->hasTrialedPlan($upgradePlan->id)
                        ? __('ui.module_locked.cta_trial', ['plan' => $upgradePlan->name, 'days' => $upgradePlan->trialDays()])
                        : __('ui.module_locked.cta', ['plan' => $upgradePlan->name, 'price' => rtrim(rtrim(number_format((float) $upgradePlan->price_monthly, 2), '0'), '.')]) }}
                </button>
            </form>
            <a class="locked-secondary" href="{{ route($u->routeNamePrefix() . '.billing.index') }}">
                {{ __('ui.module_locked.compare_plans') }}
            </a>
        @else
            {{-- No sellable plan carries this module, or the viewer cannot buy. --}}
            <p class="locked-secondary-note">{{ __('ui.module_locked.ask_admin') }}</p>
        @endif
    </div>
</div>
@endsection

@push('styles')
<style>
    .locked-stage { position: relative; }

    .locked-preview {
        filter: blur(7px) saturate(.7);
        opacity: .5;
        pointer-events: none;
        user-select: none;
        max-height: 720px;
        overflow: hidden;
        /* Blur samples transparent pixels at the edges and fades them; the
           slight scale-up pushes that soft rim outside the visible area. */
        transform: scale(1.015);
        transform-origin: top center;
    }

    .locked-preview .lk-bar {
        display: inline-block;
        height: 10px;
        border-radius: 999px;
        background: var(--card-border);
    }
    .locked-preview .lk-dot {
        display: inline-block;
        width: 28px; height: 28px;
        border-radius: 50%;
        background: var(--brand-light);
        flex-shrink: 0;
    }

    .locked-scrim {
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg,
            rgba(243,244,246,.30) 0%,
            rgba(243,244,246,.80) 42%,
            var(--page-bg) 78%);
        pointer-events: none;
    }

    .locked-panel {
        position: absolute;
        top: 56px;
        inset-inline: 0;
        margin-inline: auto;
        width: min(560px, calc(100% - 24px));
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: var(--radius-xl);
        box-shadow: 0 24px 60px -24px rgba(15,23,42,.30), var(--card-shadow);
        padding: 32px 32px 28px;
        text-align: center;
        animation: locked-rise .38s cubic-bezier(.16,.84,.44,1) both;
    }

    @keyframes locked-rise {
        from { opacity: 0; transform: translateY(14px); }
        to   { opacity: 1; transform: none; }
    }
    @media (prefers-reduced-motion: reduce) {
        .locked-panel { animation: none; }
    }

    .locked-icon {
        position: relative;
        width: 60px; height: 60px;
        margin: 0 auto 18px;
        border-radius: var(--radius-lg);
        background: var(--brand-xlight);
        border: 1px solid var(--brand-light);
        display: flex; align-items: center; justify-content: center;
    }
    .locked-icon > i { font-size: 27px; color: var(--brand); }
    .locked-icon-lock {
        position: absolute;
        bottom: -7px; inset-inline-end: -7px;
        width: 26px; height: 26px;
        border-radius: 50%;
        background: var(--brand);
        border: 2px solid var(--card-bg);
        display: flex; align-items: center; justify-content: center;
    }
    .locked-icon-lock i { font-size: 12px; color: #fff; }

    .locked-eyebrow {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--brand);
        margin-bottom: 8px;
    }

    .locked-title {
        font-size: 21px;
        font-weight: 700;
        color: var(--text-primary);
        letter-spacing: -.4px;
        line-height: 1.25;
    }

    .locked-lede {
        font-size: 13.5px;
        color: var(--text-secondary);
        margin-top: 8px;
        line-height: 1.55;
    }

    .locked-benefits {
        list-style: none;
        margin: 20px 0 0;
        padding: 16px;
        background: var(--page-bg);
        border-radius: var(--radius);
        display: flex;
        flex-direction: column;
        gap: 10px;
        text-align: start;
    }
    .locked-benefits li {
        display: flex;
        gap: 9px;
        align-items: flex-start;
        font-size: 13px;
        color: var(--text-primary);
        line-height: 1.45;
    }
    .locked-benefits li i {
        color: var(--brand);
        font-size: 15px;
        flex-shrink: 0;
        line-height: 1.35;
    }

    .locked-cta { margin-top: 22px; }
    .locked-cta .btn { width: 100%; padding: 12px 18px; font-size: 14.5px; }

    .locked-secondary {
        display: inline-block;
        margin-top: 12px;
        font-size: 13px;
        color: var(--text-secondary);
        font-weight: 500;
    }
    .locked-secondary:hover { color: var(--brand); }

    .locked-secondary-note {
        margin-top: 22px;
        font-size: 13px;
        color: var(--text-secondary);
        line-height: 1.55;
    }

    /* On a phone the mock is all scroll and no signal — lead with the offer. */
    @media (max-width: 640px) {
        .locked-preview, .locked-scrim { display: none; }
        .locked-panel { position: static; width: 100%; padding: 26px 20px 22px; }
    }
</style>
@endpush
