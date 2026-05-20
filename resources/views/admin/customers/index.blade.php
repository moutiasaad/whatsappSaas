@extends('layouts.admin')

@section('title', 'Customers')

@section('breadcrumb')
    <span>Customers</span>
@endsection

@section('content')
@php
    $panelPrefix = auth()->user()->routeNamePrefix();
@endphp
<div>
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">Customers</div>
            <div class="page-subtitle">
                @if($isSuperAdmin ?? false)
                    Full cross-tenant customer directory
                @else
                    Tenant customer directory and engagement overview
                @endif
            </div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route($panelPrefix . '.conversations.index') }}" class="btn btn-outline btn-sm">
                <i class="ri-message-3-line"></i> Open Conversations
            </a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-contacts-line"></i></div>
            <div class="stat-card-value">{{ number_format($stats['total'] ?? 0) }}</div>
            <div class="stat-card-label">Total Customers</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-card-icon"><i class="ri-message-2-line"></i></div>
            <div class="stat-card-value">{{ number_format($stats['with_conversations'] ?? 0) }}</div>
            <div class="stat-card-label">With Conversations</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-pulse-line"></i></div>
            <div class="stat-card-value">{{ number_format($stats['active_7d'] ?? 0) }}</div>
            <div class="stat-card-label">Active (7 days)</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-card-icon"><i class="ri-time-line"></i></div>
            <div class="stat-card-value">{{ number_format($stats['dormant_30d'] ?? 0) }}</div>
            <div class="stat-card-label">Dormant (30+ days)</div>
        </div>
    </div>

    <form method="GET">
        <div class="table-toolbar" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-lg);margin-bottom:1rem">
            <div class="filter-input-wrap">
                <i class="ri-search-line"></i>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search by customer, display name, or phone..." class="filter-input">
            </div>

            @if($isSuperAdmin ?? false)
                <select name="tenant_id" class="toolbar-select" onchange="this.form.submit()">
                    <option value="">All Tenants</option>
                    @foreach($tenants as $tenant)
                        <option value="{{ $tenant->id }}" @selected((string) request('tenant_id') === (string) $tenant->id)>
                            {{ $tenant->name }}
                        </option>
                    @endforeach
                </select>
            @endif

            <select name="instance_id" class="toolbar-select" onchange="this.form.submit()">
                <option value="">All Instances</option>
                @foreach($instances as $instance)
                    <option value="{{ $instance->id }}" @selected((string) request('instance_id') === (string) $instance->id)>
                        {{ $instance->name }}
                    </option>
                @endforeach
            </select>

            <select name="has_conversations" class="toolbar-select" onchange="this.form.submit()">
                <option value="">Any Conversation State</option>
                <option value="1" @selected(request('has_conversations') === '1')>With Conversations</option>
                <option value="0" @selected(request('has_conversations') === '0')>Without Conversations</option>
            </select>

            <input type="date" name="date_from" value="{{ request('date_from') }}" class="toolbar-select">
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="toolbar-select">

            <select name="sort" class="toolbar-select" onchange="this.form.submit()">
                <option value="activity_desc" @selected(request('sort', 'activity_desc') === 'activity_desc')>Recent Activity</option>
                <option value="activity_asc" @selected(request('sort') === 'activity_asc')>Oldest Activity</option>
                <option value="name_asc" @selected(request('sort') === 'name_asc')>Name A-Z</option>
                <option value="name_desc" @selected(request('sort') === 'name_desc')>Name Z-A</option>
                <option value="conversations_desc" @selected(request('sort') === 'conversations_desc')>Most Conversations</option>
                <option value="conversations_asc" @selected(request('sort') === 'conversations_asc')>Fewest Conversations</option>
            </select>

            <button type="submit" class="btn btn-outline btn-sm">Filter</button>

            @if(request()->hasAny(['search', 'tenant_id', 'instance_id', 'has_conversations', 'date_from', 'date_to', 'sort']))
                <a href="{{ route($panelPrefix . '.customers.index') }}" class="btn btn-ghost btn-sm">Clear</a>
            @endif
        </div>
    </form>

    <div class="card" style="padding:0">
        @if($customers->isEmpty())
            <div class="empty-state" style="padding:3rem">
                <div class="empty-state-icon"><i class="ri-contacts-line"></i></div>
                <h4>No customers found</h4>
                <p>Try adjusting your filters or wait for inbound WhatsApp activity.</p>
            </div>
        @else
            <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            @if($isSuperAdmin ?? false)
                                <th>Tenant</th>
                            @endif
                            <th>Phone</th>
                            <th>Conversations</th>
                            <th>Engagement</th>
                            <th>Last Message</th>
                            <th>Last Activity</th>
                            <th style="width:96px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($customers as $customer)
                            @php
                                $daysSinceActivity = $customer->updated_at ? $customer->updated_at->diffInDays(now()) : null;
                                $engagementClass = 'badge-gray';
                                $engagementLabel = 'Unknown';
                                $engagementIcon = 'ri-question-line';

                                if ($daysSinceActivity !== null && $daysSinceActivity <= 7) {
                                    $engagementClass = 'badge-green';
                                    $engagementLabel = 'Active';
                                    $engagementIcon = 'ri-pulse-line';
                                } elseif ($daysSinceActivity !== null && $daysSinceActivity <= 30) {
                                    $engagementClass = 'badge-orange';
                                    $engagementLabel = 'Stale';
                                    $engagementIcon = 'ri-time-line';
                                } elseif ($daysSinceActivity !== null) {
                                    $engagementClass = 'badge-gray';
                                    $engagementLabel = 'Dormant';
                                    $engagementIcon = 'ri-moon-clear-line';
                                }
                            @endphp
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:.75rem">
                                        <div style="width:2rem;height:2rem;border-radius:50%;background:linear-gradient(135deg,var(--brand),#059669);color:#fff;font-size:.6875rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;text-transform:uppercase">
                                            {{ strtoupper(substr($customer->display_name ?: $customer->phone_e164, 0, 2)) }}
                                        </div>
                                        <div style="display:flex;flex-direction:column;gap:.125rem;min-width:0">
                                            <span style="font-weight:600;font-size:.875rem;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                                {{ $customer->display_name ?: 'Unknown' }}
                                            </span>
                                            <span style="font-size:.75rem;color:var(--text-muted)">
                                                ID #{{ $customer->id }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                @if($isSuperAdmin ?? false)
                                    <td>
                                        <span class="badge badge-purple">
                                            <i class="ri-building-2-line"></i>
                                            {{ $customer->tenant?->name ?? 'N/A' }}
                                        </span>
                                    </td>
                                @endif
                                <td>
                                    <span style="font-size:.875rem;font-family:monospace;color:var(--text-secondary)">
                                        {{ $customer->phone_e164 }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-blue">
                                        <i class="ri-message-2-line"></i>
                                        {{ number_format($customer->conversations_count) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $engagementClass }}">
                                        <i class="{{ $engagementIcon }}"></i>
                                        {{ $engagementLabel }}
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:.8125rem;color:var(--text-muted)">
                                        {{ $customer->conversations_max_last_message_at ? \Illuminate\Support\Carbon::parse($customer->conversations_max_last_message_at)->diffForHumans() : '-' }}
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:.8125rem;color:var(--text-muted)">
                                        {{ $customer->updated_at?->diffForHumans() ?? '-' }}
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex;gap:.25rem;justify-content:flex-end">
                                        <a href="{{ route($panelPrefix . '.customers.show', $customer) }}" class="action-btn" title="View">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                        <a href="{{ route($panelPrefix . '.conversations.index') }}" class="action-btn" title="Open Conversations">
                                            <i class="ri-message-3-line"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($customers->hasPages())
                <div style="padding:1rem 1.25rem;border-top:1px solid var(--card-border)">
                    {{ $customers->links('admin.partials.pagination') }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
