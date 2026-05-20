@extends('layouts.admin')

@section('title', 'Edit Plan')

@section('breadcrumb')
    <span>Platform</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <a href="{{ route('super_admin.platform.plans') }}" style="color:var(--text-secondary);text-decoration:none">Subscription Plans</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ $plan->name }}</span>
@endsection

@section('content')
<div style="display:grid;grid-template-columns:1fr 300px;gap:1.25rem;align-items:start;">
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Edit Plan</div>
                <div class="card-subtitle">Pricing, limits, and availability</div>
            </div>
            <a href="{{ route('super_admin.platform.plans.show', $plan) }}" class="btn btn-outline btn-sm">
                <i class="ri-eye-line"></i> View
            </a>
        </div>

        <form method="POST" action="{{ route('super_admin.platform.plans.update', $plan) }}" style="padding:20px;">
            @csrf
            @method('PUT')

            @include('admin.platform.partials.plan-form-fields', ['plan' => $plan])

            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                <a href="{{ route('super_admin.platform.plans.show', $plan) }}" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ri-save-line"></i> Save Changes
                </button>
            </div>
        </form>
    </div>

    <div style="display:flex;flex-direction:column;gap:1rem;">
        <div class="card">
            <div class="card-header" style="padding-bottom:12px;">
                <div class="card-title">Plan Usage</div>
            </div>
            <div style="padding:0 18px 18px 18px;display:flex;flex-direction:column;gap:8px;font-size:13px;">
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:var(--text-muted);">Assigned Tenants</span>
                    <strong>{{ number_format($plan->tenants_count) }}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:var(--text-muted);">Status</span>
                    <strong>{{ $plan->is_active ? 'Active' : 'Disabled' }}</strong>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
