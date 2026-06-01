@extends('layouts.admin')

@section('title', 'Billing')

@section('breadcrumb')
    <span>Billing</span>
@endsection

@section('content')
<div>

    <div style="margin-bottom:1.5rem">
        <h1 style="font-size:1.375rem;font-weight:700;color:var(--text-primary)">Billing & Plan</h1>
        <p style="font-size:.875rem;color:var(--text-muted);margin-top:.125rem">Manage your subscription and usage</p>
    </div>

    {{-- Current Plan --}}
    <div class="card" style="margin-bottom:1.5rem">
        <div class="card-header">
            <div>
                <div class="card-title">Current Plan</div>
                <div class="card-subtitle">{{ $tenant->name }}</div>
            </div>
            <span class="badge {{ $tenant->subscription_status === 'active' ? 'badge-green' : ($tenant->subscription_status === 'trial' ? 'badge-orange' : 'badge-red') }}">
                {{ ucfirst($tenant->subscription_status) }}
            </span>
        </div>
        <div style="padding:0 1.5rem 1.5rem">
            @if($tenant->plan)
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.25rem">
                <div style="padding:.875rem;background:var(--page-bg);border-radius:.625rem;text-align:center">
                    <div style="font-size:1.25rem;font-weight:700;color:var(--text-primary)">{{ $tenant->plan->max_users }}</div>
                    <div style="font-size:.75rem;color:var(--text-muted);margin-top:.125rem">Max Users</div>
                </div>
                <div style="padding:.875rem;background:var(--page-bg);border-radius:.625rem;text-align:center">
                    <div style="font-size:1.25rem;font-weight:700;color:var(--text-primary)">{{ $tenant->plan->max_instances }}</div>
                    <div style="font-size:.75rem;color:var(--text-muted);margin-top:.125rem">Instances</div>
                </div>
                <div style="padding:.875rem;background:var(--page-bg);border-radius:.625rem;text-align:center">
                    <div style="font-size:1.25rem;font-weight:700;color:var(--text-primary)">{{ number_format($tenant->plan->max_conversations_per_month) }}</div>
                    <div style="font-size:.75rem;color:var(--text-muted);margin-top:.125rem">Convos/month</div>
                </div>
                <div style="padding:.875rem;background:var(--page-bg);border-radius:.625rem;text-align:center">
                    <div style="font-size:1.25rem;font-weight:700;color:var(--text-primary)">
                        {{ $tenant->plan->ai_included ? 'Yes' : 'No' }}
                    </div>
                    <div style="font-size:.75rem;color:var(--text-muted);margin-top:.125rem">AI Included</div>
                </div>
            </div>
            @endif

            @if($tenant->subscription_status === 'trial' && $tenant->trial_ends_at)
            <div style="display:flex;align-items:center;gap:.625rem;padding:.875rem 1rem;background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:.75rem">
                <svg style="color:#f59e0b;flex-shrink:0" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span style="font-size:.875rem;color:var(--text-secondary)">
                    Trial ends <strong>{{ $tenant->trial_ends_at->format('M j, Y') }}</strong>
                    ({{ $tenant->trial_ends_at->diffForHumans() }})
                </span>
            </div>
            @endif
        </div>
    </div>

    {{-- Plan Cards --}}
    <div style="margin-bottom:1rem">
        <div style="font-size:1rem;font-weight:600;color:var(--text-primary);margin-bottom:1rem">Available Plans</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem">
            @foreach($plans as $plan)
            @php $isCurrent = $tenant->plan_id === $plan->id; @endphp
            <div style="padding:1.5rem;background:var(--card-bg);border:2px solid {{ $isCurrent ? 'var(--brand)' : 'var(--card-border)' }};border-radius:1rem;position:relative">
                @if($isCurrent)
                <div style="position:absolute;top:.75rem;right:.75rem">
                    <span class="badge badge-green" style="font-size:.6875rem">Current</span>
                </div>
                @endif
                <div style="font-size:1rem;font-weight:700;color:var(--text-primary);margin-bottom:.25rem">{{ $plan->name }}</div>
                <div style="margin-bottom:1.25rem">
                    <span style="font-size:1.75rem;font-weight:800;color:var(--text-primary)">${{ $plan->price_monthly }}</span>
                    <span style="font-size:.875rem;color:var(--text-muted)">/mo</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:.5rem;font-size:.8125rem;color:var(--text-secondary);margin-bottom:1.25rem">
                    <div style="display:flex;align-items:center;gap:.5rem">
                        <svg style="color:var(--brand);flex-shrink:0" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        {{ $plan->max_users }} users
                    </div>
                    <div style="display:flex;align-items:center;gap:.5rem">
                        <svg style="color:var(--brand);flex-shrink:0" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        {{ $plan->max_instances }} WhatsApp instances
                    </div>
                    <div style="display:flex;align-items:center;gap:.5rem">
                        <svg style="color:var(--brand);flex-shrink:0" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        {{ number_format($plan->max_conversations_per_month) }} convos/mo
                    </div>
                    @if($plan->ai_included)
                    <div style="display:flex;align-items:center;gap:.5rem">
                        <svg style="color:var(--brand);flex-shrink:0" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        AI auto-reply included
                    </div>
                    @endif
                </div>
                @if(!$isCurrent)
                <form method="POST" action="{{ route('payment.upgrade') }}">
                    @csrf
                    <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                    <button type="submit" class="btn btn-outline btn-sm" style="width:100%">
                        <i class="{{ $tenant->plan && $plan->price_monthly > $tenant->plan->price_monthly ? 'ri-arrow-up-circle-line' : 'ri-refresh-line' }}"></i>
                        {{ $tenant->plan && $plan->price_monthly > $tenant->plan->price_monthly ? 'Upgrade' : 'Switch' }}
                    </button>
                </form>
                @else
                <div style="text-align:center;font-size:.8125rem;color:var(--text-muted);padding:.5rem 0">
                    <i class="ri-checkbox-circle-line" style="color:var(--brand)"></i> Active plan
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    {{-- Stripe note --}}
    <div style="padding:1.25rem;background:var(--page-bg);border:1px solid var(--card-border);border-radius:.875rem;display:flex;align-items:center;gap:.875rem">
        <svg style="color:var(--text-muted);flex-shrink:0" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <div style="font-size:.8125rem;color:var(--text-muted)">
            Payments are processed securely via <strong>Stripe</strong>. Clicking Upgrade or Switch will redirect you to a secure checkout page.
        </div>
    </div>
</div>
@endsection
