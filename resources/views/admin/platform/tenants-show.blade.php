@extends('layouts.admin')

@section('title', $tenant->name)

@section('breadcrumb')
    <span>{{ __('ui.platform_tenants_show_page.breadcrumb_root') }}</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <a href="{{ route('super_admin.platform.tenants') }}" style="color:var(--text-secondary);text-decoration:none">{{ __('ui.platform_tenants_show_page.breadcrumb') }}</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ $tenant->name }}</span>
@endsection

@section('content')
<div>
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ $tenant->name }}</div>
            <div class="page-subtitle">{{ __('ui.platform_tenants_show_page.tenant_slug') }}: {{ $tenant->slug }}</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('super_admin.platform.tenants') }}" class="btn btn-outline">
                <i class="ri-arrow-left-line"></i> {{ __('ui.back') }}
            </a>
            <a href="{{ route('super_admin.platform.tenants.edit', $tenant) }}" class="btn btn-primary">
                <i class="ri-pencil-line"></i> {{ __('ui.platform_tenants_show_page.edit_tenant') }}
            </a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-team-line"></i></div>
            <div class="stat-card-value">{{ number_format($tenant->users_count) }}</div>
            <div class="stat-card-label">{{ __('ui.platform_tenants_show_page.users') }}</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-card-icon"><i class="ri-group-line"></i></div>
            <div class="stat-card-value">{{ number_format($tenant->teams_count) }}</div>
            <div class="stat-card-label">{{ __('ui.platform_tenants_show_page.teams') }}</div>
        </div>
        <div class="stat-card purple">
            <div class="stat-card-icon"><i class="ri-whatsapp-line"></i></div>
            <div class="stat-card-value">{{ number_format($tenant->instances_count) }}</div>
            <div class="stat-card-label">{{ __('ui.platform_tenants_show_page.instances') }}</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-card-icon"><i class="ri-message-3-line"></i></div>
            <div class="stat-card-value">{{ number_format($tenant->conversations_count) }}</div>
            <div class="stat-card-label">{{ __('ui.platform_tenants_show_page.conversations') }}</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:1.25rem;align-items:start;">
        <div class="card">
            <div class="card-header">
                <div class="card-title">{{ __('ui.platform_tenants_show_page.tenant_information') }}</div>
            </div>
            <div style="padding:20px;">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.platform_tenants_show_page.plan') }}</label>
                        <div class="form-control" style="display:flex;align-items:center;">
                            {{ $tenant->plan?->name ?? __('ui.platform_tenants_page.no_plan') }}
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.platform_tenants_show_page.subscription_status') }}</label>
                        <div style="height:40px;display:flex;align-items:center;">
                            @php
                                $statusBadge = match($tenant->subscription_status) {
                                    'active' => 'badge-green',
                                    'trial' => 'badge-orange',
                                    'suspended' => 'badge-red',
                                    default => 'badge-gray',
                                };
                                $statusKey = 'ui.platform_tenants_page.statuses.' . $tenant->subscription_status;
                                $statusLabel = __($statusKey);
                                if ($statusLabel === $statusKey) {
                                    $statusLabel = ucfirst($tenant->subscription_status);
                                }
                            @endphp
                            <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                            @if(!$tenant->is_active)
                                <span class="badge badge-gray" style="margin-left:6px;">{{ __('ui.platform_tenants_page.inactive') }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.platform_tenants_show_page.trial_ends_at') }}</label>
                        <div class="form-control" style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                            <span>{{ $tenant->trial_ends_at?->format('M j, Y') ?? __('ui.platform_tenants_show_page.not_set') }}</span>
                            {{-- QA-only lever: back-date trial_ends_at to
                                 yesterday so post-trial behavior can be
                                 tested immediately. Only rendered when the
                                 tenant is actually on trial. --}}
                            @if($tenant->isOnTrial())
                                <form method="POST" action="{{ route('super_admin.platform.tenants.expire-trial', $tenant) }}" onsubmit="return confirm(@js(__('ui.platform_tenants_show_page.expire_trial_confirm', ['name' => $tenant->name])))" style="margin:0;">
                                    @csrf
                                    <button type="submit" class="btn btn-outline" style="padding:2px 10px;font-size:12px;color:#b91c1c;border-color:#fecaca;">
                                        <i class="ri-time-line"></i>
                                        {{ __('ui.platform_tenants_show_page.expire_trial_now') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.platform_tenants_show_page.stripe_customer_id') }}</label>
                        <div class="form-control" style="display:flex;align-items:center;font-family:monospace;">
                            {{ $tenant->stripe_id ?: __('ui.platform_tenants_show_page.not_set') }}
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label">{{ __('ui.platform_tenants_show_page.settings_json') }}</label>
                    <textarea class="form-control" rows="8" readonly>{{ $tenant->settings ? json_encode($tenant->settings, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) : '{}' }}</textarea>
                </div>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:1rem;">
            <div class="card">
                <div class="card-header" style="padding-bottom:12px;">
                    <div class="card-title">{{ __('ui.platform_tenants_show_page.meta') }}</div>
                </div>
                <div style="padding:0 18px 18px 18px;display:flex;flex-direction:column;gap:8px;font-size:13px;">
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-muted);">{{ __('ui.platform_tenants_show_page.created') }}</span>
                        <strong>{{ $tenant->created_at?->format('M j, Y') }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-muted);">{{ __('ui.platform_tenants_show_page.last_updated') }}</span>
                        <strong>{{ $tenant->updated_at?->diffForHumans() }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-muted);">{{ __('ui.platform_tenants_show_page.customers') }}</span>
                        <strong>{{ number_format($tenant->customers_count) }}</strong>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="padding-bottom:12px;">
                    <div class="card-title">{{ __('ui.platform_tenants_show_page.recent_users') }}</div>
                </div>
                <div style="padding:0 18px 18px 18px;display:flex;flex-direction:column;gap:8px;">
                    @forelse($tenant->users as $user)
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 10px;background:var(--page-bg);border-radius:8px;">
                            <div style="min-width:0;">
                                <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $user->name }}</div>
                                <div style="font-size:12px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $user->email }}</div>
                            </div>
                            @php
                                $roleKey = 'ui.roles.' . $user->role;
                                $roleLabel = __($roleKey);
                                if ($roleLabel === $roleKey) {
                                    $roleLabel = ucfirst($user->role);
                                }
                            @endphp
                            <span class="badge badge-gray">{{ $roleLabel }}</span>
                        </div>
                    @empty
                        <div style="font-size:13px;color:var(--text-muted);">{{ __('ui.platform_tenants_show_page.no_users_yet') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Linked accounts (fraud / abuse detection) — other tenants that
         share a WhatsApp number (or, in future, other signals) with this
         one. Empty state kept quiet: most tenants have zero linkages, and
         a wall of "no links found" clutters every profile. --}}
    @if($linkedTenants->isNotEmpty())
    <div class="card" style="margin-top:1.25rem;border-left:3px solid #f59e0b;">
        <div class="card-header">
            <div class="card-title" style="display:flex;align-items:center;gap:.5rem;">
                <i class="ri-links-line" style="color:#f59e0b;"></i>
                {{ __('ui.platform_tenants_show_page.linked_accounts') }}
            </div>
            <span style="font-size:12px;color:var(--text-muted);">
                {{ $linkedTenants->count() }} {{ __('ui.platform_tenants_show_page.linked_accounts_count') }}
            </span>
        </div>
        <div style="padding:8px 20px 20px;">
            <p style="font-size:12px;color:var(--text-muted);margin:0 0 12px;">
                {{ __('ui.platform_tenants_show_page.linked_accounts_hint') }}
            </p>
            <div style="display:flex;flex-direction:column;gap:8px;">
                @foreach($linkedTenants as $linked)
                    @php $link = $linked->pivot_link; @endphp
                    <a href="{{ route('super_admin.platform.tenants.show', $linked) }}"
                       style="display:flex;align-items:center;gap:12px;padding:10px 12px;border:1px solid var(--card-border);border-radius:8px;text-decoration:none;color:inherit;background:var(--page-bg);">
                        <div style="width:32px;height:32px;border-radius:6px;background:rgba(245,158,11,.15);color:#f59e0b;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0;">
                            {{ mb_strtoupper(mb_substr($linked->name ?? '?', 0, 1)) }}
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:600;font-size:14px;">{{ $linked->name }}</div>
                            <div style="font-size:12px;color:var(--text-muted);">
                                {{ __('ui.platform_tenants_show_page.linked_reason_' . $link->reason) }}
                                @php $phones = data_get($link->evidence, 'phone_numbers', []); @endphp
                                @if(!empty($phones))
                                    &middot; <code style="font-family:inherit;background:none;padding:0;">{{ implode(', ', $phones) }}</code>
                                @endif
                                &middot; {{ __('ui.platform_tenants_show_page.linked_first_detected') }}
                                {{ $link->first_detected_at?->diffForHumans() }}
                            </div>
                        </div>
                        <i class="ri-arrow-right-s-line" style="color:var(--text-muted);font-size:18px;flex-shrink:0;"></i>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Payment history --}}
    <div class="card" style="margin-top:1.25rem;">
        <div class="card-header">
            <div class="card-title">{{ __('ui.platform_tenants_show_page.payment_history') }}</div>
            <span style="font-size:12px;color:var(--text-muted);">{{ $payments->count() }} {{ __('ui.platform_tenants_show_page.payment_records') }}</span>
        </div>

        @if($payments->isEmpty())
            <div style="padding:24px 20px;text-align:center;color:var(--text-muted);font-size:13px;">
                <i class="ri-bank-card-line" style="font-size:24px;display:block;margin-bottom:8px;"></i>
                {{ __('ui.platform_tenants_show_page.no_payments') }}
            </div>
        @else
            <div class="table-container" style="border-radius:0 0 12px 12px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.platform_tenants_show_page.payment_date') }}</th>
                            <th>{{ __('ui.platform_tenants_show_page.plan') }}</th>
                            <th>{{ __('ui.platform_tenants_show_page.amount') }}</th>
                            <th>{{ __('ui.platform_tenants_show_page.status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payments as $payment)
                            @php
                                $statusClass = match($payment->status) {
                                    'completed' => 'badge-green',
                                    'pending'   => 'badge-orange',
                                    'failed'    => 'badge-red',
                                    default     => 'badge-gray',
                                };
                            @endphp
                            <tr style="cursor:pointer;" onclick="window.location='{{ route('super_admin.billing.payment.show', $payment) }}'">
                                <td>{{ $payment->paid_at?->format('d/m/Y') ?? $payment->created_at->format('d/m/Y') }}</td>
                                <td>{{ $payment->plan?->name ?? '—' }}</td>
                                <td style="font-weight:600;">{{ number_format($payment->amount, 2) }} {{ strtoupper($payment->currency) }}</td>
                                <td><span class="badge {{ $statusClass }}">{{ ucfirst($payment->status) }}</span></td>
                                <td style="text-align:right;color:var(--text-muted);"><i class="ri-arrow-right-s-line"></i></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
