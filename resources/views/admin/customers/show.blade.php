@extends('layouts.admin')

@section('title', $customer->displayNameOrPhone)

@section('breadcrumb')
    @php $panelPrefix = auth()->user()->routeNamePrefix(); @endphp
    <a href="{{ route($panelPrefix . '.customers.index') }}" style="color:var(--text-secondary);text-decoration:none">Customers</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ $customer->displayNameOrPhone }}</span>
@endsection

@section('content')
@php
    $panelPrefix = auth()->user()->routeNamePrefix();
@endphp
<div>
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ $customer->displayNameOrPhone }}</div>
            <div class="page-subtitle">Customer profile and full conversation history</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route($panelPrefix . '.customers.index') }}" class="btn btn-outline btn-sm">
                <i class="ri-arrow-left-line"></i> Back
            </a>
            <a href="{{ route($panelPrefix . '.conversations.index') }}" class="btn btn-outline btn-sm">
                <i class="ri-message-3-line"></i> Conversations
            </a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-message-2-line"></i></div>
            <div class="stat-card-value">{{ number_format($conversationStats['total'] ?? 0) }}</div>
            <div class="stat-card-label">Total Conversations</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-card-icon"><i class="ri-inbox-line"></i></div>
            <div class="stat-card-value">{{ number_format($conversationStats['open'] ?? 0) }}</div>
            <div class="stat-card-label">Open (Pool + Claimed)</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-mail-unread-line"></i></div>
            <div class="stat-card-value">{{ number_format($conversationStats['unread'] ?? 0) }}</div>
            <div class="stat-card-label">Conversations With Unread</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-card-icon"><i class="ri-robot-line"></i></div>
            <div class="stat-card-value">{{ number_format($conversationStats['ai_suspended'] ?? 0) }}</div>
            <div class="stat-card-label">AI Suspended</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:minmax(260px,320px) 1fr;gap:1rem;align-items:start">
        <div class="card">
            <div style="padding:1.5rem;text-align:center">
                <div style="width:4rem;height:4rem;border-radius:50%;background:linear-gradient(135deg,var(--brand),#059669);color:#fff;font-size:1.25rem;font-weight:700;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;text-transform:uppercase">
                    {{ strtoupper(substr($customer->displayNameOrPhone, 0, 2)) }}
                </div>
                <div style="font-weight:700;font-size:1rem;color:var(--text-primary)">
                    {{ $customer->display_name ?: 'Unknown' }}
                </div>
                <div style="font-size:.875rem;color:var(--text-muted);margin-top:.25rem;font-family:monospace">
                    {{ $customer->phone_e164 }}
                </div>
            </div>

            <div style="border-top:1px solid var(--card-border);padding:1rem 1.25rem;display:flex;flex-direction:column;gap:.625rem;font-size:.8125rem">
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">First contact</span>
                    <span>{{ $customer->created_at?->format('M j, Y') ?? '-' }}</span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Last activity</span>
                    <span>{{ $customer->updated_at?->diffForHumans() ?? '-' }}</span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Tenant ID</span>
                    <span>{{ $customer->tenant_id ?? '-' }}</span>
                </div>
            </div>
        </div>

        <div class="card" style="padding:0">
            <form method="GET">
                <div class="table-toolbar" style="border-bottom:1px solid var(--card-border);margin-bottom:0">
                    <div class="filter-input-wrap">
                        <i class="ri-search-line"></i>
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Search last message preview..." class="filter-input">
                    </div>

                    <select name="state" class="toolbar-select" onchange="this.form.submit()">
                        <option value="">All States</option>
                        <option value="pool" @selected(request('state') === 'pool')>Pool</option>
                        <option value="claimed" @selected(request('state') === 'claimed')>Claimed</option>
                        <option value="closed" @selected(request('state') === 'closed')>Closed</option>
                    </select>

                    <select name="instance_id" class="toolbar-select" onchange="this.form.submit()">
                        <option value="">All Instances</option>
                        @foreach($instanceOptions as $instance)
                            <option value="{{ $instance->id }}" @selected((string) request('instance_id') === (string) $instance->id)>{{ $instance->name }}</option>
                        @endforeach
                    </select>

                    <select name="agent_id" class="toolbar-select" onchange="this.form.submit()">
                        <option value="">All Agents</option>
                        @foreach($agentOptions as $agent)
                            <option value="{{ $agent->id }}" @selected((string) request('agent_id') === (string) $agent->id)>
                                {{ $agent->name }} ({{ $agent->role }})
                            </option>
                        @endforeach
                    </select>

                    <select name="ai_suspended" class="toolbar-select" onchange="this.form.submit()">
                        <option value="">AI Any State</option>
                        <option value="1" @selected(request('ai_suspended') === '1')>AI Suspended</option>
                        <option value="0" @selected(request('ai_suspended') === '0')>AI Active</option>
                    </select>

                    <select name="sort" class="toolbar-select" onchange="this.form.submit()">
                        <option value="last_message_desc" @selected(request('sort', 'last_message_desc') === 'last_message_desc')>Latest Message</option>
                        <option value="created_desc" @selected(request('sort') === 'created_desc')>Newest Created</option>
                        <option value="created_asc" @selected(request('sort') === 'created_asc')>Oldest Created</option>
                        <option value="state" @selected(request('sort') === 'state')>State</option>
                    </select>

                    <button type="submit" class="btn btn-outline btn-sm">Filter</button>

                    @if(request()->hasAny(['search', 'state', 'instance_id', 'agent_id', 'ai_suspended', 'sort']))
                        <a href="{{ route($panelPrefix . '.customers.show', $customer) }}" class="btn btn-ghost btn-sm">Clear</a>
                    @endif
                </div>
            </form>

            @if($conversations->isEmpty())
                <div class="empty-state" style="padding:3rem">
                    <div class="empty-state-icon"><i class="ri-message-3-line"></i></div>
                    <h4>No conversations found</h4>
                    <p>Try adjusting your filters.</p>
                </div>
            @else
                <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Instance</th>
                                <th>Agent</th>
                                <th>Unread</th>
                                <th>AI</th>
                                <th>Started</th>
                                <th>Last Message</th>
                                <th style="width:48px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($conversations as $conv)
                                @php
                                    $stateClass = match($conv->state) {
                                        'pool' => 'badge-orange',
                                        'claimed' => 'badge-blue',
                                        'closed' => 'badge-gray',
                                        default => 'badge-gray',
                                    };
                                    $stateIcon = match($conv->state) {
                                        'pool' => 'ri-inbox-line',
                                        'claimed' => 'ri-user-star-line',
                                        'closed' => 'ri-check-double-line',
                                        default => 'ri-question-line',
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <span class="badge {{ $stateClass }}">
                                            <i class="{{ $stateIcon }}"></i>
                                            {{ ucfirst($conv->state) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-size:.8125rem;color:var(--text-secondary)">
                                            {{ $conv->instance?->name ?? '-' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-size:.8125rem">
                                            {{ $conv->ownerAgent?->name ?? '-' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($conv->unread_count > 0)
                                            <span class="badge badge-purple">
                                                <i class="ri-mail-unread-line"></i>
                                                {{ $conv->unread_count }}
                                            </span>
                                        @else
                                            <span class="badge badge-gray">
                                                <i class="ri-check-line"></i>
                                                0
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($conv->ai_suspended)
                                            <span class="badge badge-gray">
                                                <i class="ri-robot-line"></i>
                                                Suspended
                                            </span>
                                        @else
                                            <span class="badge badge-green">
                                                <i class="ri-robot-line"></i>
                                                Active
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <span style="font-size:.8125rem;color:var(--text-muted)">
                                            {{ $conv->created_at?->format('M j, Y') ?? '-' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-size:.8125rem;color:var(--text-muted)">
                                            {{ $conv->last_message_at?->diffForHumans() ?? '-' }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route($panelPrefix . '.conversations.show', $conv) }}" class="action-btn" title="Open">
                                            <i class="ri-arrow-right-up-line"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($conversations->hasPages())
                    <div style="padding:1rem 1.25rem;border-top:1px solid var(--card-border)">
                        {{ $conversations->links('admin.partials.pagination') }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection
