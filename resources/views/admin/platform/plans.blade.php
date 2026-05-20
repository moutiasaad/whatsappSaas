@extends('layouts.admin')

@section('title', 'Subscription Plans')

@section('breadcrumb')
    <span>Platform</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>Subscription Plans</span>
@endsection

@section('content')
<div>
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">Subscription Plans</div>
            <div class="page-subtitle">Global plan catalog and tenant usage</div>
        </div>
    </div>

    <div class="card">
        <div style="overflow:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Plan</th>
                        <th>Monthly</th>
                        <th>Max Users</th>
                        <th>Max Instances</th>
                        <th>Tenants</th>
                        <th>Active</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($plans as $plan)
                        <tr>
                            <td>{{ $plan->name }}</td>
                            <td>${{ $plan->price_monthly }}</td>
                            <td>{{ $plan->max_users }}</td>
                            <td>{{ $plan->max_instances }}</td>
                            <td>{{ $plan->tenants_count }}</td>
                            <td>{{ $plan->is_active ? 'Yes' : 'No' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
