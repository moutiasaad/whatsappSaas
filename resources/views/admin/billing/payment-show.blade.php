@extends('layouts.admin')

@section('title', __('ui.payments_page.title') . ' #' . $payment->id)

@section('breadcrumb')
    <a href="{{ route('super_admin.billing.index') }}" style="color:var(--text-secondary);text-decoration:none">{{ __('ui.sidebar.billing') }}</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <a href="{{ route('super_admin.billing.payments') }}" style="color:var(--text-secondary);text-decoration:none">{{ __('ui.payments_page.title') }}</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>#{{ $payment->id }}</span>
@endsection

@section('content')

@php
    $statusMap = [
        'completed' => ['class' => 'badge-green',  'icon' => 'ri-checkbox-circle-line'],
        'pending'   => ['class' => 'badge-orange', 'icon' => 'ri-time-line'],
        'failed'    => ['class' => 'badge-red',    'icon' => 'ri-close-circle-line'],
    ];
    $st = $statusMap[$payment->status] ?? ['class' => 'badge-gray', 'icon' => 'ri-question-line'];

    $subStatusMap = [
        'trial'     => ['class' => 'badge-purple', 'icon' => 'ri-gift-line'],
        'active'    => ['class' => 'badge-green',  'icon' => 'ri-checkbox-circle-line'],
        'inactive'  => ['class' => 'badge-gray',   'icon' => 'ri-pause-circle-line'],
        'suspended' => ['class' => 'badge-orange', 'icon' => 'ri-alert-line'],
        'cancelled' => ['class' => 'badge-red',    'icon' => 'ri-close-circle-line'],
    ];
    $ss = $subStatusMap[$tenant?->subscription_status ?? ''] ?? ['class' => 'badge-gray', 'icon' => 'ri-question-line'];
@endphp

{{-- Page header --}}
<div class="page-header">
    <div class="page-header-left" style="gap:.75rem;">
        <a href="{{ route('super_admin.billing.payments') }}" class="btn btn-outline btn-sm">
            <i class="ri-arrow-left-line"></i> {{ __('ui.back') }}
        </a>
        <div>
            <div class="page-title" style="display:flex;align-items:center;gap:.625rem;">
                {{ __('ui.payments_page.title') }} #{{ $payment->id }}
                <span class="badge {{ $st['class'] }}"><i class="{{ $st['icon'] }}"></i> {{ __('ui.payments_page.status_' . $payment->status) }}</span>
            </div>
            <div class="page-subtitle">
                {{ $payment->created_at?->format('d M Y, H:i') }}
            </div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:1.5rem;align-items:start;">

    {{-- ── Left column ── --}}
    <div style="display:flex;flex-direction:column;gap:1.25rem;">

        {{-- Payment details --}}
        <div class="card">
            <div style="padding:1.125rem 1.25rem;border-bottom:1px solid var(--card-border);display:flex;align-items:center;gap:.625rem;">
                <i class="ri-receipt-line" style="color:var(--brand);font-size:1.1rem;"></i>
                <span style="font-weight:700;font-size:.9375rem;">{{ __('ui.payments_page.payment_details') }}</span>
            </div>
            <div style="padding:1.25rem;display:grid;grid-template-columns:1fr 1fr;gap:1rem;">

                <div class="detail-item">
                    <div class="detail-label">{{ __('ui.payments_page.payment_id') }}</div>
                    <div class="detail-value" style="font-family:monospace;">#{{ $payment->id }}</div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">{{ __('ui.payments_page.col_status') }}</div>
                    <div class="detail-value">
                        <span class="badge {{ $st['class'] }}"><i class="{{ $st['icon'] }}"></i> {{ __('ui.payments_page.status_' . $payment->status) }}</span>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">{{ __('ui.payments_page.col_amount') }}</div>
                    <div class="detail-value" style="font-size:1.25rem;font-weight:800;color:{{ $payment->isCompleted() ? 'var(--brand)' : 'var(--text-primary)' }};">
                        {{ $payment->currency ?? 'USD' }} {{ number_format((float)$payment->amount, 2) }}
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">{{ __('ui.payments_page.col_plan') }}</div>
                    <div class="detail-value">{{ $payment->plan?->name ?? '—' }}</div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">{{ __('ui.payments_page.created_at') }}</div>
                    <div class="detail-value">
                        {{ $payment->created_at?->format('d M Y') }}
                        <span style="color:var(--text-muted);font-size:.8125rem;">{{ $payment->created_at?->format('H:i') }}</span>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">{{ __('ui.payments_page.paid_at') }}</div>
                    <div class="detail-value">
                        @if($payment->paid_at)
                            {{ $payment->paid_at->format('d M Y') }}
                            <span style="color:var(--text-muted);font-size:.8125rem;">{{ $payment->paid_at->format('H:i') }}</span>
                        @else
                            <span style="color:var(--text-muted);">—</span>
                        @endif
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">{{ __('ui.payments_page.payment_method', ['default' => 'Payment method']) }}</div>
                    <div class="detail-value" style="display:flex;align-items:center;gap:.4rem;">
                        @if(($payment->payment_method ?? 'stripe') === 'paypal')
                            <span class="badge badge-blue"><i class="ri-paypal-line"></i> PayPal</span>
                        @else
                            <span class="badge badge-purple"><i class="ri-bank-card-line"></i> Stripe</span>
                        @endif
                    </div>
                </div>

                @if($payment->stripe_session_id)
                <div class="detail-item" style="grid-column:1/-1;">
                    <div class="detail-label">{{ __('ui.payments_page.col_session') }}</div>
                    <div style="display:flex;align-items:center;gap:.5rem;margin-top:.25rem;">
                        <code style="font-size:.8125rem;background:var(--page-bg);border:1px solid var(--card-border);padding:.3rem .625rem;border-radius:.5rem;word-break:break-all;flex:1;">{{ $payment->stripe_session_id }}</code>
                        <button type="button" onclick="navigator.clipboard.writeText('{{ $payment->stripe_session_id }}');this.innerHTML='<i class=\'ri-check-line\'></i>';setTimeout(()=>this.innerHTML='<i class=\'ri-file-copy-line\'></i>',1500)"
                                class="btn btn-outline btn-sm" title="Copy" style="flex-shrink:0;">
                            <i class="ri-file-copy-line"></i>
                        </button>
                    </div>
                </div>
                @endif

                @if($payment->stripe_checkout_url)
                <div class="detail-item" style="grid-column:1/-1;">
                    <div class="detail-label">{{ __('ui.payments_page.checkout_url') }}</div>
                    <div style="margin-top:.25rem;">
                        <a href="{{ $payment->stripe_checkout_url }}" target="_blank" rel="noopener"
                           style="font-size:.8125rem;color:var(--brand);word-break:break-all;">
                            <i class="ri-external-link-line"></i> {{ Str::limit($payment->stripe_checkout_url, 80) }}
                        </a>
                    </div>
                </div>
                @endif

                @if($payment->paypal_order_id)
                <div class="detail-item" style="grid-column:1/-1;">
                    <div class="detail-label">{{ __('ui.payments_page.paypal_order_id', ['default' => 'PayPal order ID']) }}</div>
                    <div style="display:flex;align-items:center;gap:.5rem;margin-top:.25rem;">
                        <code style="font-size:.8125rem;background:var(--page-bg);border:1px solid var(--card-border);padding:.3rem .625rem;border-radius:.5rem;word-break:break-all;flex:1;">{{ $payment->paypal_order_id }}</code>
                        <button type="button" onclick="navigator.clipboard.writeText('{{ $payment->paypal_order_id }}');this.innerHTML='<i class=\'ri-check-line\'></i>';setTimeout(()=>this.innerHTML='<i class=\'ri-file-copy-line\'></i>',1500)"
                                class="btn btn-outline btn-sm" title="Copy" style="flex-shrink:0;">
                            <i class="ri-file-copy-line"></i>
                        </button>
                    </div>
                </div>
                @endif

                @if($payment->paypal_capture_id)
                <div class="detail-item" style="grid-column:1/-1;">
                    <div class="detail-label">{{ __('ui.payments_page.paypal_capture_id', ['default' => 'PayPal capture ID']) }}</div>
                    <div style="display:flex;align-items:center;gap:.5rem;margin-top:.25rem;">
                        <code style="font-size:.8125rem;background:var(--page-bg);border:1px solid var(--card-border);padding:.3rem .625rem;border-radius:.5rem;word-break:break-all;flex:1;">{{ $payment->paypal_capture_id }}</code>
                        <button type="button" onclick="navigator.clipboard.writeText('{{ $payment->paypal_capture_id }}');this.innerHTML='<i class=\'ri-check-line\'></i>';setTimeout(()=>this.innerHTML='<i class=\'ri-file-copy-line\'></i>',1500)"
                                class="btn btn-outline btn-sm" title="Copy" style="flex-shrink:0;">
                            <i class="ri-file-copy-line"></i>
                        </button>
                    </div>
                </div>
                @endif

            </div>
        </div>

        {{-- Gateway response --}}
        @if($payment->gateway_response)
        <div class="card" x-data="{ open: false }">
            <button type="button" @click="open = !open"
                    style="width:100%;padding:1.125rem 1.25rem;background:none;border:none;border-bottom:1px solid var(--card-border);display:flex;align-items:center;justify-content:space-between;cursor:pointer;font-family:inherit;">
                <div style="display:flex;align-items:center;gap:.625rem;">
                    <i class="ri-code-s-slash-line" style="color:var(--brand);font-size:1.1rem;"></i>
                    <span style="font-weight:700;font-size:.9375rem;">{{ __('ui.payments_page.gateway_response') }}</span>
                </div>
                <i class="ri-arrow-down-s-line" :style="open ? 'transform:rotate(180deg)' : ''" style="transition:transform .2s;color:var(--text-muted);"></i>
            </button>
            <div x-show="open" x-cloak style="padding:1.25rem;">
                <pre style="background:var(--page-bg);border:1px solid var(--card-border);border-radius:.625rem;padding:1rem;font-size:.75rem;overflow-x:auto;line-height:1.6;color:var(--text-secondary);max-height:340px;overflow-y:auto;">{{ json_encode($payment->gateway_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </div>
        @endif

    </div>

    {{-- ── Right column ── --}}
    <div style="display:flex;flex-direction:column;gap:1.25rem;">

        {{-- Tenant --}}
        <div class="card">
            <div style="padding:1.125rem 1.25rem;border-bottom:1px solid var(--card-border);display:flex;align-items:center;gap:.625rem;">
                <i class="ri-building-line" style="color:var(--brand);font-size:1.1rem;"></i>
                <span style="font-weight:700;font-size:.9375rem;">{{ __('ui.payments_page.tenant_info') }}</span>
            </div>
            <div style="padding:1.25rem;display:flex;flex-direction:column;gap:.875rem;">

                <div style="display:flex;align-items:center;gap:.75rem;">
                    <div style="width:3rem;height:3rem;border-radius:.75rem;background:linear-gradient(135deg,rgba(16,185,129,.15),rgba(5,150,105,.25));display:flex;align-items:center;justify-content:center;color:var(--brand);font-size:1.1rem;font-weight:700;flex-shrink:0;">
                        {{ strtoupper(substr($tenant?->name ?? '?', 0, 1)) }}
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:1rem;">{{ $tenant?->name ?? '—' }}</div>
                        <div style="font-size:.8125rem;color:var(--text-muted);">{{ $tenant?->slug ?? '' }}</div>
                    </div>
                </div>

                @if($adminUser)
                <div class="detail-item">
                    <div class="detail-label">{{ __('ui.payments_page.admin_user') }}</div>
                    <div class="detail-value">{{ $adminUser->name }}</div>
                    <div style="font-size:.8125rem;color:var(--text-muted);">{{ $adminUser->email }}</div>
                </div>
                @endif

                <div class="detail-item">
                    <div class="detail-label">{{ __('ui.payments_page.account_status') }}</div>
                    <div class="detail-value">
                        @if($tenant?->is_active)
                            <span class="badge badge-green"><i class="ri-checkbox-circle-line"></i> Active</span>
                        @else
                            <span class="badge badge-gray"><i class="ri-pause-circle-line"></i> Inactive</span>
                        @endif
                    </div>
                </div>

                <div style="border-top:1px solid var(--card-border);padding-top:.875rem;display:grid;grid-template-columns:1fr 1fr;gap:.625rem;">
                    <div class="detail-item">
                        <div class="detail-label">{{ __('ui.payments_page.total_paid') }}</div>
                        <div class="detail-value" style="font-weight:700;color:var(--brand);">USD {{ number_format((float)$totalPaid, 2) }}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">{{ __('ui.payments_page.total_transactions') }}</div>
                        <div class="detail-value" style="font-weight:700;">{{ $paymentsCount }}</div>
                    </div>
                </div>

                @if($tenant)
                <div>
                    <a href="{{ route('super_admin.platform.tenants.show', $tenant) }}" class="btn btn-outline btn-sm" style="width:100%;justify-content:center;">
                        <i class="ri-external-link-line"></i> {{ __('ui.payments_page.view_tenant') }}
                    </a>
                </div>
                @endif

            </div>
        </div>

        {{-- Subscription --}}
        <div class="card">
            <div style="padding:1.125rem 1.25rem;border-bottom:1px solid var(--card-border);display:flex;align-items:center;gap:.625rem;">
                <i class="ri-vip-crown-line" style="color:var(--brand);font-size:1.1rem;"></i>
                <span style="font-weight:700;font-size:.9375rem;">{{ __('ui.payments_page.subscription_details') }}</span>
            </div>
            <div style="padding:1.25rem;display:flex;flex-direction:column;gap:.875rem;">

                <div class="detail-item">
                    <div class="detail-label">{{ __('ui.payments_page.subscription_status') }}</div>
                    <div class="detail-value">
                        <span class="badge {{ $ss['class'] }}"><i class="{{ $ss['icon'] }}"></i> {{ ucfirst($tenant?->subscription_status ?? '—') }}</span>
                    </div>
                </div>

                @if($tenant?->trial_ends_at)
                <div class="detail-item">
                    <div class="detail-label">{{ __('ui.payments_page.trial_ends') }}</div>
                    <div class="detail-value">{{ $tenant->trial_ends_at->format('d M Y') }}</div>
                </div>
                @endif

                @if($payment->plan)
                <div style="border-top:1px solid var(--card-border);padding-top:.875rem;">
                    <div class="detail-label" style="margin-bottom:.625rem;">{{ __('ui.payments_page.plan_features') }}</div>
                    <div style="display:flex;flex-direction:column;gap:.5rem;">
                        <div class="plan-feat"><i class="ri-user-line"></i> {{ $payment->plan->max_users ?? '∞' }} {{ __('ui.payments_page.feat_users') }}</div>
                        <div class="plan-feat"><i class="ri-message-3-line"></i> {{ $payment->plan->max_conversations_per_month ?? '∞' }} {{ __('ui.payments_page.feat_conversations') }}</div>
                        <div class="plan-feat">
                            @if($payment->plan->ai_included)
                                <i class="ri-sparkling-line" style="color:var(--brand);"></i> {{ __('ui.payments_page.feat_ai_included') }}
                            @else
                                <i class="ri-sparkling-line" style="color:var(--text-muted);"></i> <span style="color:var(--text-muted);">{{ __('ui.payments_page.feat_ai_not_included') }}</span>
                            @endif
                        </div>
                        <div class="plan-feat">
                            <i class="ri-price-tag-3-line"></i>
                            USD {{ number_format((float)$payment->plan->price_monthly, 2) }} / {{ __('ui.payments_page.feat_month') }}
                        </div>
                    </div>
                </div>
                @endif

            </div>
        </div>

    </div>
</div>

<style>
.detail-item { display:flex;flex-direction:column;gap:.2rem; }
.detail-label { font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted); }
.detail-value { font-size:.9rem;color:var(--text-primary);font-weight:500; }
.plan-feat { display:flex;align-items:center;gap:.5rem;font-size:.8125rem;color:var(--text-secondary); }
.plan-feat i { font-size:.9rem;color:var(--brand); }
@media(max-width:860px) {
    div[style*="grid-template-columns:1fr 340px"] { grid-template-columns:1fr !important; }
}
</style>

@endsection
