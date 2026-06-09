@extends('layouts.admin')

@section('title', __('ui.payments_page.title'))

@section('breadcrumb')
    <a href="{{ route('super_admin.billing.index') }}" style="color:var(--text-secondary);text-decoration:none">{{ __('ui.sidebar.billing') }}</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ __('ui.payments_page.title') }}</span>
@endsection

@section('content')
@php
    $completed = $totals['completed'] ?? 0;
    $pending   = $totals['pending']   ?? 0;
    $failed    = $totals['failed']    ?? 0;
@endphp

<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">{{ __('ui.payments_page.title') }}</div>
        <div class="page-subtitle">{{ __('ui.payments_page.subtitle') }}</div>
    </div>
</div>

{{-- KPI cards --}}
<div class="stats-grid" style="margin-bottom:1.5rem;grid-template-columns:repeat(4,1fr)">
    <div class="stat-card">
        <div class="stat-card-icon"><i class="ri-money-dollar-circle-line"></i></div>
        <div class="stat-card-value">USD {{ number_format($totalRevenue, 2) }}</div>
        <div class="stat-card-label">{{ __('ui.payments_page.total_revenue') }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><i class="ri-checkbox-circle-line"></i></div>
        <div class="stat-card-value">{{ $payments->total() }}</div>
        <div class="stat-card-label">{{ __('ui.payments_page.total_transactions') }}</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-card-icon"><i class="ri-time-line"></i></div>
        <div class="stat-card-value">{{ $pending }}</div>
        <div class="stat-card-label">{{ __('ui.payments_page.pending') }}</div>
    </div>
    <div class="stat-card" style="--stat-accent:#ef4444">
        <div class="stat-card-icon" style="color:#ef4444"><i class="ri-close-circle-line"></i></div>
        <div class="stat-card-value">{{ $failed }}</div>
        <div class="stat-card-label">{{ __('ui.payments_page.failed') }}</div>
    </div>
</div>

{{-- Filters --}}
<div class="card" style="padding:0;margin-bottom:1rem;">
    <form method="GET" action="{{ route('super_admin.billing.payments') }}"
          style="display:flex;gap:.75rem;padding:.875rem 1.25rem;flex-wrap:wrap;align-items:center;">
        <div style="flex:1;min-width:200px;">
            <div class="search-wrap">
                <i class="ri-search-line search-icon"></i>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="{{ __('ui.payments_page.search_placeholder') }}"
                       class="search-input">
            </div>
        </div>
        <div x-data="{
                open: false,
                value: '{{ request('status') }}',
                options: [
                    {value: '', label: '{{ __('ui.payments_page.all_statuses') }}'},
                    {value: 'completed', label: '{{ __('ui.payments_page.status_completed') }}'},
                    {value: 'pending',   label: '{{ __('ui.payments_page.status_pending') }}'},
                    {value: 'failed',    label: '{{ __('ui.payments_page.status_failed') }}'},
                ],
                get label() { return this.options.find(o => o.value === this.value)?.label ?? '{{ __('ui.payments_page.all_statuses') }}'; },
                select(val) { this.value = val; this.open = false; this.$el.closest('form').submit(); }
             }" style="position:relative;">
            <input type="hidden" name="status" :value="value">
            <button type="button" @click="open = !open" @click.outside="open = false"
                    style="display:flex;align-items:center;gap:.5rem;padding:.45rem .875rem;background:#fff;border:1px solid var(--card-border);border-radius:.5rem;font-size:.875rem;color:var(--text-secondary);cursor:pointer;white-space:nowrap;min-width:160px;justify-content:space-between;font-family:inherit;">
                <span x-text="label"></span>
                <i class="ri-arrow-down-s-line" style="font-size:1rem;transition:transform .15s;" :style="open ? 'transform:rotate(180deg)' : ''"></i>
            </button>
            <div x-show="open" x-cloak
                 style="position:absolute;bottom:calc(100% + 6px);left:0;min-width:160px;background:#fff;border:1px solid var(--card-border);border-radius:.625rem;box-shadow:0 -4px 20px rgba(0,0,0,.1);z-index:9999;overflow:hidden;">
                <template x-for="opt in options" :key="opt.value">
                    <button type="button" @click="select(opt.value)"
                            :style="opt.value === value ? 'background:var(--brand-xlight);color:var(--brand);font-weight:600;' : ''"
                            style="display:block;width:100%;text-align:left;padding:.5rem .875rem;font-size:.875rem;background:none;border:none;cursor:pointer;color:var(--text-secondary);font-family:inherit;transition:background .1s;">
                        <span x-text="opt.label"></span>
                    </button>
                </template>
            </div>
        </div>
        @if(request('search') || request('status'))
            <a href="{{ route('super_admin.billing.payments') }}" class="btn btn-outline btn-sm">
                <i class="ri-close-line"></i> {{ __('ui.clear') }}
            </a>
        @endif
    </form>
</div>

{{-- Table --}}
<div class="card" style="padding:0;">
    <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('ui.payments_page.col_tenant') }}</th>
                    <th>{{ __('ui.payments_page.col_plan') }}</th>
                    <th style="text-align:right;">{{ __('ui.payments_page.col_amount') }}</th>
                    <th style="text-align:center;">{{ __('ui.payments_page.col_status') }}</th>
                    <th>{{ __('ui.payments_page.col_date') }}</th>
                    <th>{{ __('ui.payments_page.col_session') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $payment)
                <tr>
                    <td style="color:var(--text-muted);font-size:.8125rem;">{{ $payment->id }}</td>
                    <td>
                        <div style="display:flex;align-items:center;gap:.625rem;">
                            <div style="width:2rem;height:2rem;border-radius:.5rem;background:linear-gradient(135deg,rgba(16,185,129,.15),rgba(5,150,105,.25));display:flex;align-items:center;justify-content:center;color:var(--brand);font-size:.75rem;font-weight:700;flex-shrink:0;">
                                {{ strtoupper(substr($payment->tenant?->name ?? '?', 0, 1)) }}
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:.875rem;">{{ $payment->tenant?->name ?? '—' }}</div>
                                <div style="font-size:.75rem;color:var(--text-muted);">{{ $payment->tenant?->slug ?? '' }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span style="font-size:.875rem;font-weight:500;">{{ $payment->plan?->name ?? '—' }}</span>
                    </td>
                    <td style="text-align:right;">
                        <span style="font-weight:700;font-size:.9375rem;color:{{ $payment->isCompleted() ? 'var(--brand)' : 'var(--text-muted)' }};">
                            {{ $payment->currency ?? 'USD' }} {{ number_format($payment->amount, 2) }}
                        </span>
                    </td>
                    <td style="text-align:center;">
                        @if($payment->status === 'completed')
                            <span class="badge badge-green"><i class="ri-checkbox-circle-line"></i> {{ __('ui.payments_page.status_completed') }}</span>
                        @elseif($payment->status === 'pending')
                            <span class="badge badge-orange"><i class="ri-time-line"></i> {{ __('ui.payments_page.status_pending') }}</span>
                        @else
                            <span class="badge badge-red"><i class="ri-close-circle-line"></i> {{ __('ui.payments_page.status_failed') }}</span>
                        @endif
                    </td>
                    <td style="font-size:.8125rem;color:var(--text-secondary);white-space:nowrap;">
                        @if($payment->paid_at)
                            <div>{{ $payment->paid_at->format('d M Y') }}</div>
                            <div style="color:var(--text-muted);font-size:.75rem;">{{ $payment->paid_at->format('H:i') }}</div>
                        @elseif($payment->created_at)
                            <div style="color:var(--text-muted);">{{ $payment->created_at->format('d M Y') }}</div>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if($payment->stripe_session_id)
                            <span style="font-family:monospace;font-size:.75rem;color:var(--text-muted);background:var(--page-bg);padding:.15rem .4rem;border-radius:.375rem;border:1px solid var(--card-border);">
                                {{ Str::limit($payment->stripe_session_id, 24) }}
                            </span>
                        @else
                            <span style="color:var(--text-muted);">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="empty-state" style="padding:3rem 1rem;">
                            <div class="empty-state-icon"><i class="ri-receipt-line"></i></div>
                            <h4>{{ __('ui.payments_page.no_payments') }}</h4>
                            <p>{{ __('ui.payments_page.no_payments_desc') }}</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($payments->hasPages())
    <div style="padding:.875rem 1.25rem;border-top:1px solid var(--card-border);">
        {{ $payments->links('admin.partials.pagination') }}
    </div>
    @endif
</div>

@endsection
