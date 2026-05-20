@extends('layouts.admin')

@section('title', $tenant->name)

@section('breadcrumb')
    <span>Platform</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <a href="{{ route('super_admin.platform.tenants') }}" style="color:var(--text-secondary);text-decoration:none">Tenants</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ $tenant->name }}</span>
@endsection

@section('content')
<div>
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ $tenant->name }}</div>
            <div class="page-subtitle">Tenant slug: {{ $tenant->slug }}</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('super_admin.platform.tenants') }}" class="btn btn-outline">
                <i class="ri-arrow-left-line"></i> Back
            </a>
            <a href="{{ route('super_admin.platform.tenants.edit', $tenant) }}" class="btn btn-primary">
                <i class="ri-pencil-line"></i> Edit Tenant
            </a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-team-line"></i></div>
            <div class="stat-card-value">{{ number_format($tenant->users_count) }}</div>
            <div class="stat-card-label">Users</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-card-icon"><i class="ri-group-line"></i></div>
            <div class="stat-card-value">{{ number_format($tenant->teams_count) }}</div>
            <div class="stat-card-label">Teams</div>
        </div>
        <div class="stat-card purple">
            <div class="stat-card-icon"><i class="ri-whatsapp-line"></i></div>
            <div class="stat-card-value">{{ number_format($tenant->instances_count) }}</div>
            <div class="stat-card-label">Instances</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-card-icon"><i class="ri-message-3-line"></i></div>
            <div class="stat-card-value">{{ number_format($tenant->conversations_count) }}</div>
            <div class="stat-card-label">Conversations</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:1.25rem;align-items:start;">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Tenant Information</div>
            </div>
            <div style="padding:20px;">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Plan</label>
                        <div class="form-control" style="display:flex;align-items:center;">
                            {{ $tenant->plan?->name ?? 'No plan' }}
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Subscription Status</label>
                        <div style="height:40px;display:flex;align-items:center;">
                            @php
                                $statusBadge = match($tenant->subscription_status) {
                                    'active' => 'badge-green',
                                    'trial' => 'badge-orange',
                                    'suspended' => 'badge-red',
                                    default => 'badge-gray',
                                };
                            @endphp
                            <span class="badge {{ $statusBadge }}">{{ ucfirst($tenant->subscription_status) }}</span>
                            @if(!$tenant->is_active)
                                <span class="badge badge-gray" style="margin-left:6px;">Inactive</span>
                            @endif
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Trial Ends At</label>
                        <div class="form-control" style="display:flex;align-items:center;">
                            {{ $tenant->trial_ends_at?->format('M j, Y') ?? 'Not set' }}
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stripe Customer ID</label>
                        <div class="form-control" style="display:flex;align-items:center;font-family:monospace;">
                            {{ $tenant->stripe_id ?: 'Not set' }}
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label">Settings JSON</label>
                    <textarea class="form-control" rows="8" readonly>{{ $tenant->settings ? json_encode($tenant->settings, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) : '{}' }}</textarea>
                </div>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:1rem;">
            <div class="card">
                <div class="card-header" style="padding-bottom:12px;">
                    <div class="card-title">Meta</div>
                </div>
                <div style="padding:0 18px 18px 18px;display:flex;flex-direction:column;gap:8px;font-size:13px;">
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-muted);">Created</span>
                        <strong>{{ $tenant->created_at?->format('M j, Y') }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-muted);">Last Updated</span>
                        <strong>{{ $tenant->updated_at?->diffForHumans() }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-muted);">Customers</span>
                        <strong>{{ number_format($tenant->customers_count) }}</strong>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="padding-bottom:12px;">
                    <div class="card-title">Recent Users</div>
                </div>
                <div style="padding:0 18px 18px 18px;display:flex;flex-direction:column;gap:8px;">
                    @forelse($tenant->users as $user)
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 10px;background:var(--page-bg);border-radius:8px;">
                            <div style="min-width:0;">
                                <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $user->name }}</div>
                                <div style="font-size:12px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $user->email }}</div>
                            </div>
                            <span class="badge badge-gray">{{ ucfirst($user->role) }}</span>
                        </div>
                    @empty
                        <div style="font-size:13px;color:var(--text-muted);">No users yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
