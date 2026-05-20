@extends('layouts.admin')

@section('title', $customer->displayNameOrPhone)

@section('breadcrumb')
    <a href="{{ route('admin.customers.index') }}" style="color:var(--text-secondary);text-decoration:none">Customers</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ $customer->displayNameOrPhone }}</span>
@endsection

@section('content')
<div style="display:grid;grid-template-columns:280px 1fr;gap:1.5rem;align-items:start">

    {{-- Left: Profile Card --}}
    <div style="display:flex;flex-direction:column;gap:1rem">
        <div class="card">
            <div style="padding:1.5rem;text-align:center">
                <div style="width:4rem;height:4rem;border-radius:50%;background:linear-gradient(135deg,var(--brand),#059669);color:#fff;font-size:1.25rem;font-weight:700;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;text-transform:uppercase">
                    {{ strtoupper(substr($customer->displayNameOrPhone, 0, 2)) }}
                </div>
                <div style="font-weight:700;font-size:1rem;color:var(--text-primary)">
                    {{ $customer->name ?? 'Unknown' }}
                </div>
                <div style="font-size:.875rem;color:var(--text-muted);margin-top:.25rem;font-family:monospace">
                    {{ $customer->phone_e164 }}
                </div>
            </div>

            <div style="border-top:1px solid var(--card-border);padding:1rem 1.25rem;display:flex;flex-direction:column;gap:.625rem;font-size:.8125rem">
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">First contact</span>
                    <span>{{ $customer->created_at->format('M j, Y') }}</span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Last activity</span>
                    <span>{{ $customer->updated_at->diffForHumans() }}</span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Total conversations</span>
                    <span style="font-weight:600">{{ $conversations->total() }}</span>
                </div>
            </div>
        </div>

        {{-- Stats --}}
        <div class="card">
            <div class="card-header" style="padding-bottom:.75rem">
                <div class="card-title">Conversation Stats</div>
            </div>
            <div style="padding:0 1.25rem 1.25rem;display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
                @php
                    $open   = $conversations->getCollection()->whereIn('state', ['pool','claimed'])->count();
                    $closed = $conversations->getCollection()->where('state','closed')->count();
                @endphp
                <div style="padding:.75rem;background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.15);border-radius:.625rem;text-align:center">
                    <div style="font-size:1.25rem;font-weight:700;color:var(--brand)">{{ $open }}</div>
                    <div style="font-size:.6875rem;color:var(--text-muted);margin-top:.125rem">Open</div>
                </div>
                <div style="padding:.75rem;background:var(--page-bg);border:1px solid var(--card-border);border-radius:.625rem;text-align:center">
                    <div style="font-size:1.25rem;font-weight:700;color:var(--text-primary)">{{ $closed }}</div>
                    <div style="font-size:.6875rem;color:var(--text-muted);margin-top:.125rem">Closed</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Right: Conversation History --}}
    <div class="card" style="padding:0">
        <div class="card-header" style="padding:1rem 1.25rem">
            <div class="card-title">Conversation History</div>
        </div>

        @if($conversations->isEmpty())
            <div class="empty-state" style="padding:3rem">
                <div class="empty-state-icon">
                    <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                </div>
                <h4>No conversations yet</h4>
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Instance</th>
                            <th>Agent</th>
                            <th>Started</th>
                            <th>Last Message</th>
                            <th style="width:48px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($conversations as $conv)
                        <tr>
                            <td>
                                @if($conv->state === 'pool')
                                    <span class="badge badge-orange">Pool</span>
                                @elseif($conv->state === 'claimed')
                                    <span class="badge badge-blue">Claimed</span>
                                @else
                                    <span class="badge badge-gray">Closed</span>
                                @endif
                            </td>
                            <td>
                                <span style="font-size:.8125rem;color:var(--text-secondary)">
                                    {{ $conv->instance->name }}
                                </span>
                            </td>
                            <td>
                                <span style="font-size:.8125rem">
                                    {{ $conv->ownerAgent?->name ?? '—' }}
                                </span>
                            </td>
                            <td>
                                <span style="font-size:.8125rem;color:var(--text-muted)">
                                    {{ $conv->created_at->format('M j, Y') }}
                                </span>
                            </td>
                            <td>
                                <span style="font-size:.8125rem;color:var(--text-muted)">
                                    {{ $conv->last_message_at?->diffForHumans() ?? '—' }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('admin.conversations.show', $conv) }}" class="action-btn" title="Open">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M5 12h14M12 5l7 7-7 7"/>
                                    </svg>
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
@endsection
