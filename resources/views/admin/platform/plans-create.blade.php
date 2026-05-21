@extends('layouts.admin')

@section('title', 'Create Plan')

@section('breadcrumb')
    <span>Platform</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <a href="{{ route('super_admin.platform.plans') }}" style="color:var(--text-secondary);text-decoration:none">Subscription Plans</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>Create</span>
@endsection

@section('content')
<div>
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">Create Plan</div>
            <div class="page-subtitle">Add a new subscription plan to the global catalog</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('super_admin.platform.plans') }}" class="btn btn-outline">
                <i class="ri-arrow-left-line"></i> Back to Plans
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Plan Details</div>
                <div class="card-subtitle">Pricing, limits, Stripe IDs, AI options, and activation status</div>
            </div>
        </div>

        <form method="POST" action="{{ route('super_admin.platform.plans.store') }}" style="padding:20px;">
            @csrf
            @include('admin.platform.partials.plan-form-fields')

            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                <a href="{{ route('super_admin.platform.plans') }}" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ri-save-line"></i> Create Plan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

