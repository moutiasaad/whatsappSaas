@extends('layouts.admin')

@section('title', 'Customers')

@section('breadcrumb')
    <span>Customers</span>
@endsection

@section('content')

    {{-- Header --}}
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">Customers</div>
            <div class="page-subtitle">
                {{ $customers->total() }} contact{{ $customers->total() !== 1 ? 's' : '' }} across all WhatsApp instances
            </div>
        </div>
    </div>

    {{-- Search --}}
    <form method="GET">
        <div class="table-toolbar" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-lg);margin-bottom:1rem">
            <div class="filter-input-wrap">
                <i class="ri-search-line"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name or phone…" class="filter-input">
            </div>
            @if(request('search'))
                <a href="{{ route('admin.customers.index') }}" class="btn btn-ghost btn-sm">Clear</a>
            @endif
        </div>
    </form>

    {{-- Table --}}
    <div class="card" style="padding:0">
        @if($customers->isEmpty())
            <div class="empty-state" style="padding:3rem">
                <div class="empty-state-icon">
                    <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                    </svg>
                </div>
                <h4>{{ request('search') ? 'No customers match your search' : 'No customers yet' }}</h4>
                <p>{{ request('search') ? 'Try a different phone number or name' : 'Customers are created automatically when they send a WhatsApp message' }}</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Contact</th>
                            <th>Phone</th>
                            <th>Conversations</th>
                            <th>Last Seen</th>
                            <th style="width:48px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($customers as $customer)
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:.75rem">
                                    <div style="width:2rem;height:2rem;border-radius:50%;background:linear-gradient(135deg,var(--brand),#059669);color:#fff;font-size:.6875rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;text-transform:uppercase">
                                        {{ strtoupper(substr($customer->displayNameOrPhone, 0, 2)) }}
                                    </div>
                                    <span style="font-weight:500;font-size:.875rem">
                                        {{ $customer->name ?? '—' }}
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span style="font-size:.875rem;font-family:monospace;color:var(--text-secondary)">
                                    {{ $customer->phone_e164 }}
                                </span>
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:.5rem">
                                    <span style="font-weight:600;font-size:.875rem">{{ $customer->conversations_count }}</span>
                                    <span style="font-size:.75rem;color:var(--text-muted)">total</span>
                                </div>
                            </td>
                            <td>
                                <span style="font-size:.8125rem;color:var(--text-muted)">
                                    {{ $customer->updated_at->diffForHumans() }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('admin.customers.show', $customer) }}" class="action-btn" title="View">
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

            @if($customers->hasPages())
            <div style="padding:1rem 1.25rem;border-top:1px solid var(--card-border)">
                {{ $customers->links('admin.partials.pagination') }}
            </div>
            @endif
        @endif
    </div>

@endsection
