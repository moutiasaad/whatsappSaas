@extends('layouts.admin')

@section('title', 'Billing Control Plane')

@section('breadcrumb')
    <span>Billing</span>
@endsection

@section('content')
<div>
    <div style="margin-bottom:1.5rem">
        <h1 style="font-size:1.375rem;font-weight:700;color:var(--text-primary)">SaaS Billing Control Plane</h1>
        <p style="font-size:.875rem;color:var(--text-muted);margin-top:.125rem">Manage tenant plans, subscription status, and platform billing visibility.</p>
    </div>

    <div class="card" style="margin-bottom:1.5rem">
        <div class="card-header">
            <div class="card-title">Tenants</div>
            <div class="card-subtitle">{{ $tenants->count() }} total</div>
        </div>
        <div style="padding:0 1.5rem 1.5rem;overflow:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tenant</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Trial Ends</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tenants as $tenant)
                        <tr>
                            <td>{{ $tenant->name }}</td>
                            <td>{{ $tenant->plan?->name ?? 'No plan' }}</td>
                            <td>{{ ucfirst($tenant->subscription_status) }}</td>
                            <td>{{ $tenant->trial_ends_at?->format('M j, Y') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align:center;color:var(--text-muted)">No tenants found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Active Plans</div>
        </div>
        <div style="padding:0 1.5rem 1.5rem;overflow:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Plan</th>
                        <th>Monthly</th>
                        <th>Users</th>
                        <th>Instances</th>
                        <th>Conversations/month</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($plans as $plan)
                        <tr>
                            <td>{{ $plan->name }}</td>
                            <td>${{ $plan->price_monthly }}</td>
                            <td>{{ $plan->max_users }}</td>
                            <td>{{ $plan->max_instances }}</td>
                            <td>{{ number_format($plan->max_conversations_per_month) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
