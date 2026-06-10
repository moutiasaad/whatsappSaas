@extends('layouts.admin')

@section('title', __('ui.reservations.title') . ' #' . $reservation->id)

@section('breadcrumb')
@php $panelPrefix = auth()->user()->routeNamePrefix(); @endphp
<a href="{{ route($panelPrefix . '.reservations.index') }}" style="color:var(--text-secondary);text-decoration:none">{{ __('ui.reservations.breadcrumb') }}</a>
<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
<span>#{{ $reservation->id }}</span>
@endsection

@section('content')
@php
    $panelPrefix = auth()->user()->routeNamePrefix();
    $statusMap = [
        'confirmed' => ['label' => __('ui.reservations.status_confirmed'), 'class' => 'badge-green',  'icon' => 'ri-checkbox-circle-line'],
        'pending'   => ['label' => __('ui.reservations.status_pending'),   'class' => 'badge-orange', 'icon' => 'ri-time-line'],
        'cancelled' => ['label' => __('ui.reservations.status_cancelled'), 'class' => 'badge-red',    'icon' => 'ri-close-circle-line'],
        'completed' => ['label' => __('ui.reservations.status_completed'), 'class' => 'badge-gray',   'icon' => 'ri-check-double-line'],
    ];
    $s = $statusMap[$reservation->status] ?? ['label' => $reservation->status, 'class' => 'badge-gray', 'icon' => 'ri-question-line'];
    $customerInitials = strtoupper(substr($reservation->customer_name ?: $reservation->customer_phone, 0, 2));
    $slot = $reservation->slot;
@endphp

<div x-data="reservationShow()" x-init="init()" x-cloak>

<div class="page-header">
    <div class="page-header-left">
        <a href="{{ route($panelPrefix . '.reservations.index') }}" class="page-back">
            <i class="ri-arrow-left-line"></i>
        </a>
        <div>
            <div style="display:flex;align-items:center;gap:.75rem">
                <h1 class="page-title">#{{ $reservation->id }}</h1>
                <span class="badge {{ $s['class'] }}">
                    <i class="{{ $s['icon'] }}"></i> {{ $s['label'] }}
                </span>
            </div>
            <p class="page-subtitle">{{ __('ui.reservations.show_subtitle') }}</p>
        </div>
    </div>
    <div class="page-header-actions">
        @if($reservation->status === 'pending')
            <button class="btn btn-primary" @click="setStatus('confirmed')" :disabled="saving">
                <span x-show="!saving"><i class="ri-checkbox-circle-line"></i> {{ __('ui.reservations.confirm_booking') }}</span>
                <span x-show="saving" class="spinner-sm"></span>
            </button>
        @endif
        @if(in_array($reservation->status, ['confirmed', 'pending']))
            <button class="btn btn-secondary" @click="setStatus('completed')" :disabled="saving">
                <i class="ri-check-double-line"></i> {{ __('ui.reservations.mark_completed') }}
            </button>
            <button class="btn btn-secondary" @click="setStatus('cancelled')" :disabled="saving">
                <i class="ri-close-circle-line"></i> {{ __('ui.reservations.cancel_booking') }}
            </button>
        @endif
        <button class="btn btn-danger" @click="deleteModal.show = true">
            <i class="ri-delete-bin-6-line"></i> {{ __('ui.delete') }}
        </button>
    </div>
</div>

{{-- KPI row --}}
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr)">
    <div class="stat-card">
        <div class="stat-card-icon"><i class="ri-calendar-event-line"></i></div>
        <div class="stat-card-value">{{ $reservation->reservation_date->format('M j') }}</div>
        <div class="stat-card-label">{{ __('ui.reservations.reservation_date') }}</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-card-icon"><i class="ri-time-line"></i></div>
        <div class="stat-card-value">{{ \Str::substr($reservation->start_time, 0, 5) }}</div>
        <div class="stat-card-label">{{ __('ui.reservations.start_time') }}</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-card-icon"><i class="ri-user-line"></i></div>
        <div class="stat-card-value" style="font-size:1rem">{{ $reservation->customer_name ?: $reservation->customer_phone }}</div>
        <div class="stat-card-label">{{ __('ui.reservations.customer_info') }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><i class="ri-calendar-check-line"></i></div>
        <div class="stat-card-value" style="font-size:.9rem">{{ $reservation->booked_at?->format('M j, H:i') ?? '-' }}</div>
        <div class="stat-card-label">{{ __('ui.reservations.booked_at') }}</div>
    </div>
</div>

{{-- Main two-column layout --}}
<div style="display:grid;grid-template-columns:minmax(260px,320px) 1fr;gap:1rem;align-items:start">

    {{-- Left: Customer card --}}
    <div class="card">
        <div style="padding:1.5rem;text-align:center;border-bottom:1px solid var(--card-border)">
            <div style="width:4rem;height:4rem;border-radius:50%;background:linear-gradient(135deg,var(--brand),#059669);color:#fff;font-size:1.25rem;font-weight:700;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;text-transform:uppercase">
                {{ $customerInitials }}
            </div>
            <div style="font-weight:700;font-size:1rem;color:var(--text-primary)">
                {{ $reservation->customer_name ?: __('ui.reservations.unknown_customer') }}
            </div>
            <div style="font-size:.875rem;color:var(--text-muted);margin-top:.25rem;font-family:monospace">
                {{ $reservation->customer_phone }}
            </div>
        </div>

        <div style="padding:1rem 1.25rem;display:flex;flex-direction:column;gap:.625rem;font-size:.8125rem">
            @if($reservation->customer_notes)
                <div style="background:var(--page-bg);border-radius:var(--radius-md);padding:.75rem;font-size:.8125rem;color:var(--text-secondary);border:1px solid var(--card-border)">
                    <div style="font-size:.75rem;color:var(--text-muted);margin-bottom:.35rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em">
                        {{ __('ui.reservations.col_notes') }}
                    </div>
                    {{ $reservation->customer_notes }}
                </div>
            @else
                <div style="color:var(--text-muted);font-style:italic;text-align:center;padding:.5rem 0">
                    {{ __('ui.reservations.no_notes') }}
                </div>
            @endif

            @if($reservation->conversation)
                <a href="{{ route($panelPrefix . '.conversations.show', $reservation->conversation) }}"
                   class="btn btn-secondary" style="width:100%;justify-content:center">
                    <i class="ri-message-3-line"></i> {{ __('ui.reservations.view_conversation') }}
                </a>
            @endif

            @if($reservation->customer)
                <a href="{{ route($panelPrefix . '.customers.show', $reservation->customer) }}"
                   class="btn btn-secondary" style="width:100%;justify-content:center">
                    <i class="ri-user-line"></i> {{ __('ui.reservations.view_customer') }}
                </a>
            @endif
        </div>
    </div>

    {{-- Right: Booking & slot details --}}
    <div style="display:flex;flex-direction:column;gap:1rem">

        {{-- Booking details card --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ri-calendar-2-line" style="color:var(--brand)"></i> {{ __('ui.reservations.booking_info') }}</h3>
            </div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                    <div>
                        <div style="font-size:.75rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.35rem">
                            {{ __('ui.reservations.reservation_date') }}
                        </div>
                        <div style="font-weight:600;color:var(--text-primary)">
                            {{ $reservation->reservation_date->format('l, F j, Y') }}
                        </div>
                    </div>
                    <div>
                        <div style="font-size:.75rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.35rem">
                            {{ __('ui.reservations.time_slot') }}
                        </div>
                        <div style="font-weight:600;color:var(--text-primary)">
                            {{ \Str::substr($reservation->start_time, 0, 5) }} – {{ \Str::substr($reservation->end_time, 0, 5) }}
                        </div>
                    </div>
                    <div>
                        <div style="font-size:.75rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.35rem">
                            {{ __('ui.reservations.status_section') }}
                        </div>
                        <span class="badge {{ $s['class'] }}">
                            <i class="{{ $s['icon'] }}"></i> {{ $s['label'] }}
                        </span>
                    </div>
                    <div>
                        <div style="font-size:.75rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.35rem">
                            {{ __('ui.reservations.booked_at') }}
                        </div>
                        <div style="color:var(--text-secondary);font-size:.875rem">
                            {{ $reservation->booked_at?->format('M j, Y H:i') ?? '-' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Slot details card --}}
        @if($slot)
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ri-time-line" style="color:var(--brand)"></i> {{ __('ui.reservations.slot_info') }}</h3>
                <span class="badge {{ $slot->is_active ? 'badge-green' : 'badge-gray' }}">
                    {{ $slot->is_active ? __('ui.active') : __('ui.inactive') }}
                </span>
            </div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
                    <div>
                        <div style="font-size:.75rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.35rem">
                            {{ __('ui.reservations.slot_type') }}
                        </div>
                        <div style="font-weight:500">
                            @if($slot->type === 'recurring')
                                <i class="ri-repeat-line" style="color:var(--brand)"></i> {{ __('ui.reservations.type_recurring') }}
                            @else
                                <i class="ri-calendar-event-line" style="color:var(--brand)"></i> {{ __('ui.reservations.type_specific') }}
                            @endif
                        </div>
                    </div>
                    <div>
                        <div style="font-size:.75rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.35rem">
                            {{ __('ui.reservations.col_time') }}
                        </div>
                        <div style="font-weight:500">
                            {{ \Str::substr($slot->start_time, 0, 5) }} – {{ \Str::substr($slot->end_time, 0, 5) }}
                        </div>
                    </div>
                    <div>
                        <div style="font-size:.75rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.35rem">
                            {{ __('ui.reservations.slot_capacity') }}
                        </div>
                        <div style="font-weight:500">{{ $slot->max_bookings }}</div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Status change card (only for active statuses) --}}
        @if(in_array($reservation->status, ['pending', 'confirmed']))
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ri-exchange-line" style="color:var(--brand)"></i> {{ __('ui.reservations.change_status') }}</h3>
            </div>
            <div class="card-body">
                <div style="display:flex;gap:.75rem;flex-wrap:wrap">
                    @if($reservation->status === 'pending')
                    <button class="btn btn-primary" @click="setStatus('confirmed')" :disabled="saving">
                        <i class="ri-checkbox-circle-line"></i> {{ __('ui.reservations.confirm_booking') }}
                    </button>
                    @endif
                    @if($reservation->status !== 'completed')
                    <button class="btn btn-secondary" @click="setStatus('completed')" :disabled="saving">
                        <i class="ri-check-double-line"></i> {{ __('ui.reservations.mark_completed') }}
                    </button>
                    @endif
                    <button class="btn btn-secondary" style="color:var(--orange);border-color:var(--orange)" @click="setStatus('cancelled')" :disabled="saving">
                        <i class="ri-close-circle-line"></i> {{ __('ui.reservations.cancel_booking') }}
                    </button>
                </div>
                <div x-show="statusMsg" x-text="statusMsg" style="margin-top:.75rem;font-size:.875rem;color:var(--brand)"></div>
            </div>
        </div>
        @endif

    </div>{{-- end right column --}}
</div>{{-- end grid --}}

{{-- Delete modal --}}
<div class="modal-overlay" :class="deleteModal.show ? 'show' : ''" @click.self="deleteModal.show = false">
    <div class="modal-card">
        <div class="modal-header">
            <h3>{{ __('ui.reservations.delete_confirm_title') }}</h3>
            <button class="modal-close" @click="deleteModal.show = false"><i class="ri-close-line"></i></button>
        </div>
        <div class="modal-body">
            <p>{{ __('ui.reservations.delete_confirm_body') }} <strong>#{{ $reservation->id }}</strong>?</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" @click="deleteModal.show = false">{{ __('ui.cancel') }}</button>
            <button class="btn btn-danger" :disabled="deleteModal.saving" @click="confirmDelete()">
                <span x-show="deleteModal.saving" class="spinner-sm"></span>
                {{ __('ui.delete') }}
            </button>
        </div>
    </div>
</div>

</div>{{-- end x-data --}}

<script>
function reservationShow() {
    const csrf      = () => document.querySelector('meta[name=csrf-token]').content;
    const routeBase = '{{ url(auth()->user()->routeNamePrefix() . "/reservations") }}';
    const id        = {{ $reservation->id }};
    const indexUrl  = '{{ route(auth()->user()->routeNamePrefix() . ".reservations.index") }}';

    return {
        saving: false,
        statusMsg: '',
        deleteModal: { show: false, saving: false },

        init() {},

        async setStatus(status) {
            this.saving = true;
            this.statusMsg = '';
            try {
                const r = await fetch(`${routeBase}/${id}/status`, {
                    method: 'PATCH',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ status }),
                });
                if (r.ok) {
                    window.location.reload();
                } else {
                    const d = await r.json();
                    this.statusMsg = d.message ?? 'Error';
                }
            } finally {
                this.saving = false;
            }
        },

        async confirmDelete() {
            this.deleteModal.saving = true;
            try {
                const r = await fetch(`${routeBase}/${id}`, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
                });
                if (r.ok) {
                    window.location.href = indexUrl;
                }
            } finally {
                this.deleteModal.saving = false;
            }
        },
    };
}
</script>
@endsection
