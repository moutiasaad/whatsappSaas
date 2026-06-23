@extends('layouts.admin')

@section('title', __('ui.reservations.slots_title'))

@push('styles')
<style>
    .period-picker {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .85rem;
    }
    .period-card {
        position: relative;
        display: flex;
        align-items: center;
        gap: .85rem;
        padding: 1rem;
        border: 2px solid var(--card-border);
        border-radius: var(--radius-lg, .85rem);
        background: var(--card-bg);
        cursor: pointer;
        transition: border-color .18s ease, background .18s ease, box-shadow .18s ease, transform .18s ease;
    }
    .period-card:hover {
        border-color: var(--text-muted);
        background: var(--page-bg);
    }
    .period-input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .period-chip {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: .75rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        background: var(--page-bg);
        color: var(--text-secondary);
        flex-shrink: 0;
        transition: background .18s ease, color .18s ease;
    }
    .period-meta {
        display: flex;
        flex-direction: column;
        gap: .1rem;
        min-width: 0;
    }
    .period-title {
        font-weight: 600;
        font-size: .9375rem;
        color: var(--text-primary);
        line-height: 1.2;
    }
    .period-hint {
        font-size: .75rem;
        color: var(--text-muted);
        line-height: 1.2;
        font-variant-numeric: tabular-nums;
    }
    .period-check {
        position: absolute;
        top: .55rem;
        right: .55rem;
        width: 1.25rem;
        height: 1.25rem;
        border-radius: 50%;
        color: #fff;
        font-size: .8rem;
        line-height: 1;
        display: none;
        align-items: center;
        justify-content: center;
        box-shadow: 0 1px 2px rgba(0,0,0,.12);
    }
    /* Morning accent (orange) */
    .period-card[data-accent="morning"] .period-chip { color: #f97316; }
    .period-card[data-accent="morning"]:hover { border-color: #fdba74; background: rgba(249,115,22,.04); }
    .period-card[data-accent="morning"].is-active {
        border-color: #f97316;
        background: rgba(249,115,22,.07);
        box-shadow: 0 0 0 3px rgba(249,115,22,.12);
    }
    .period-card[data-accent="morning"].is-active .period-chip { background: rgba(249,115,22,.14); color: #ea580c; }
    .period-card[data-accent="morning"].is-active .period-check { background: #f97316; display: inline-flex; }
    /* Afternoon accent (brand) */
    .period-card[data-accent="afternoon"] .period-chip { color: var(--brand); }
    .period-card[data-accent="afternoon"]:hover { border-color: var(--brand-light, #6ee7b7); background: rgba(16,185,129,.04); }
    .period-card[data-accent="afternoon"].is-active {
        border-color: var(--brand);
        background: rgba(16,185,129,.07);
        box-shadow: 0 0 0 3px rgba(16,185,129,.14);
    }
    .period-card[data-accent="afternoon"].is-active .period-chip { background: rgba(16,185,129,.14); color: var(--brand-dark, #059669); }
    .period-card[data-accent="afternoon"].is-active .period-check { background: var(--brand); display: inline-flex; }

    [dir="rtl"] .period-check { left: .55rem; right: auto; }
</style>
@endpush

@section('content')
<div x-data="slotsPage()" x-init="init()" x-cloak>

    <div class="page-header">
        <div class="page-header-left">
            <a href="{{ route(auth()->user()->routeNamePrefix().'.reservations.index') }}" class="btn btn-outline btn-sm">
                <i class="ri-arrow-left-line"></i> {{ __('ui.back') }}
            </a>
            <div>
                <h1 class="page-title">{{ __('ui.reservations.slots_title') }}</h1>
                <p class="page-subtitle">{{ __('ui.reservations.slots_subtitle') }}</p>
            </div>
        </div>
        <div class="page-header-actions">
            <button class="btn btn-primary" @click="openAdd()">
                <i class="ri-add-line"></i> {{ __('ui.reservations.add_slot') }}
            </button>
        </div>
    </div>

    {{-- Recurring slots --}}
    <div class="card" style="padding:0;margin-bottom:1.5rem">
        <div style="padding:.875rem 1.25rem;border-bottom:1px solid var(--card-border);display:flex;align-items:center;gap:.5rem">
            <i class="ri-repeat-line" style="color:var(--brand)"></i>
            <span style="font-weight:600;font-size:.9375rem">{{ __('ui.reservations.recurring_slots') }}</span>
        </div>

        <div x-show="loading" class="spinner-wrap" style="min-height:120px">
            <div class="spinner" style="margin:0 auto"></div>
        </div>

        <div x-show="!loading">
            <div x-show="recurringSlots.length === 0" class="empty-state" style="padding:2rem">
                <div class="empty-state-icon"><i class="ri-repeat-line"></i></div>
                <h4>{{ __('ui.reservations.no_recurring') }}</h4>
            </div>
            <div x-show="recurringSlots.length > 0" class="table-wrap" style="border:none;border-radius:0;box-shadow:none">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.reservations.col_day') }}</th>
                            <th>{{ __('ui.reservations.col_period') }}</th>
                            <th>{{ __('ui.reservations.col_time') }}</th>
                            <th>{{ __('ui.reservations.col_max') }}</th>
                            <th>{{ __('ui.reservations.col_active') }}</th>
                            <th style="width:96px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="slot in recurringSlots" :key="slot.id">
                            <tr>
                                <td x-text="dayName(slot.day_of_week)" class="font-medium"></td>
                                <td>
                                    <span class="badge" :class="slot.period === 'morning' ? 'badge-orange' : 'badge-blue'">
                                        <i :class="slot.period === 'morning' ? 'ri-sun-line' : 'ri-moon-line'"></i>
                                        <span x-text="slot.period === 'morning' ? '{{ __('ui.reservations.period_morning') }}' : '{{ __('ui.reservations.period_afternoon') }}'"></span>
                                    </span>
                                </td>
                                <td x-text="slot.start_time.slice(0,5) + ' — ' + slot.end_time.slice(0,5)"></td>
                                <td x-text="slot.max_bookings"></td>
                                <td>
                                    <span class="badge" :class="slot.is_active ? 'badge-green' : 'badge-gray'"
                                          x-text="slot.is_active ? '{{ __('ui.active') }}' : '{{ __('ui.inactive') }}'"></span>
                                </td>
                                <td>
                                    <div style="display:flex;gap:.25rem;justify-content:flex-end">
                                        <button class="action-btn" title="{{ __('ui.edit') }}" @click="editSlot(slot)">
                                            <i class="ri-edit-line" style="color:var(--brand)"></i>
                                        </button>
                                        <button class="action-btn danger" title="{{ __('ui.delete') }}"
                                                @click="deleteSlotModal = {show:true, id:slot.id, label: dayName(slot.day_of_week)+' '+slot.start_time.slice(0,5), saving:false}">
                                            <i class="ri-delete-bin-6-line"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Specific date slots --}}
    <div class="card" style="padding:0">
        <div style="padding:.875rem 1.25rem;border-bottom:1px solid var(--card-border);display:flex;align-items:center;gap:.5rem">
            <i class="ri-calendar-event-line" style="color:var(--brand)"></i>
            <span style="font-weight:600;font-size:.9375rem">{{ __('ui.reservations.specific_slots') }}</span>
        </div>

        <div x-show="loading" class="spinner-wrap" style="min-height:120px">
            <div class="spinner" style="margin:0 auto"></div>
        </div>

        <div x-show="!loading">
            <div x-show="specificSlots.length === 0" class="empty-state" style="padding:2rem">
                <div class="empty-state-icon"><i class="ri-calendar-line"></i></div>
                <h4>{{ __('ui.reservations.no_specific') }}</h4>
            </div>
            <div x-show="specificSlots.length > 0" class="table-wrap" style="border:none;border-radius:0;box-shadow:none">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.reservations.col_date') }}</th>
                            <th>{{ __('ui.reservations.col_period') }}</th>
                            <th>{{ __('ui.reservations.col_time') }}</th>
                            <th>{{ __('ui.reservations.col_max') }}</th>
                            <th>{{ __('ui.reservations.col_active') }}</th>
                            <th style="width:96px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="slot in specificSlots" :key="slot.id">
                            <tr>
                                <td x-text="slot.specific_date" class="font-medium"></td>
                                <td>
                                    <span class="badge" :class="slot.period === 'morning' ? 'badge-orange' : 'badge-blue'">
                                        <i :class="slot.period === 'morning' ? 'ri-sun-line' : 'ri-moon-line'"></i>
                                        <span x-text="slot.period === 'morning' ? '{{ __('ui.reservations.period_morning') }}' : '{{ __('ui.reservations.period_afternoon') }}'"></span>
                                    </span>
                                </td>
                                <td x-text="slot.start_time.slice(0,5) + ' — ' + slot.end_time.slice(0,5)"></td>
                                <td x-text="slot.max_bookings"></td>
                                <td>
                                    <span class="badge" :class="slot.is_active ? 'badge-green' : 'badge-gray'"
                                          x-text="slot.is_active ? '{{ __('ui.active') }}' : '{{ __('ui.inactive') }}'"></span>
                                </td>
                                <td>
                                    <div style="display:flex;gap:.25rem;justify-content:flex-end">
                                        <button class="action-btn" title="{{ __('ui.edit') }}" @click="editSlot(slot)">
                                            <i class="ri-edit-line" style="color:var(--brand)"></i>
                                        </button>
                                        <button class="action-btn danger" title="{{ __('ui.delete') }}"
                                                @click="deleteSlotModal = {show:true, id:slot.id, label: slot.specific_date+' '+slot.start_time.slice(0,5), saving:false}">
                                            <i class="ri-delete-bin-6-line"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Add / Edit modal --}}
    <div class="modal-overlay" :class="addModal.show ? 'show' : ''" @click.self="closeModal()">
        <div class="modal-card" style="max-width:480px">
            <div class="modal-header">
                <h3 x-text="addModal.id ? '{{ __('ui.reservations.edit_slot') }}' : '{{ __('ui.reservations.add_slot') }}'"></h3>
                <button class="modal-close" @click="closeModal()"><i class="ri-close-line"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group" x-show="!addModal.id">
                    <label class="form-label">{{ __('ui.reservations.slot_type') }}</label>
                    <select class="form-control" x-model="addModal.type">
                        <option value="recurring">{{ __('ui.reservations.type_recurring') }}</option>
                        <option value="specific">{{ __('ui.reservations.type_specific') }}</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('ui.reservations.period') }}</label>
                    <div class="period-picker">
                        <label class="period-card" :class="{ 'is-active': addModal.period === 'morning' }" data-accent="morning">
                            <input type="radio" x-model="addModal.period" value="morning" class="period-input">
                            <span class="period-check"><i class="ri-check-line"></i></span>
                            <span class="period-chip"><i class="ri-sun-line"></i></span>
                            <span class="period-meta">
                                <span class="period-title">{{ __('ui.reservations.period_morning') }}</span>
                                <span class="period-hint">06:00 – 12:00</span>
                            </span>
                        </label>
                        <label class="period-card" :class="{ 'is-active': addModal.period === 'afternoon' }" data-accent="afternoon">
                            <input type="radio" x-model="addModal.period" value="afternoon" class="period-input">
                            <span class="period-check"><i class="ri-check-line"></i></span>
                            <span class="period-chip"><i class="ri-moon-line"></i></span>
                            <span class="period-meta">
                                <span class="period-title">{{ __('ui.reservations.period_afternoon') }}</span>
                                <span class="period-hint">12:00 – 18:00</span>
                            </span>
                        </label>
                    </div>
                </div>
                <div class="form-group" x-show="addModal.type === 'recurring'">
                    <label class="form-label">{{ __('ui.reservations.day_of_week') }}</label>
                    <select class="form-control" x-model="addModal.day_of_week">
                        <option value="0">{{ __('ui.reservations.sunday') }}</option>
                        <option value="1">{{ __('ui.reservations.monday') }}</option>
                        <option value="2">{{ __('ui.reservations.tuesday') }}</option>
                        <option value="3">{{ __('ui.reservations.wednesday') }}</option>
                        <option value="4">{{ __('ui.reservations.thursday') }}</option>
                        <option value="5">{{ __('ui.reservations.friday') }}</option>
                        <option value="6">{{ __('ui.reservations.saturday') }}</option>
                    </select>
                </div>
                <div class="form-group" x-show="addModal.type === 'specific'">
                    <label class="form-label">{{ __('ui.reservations.specific_date') }}</label>
                    <input type="date" class="form-control" x-model="addModal.specific_date">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.reservations.start_time') }}</label>
                        <input type="time" class="form-control" x-model="addModal.start_time">
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.reservations.end_time') }}</label>
                        <input type="time" class="form-control" x-model="addModal.end_time">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('ui.reservations.max_bookings') }}</label>
                    <input type="number" class="form-control" min="1" max="999" x-model="addModal.max_bookings">
                </div>
                <div class="form-group">
                    <label class="toggle-label">
                        <input type="checkbox" x-model="addModal.is_active">
                        <span class="toggle-text">{{ __('ui.reservations.slot_active') }}</span>
                    </label>
                </div>
                <p x-show="addModal.error" class="form-error" x-text="addModal.error"></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" @click="closeModal()">{{ __('ui.cancel') }}</button>
                <button class="btn btn-primary" :disabled="addModal.saving" @click="saveSlot()">
                    <span x-show="addModal.saving" class="spinner-sm"></span>
                    {{ __('ui.save') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Delete modal --}}
    <div class="modal-overlay" :class="deleteSlotModal.show ? 'show' : ''" @click.self="deleteSlotModal.show=false">
        <div class="modal-card">
            <div class="modal-header">
                <h3>{{ __('ui.reservations.delete_slot_confirm') }}</h3>
                <button class="modal-close" @click="deleteSlotModal.show=false"><i class="ri-close-line"></i></button>
            </div>
            <div class="modal-body">
                <p x-text="deleteSlotModal.label"></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" @click="deleteSlotModal.show=false">{{ __('ui.cancel') }}</button>
                <button class="btn btn-danger" :disabled="deleteSlotModal.saving" @click="confirmDeleteSlot()">
                    <span x-show="deleteSlotModal.saving" class="spinner-sm"></span>{{ __('ui.delete') }}
                </button>
            </div>
        </div>
    </div>

</div>{{-- end x-data --}}

<script>
function slotsPage() {
    const csrf = () => document.querySelector('meta[name=csrf-token]').content;
    const apiBase = '{{ route(auth()->user()->routeNamePrefix().'.reservations.slots') }}';

    return {
        loading: false,
        slots: [],
        addModal: { show: false, id: null, type: 'recurring', period: 'morning', day_of_week: 1, specific_date: '', start_time: '09:00', end_time: '10:00', max_bookings: 1, is_active: true, error: '', saving: false },
        deleteSlotModal: { show: false, id: null, label: '', saving: false },

        get recurringSlots() { return this.slots.filter(s => s.type === 'recurring'); },
        get specificSlots()  { return this.slots.filter(s => s.type === 'specific'); },

        async init() { await this.loadSlots(); },

        openAdd() {
            this.addModal = { show: true, id: null, type: 'recurring', period: 'morning', day_of_week: 1,
                specific_date: '', start_time: '09:00', end_time: '10:00', max_bookings: 1,
                is_active: true, error: '', saving: false };
        },

        async loadSlots() {
            this.loading = true;
            try {
                const r = await fetch(apiBase, { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                this.slots = d.data ?? [];
            } finally {
                this.loading = false;
            }
        },

        editSlot(slot) {
            this.addModal = { show: true, id: slot.id, type: slot.type, period: slot.period ?? 'morning',
                day_of_week: slot.day_of_week ?? 1, specific_date: slot.specific_date ?? '',
                start_time: slot.start_time?.slice(0,5) ?? '09:00', end_time: slot.end_time?.slice(0,5) ?? '10:00',
                max_bookings: slot.max_bookings, is_active: slot.is_active, error: '', saving: false };
        },

        closeModal() { this.addModal.show = false; },

        async saveSlot() {
            this.addModal.saving = true;
            this.addModal.error = '';
            const payload = {
                type: this.addModal.type, period: this.addModal.period,
                day_of_week: this.addModal.day_of_week, specific_date: this.addModal.specific_date,
                start_time: this.addModal.start_time, end_time: this.addModal.end_time,
                max_bookings: parseInt(this.addModal.max_bookings), is_active: this.addModal.is_active,
            };
            const url = this.addModal.id ? `${apiBase}/${this.addModal.id}` : apiBase;
            const method = this.addModal.id ? 'PUT' : 'POST';
            try {
                const r = await fetch(url, {
                    method, headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify(payload),
                });
                const d = await r.json();
                if (!r.ok) { this.addModal.error = d.message ?? 'Error'; return; }
                this.addModal.show = false;
                await this.loadSlots();
            } finally {
                this.addModal.saving = false;
            }
        },

        async confirmDeleteSlot() {
            this.deleteSlotModal.saving = true;
            try {
                const r = await fetch(`${apiBase}/${this.deleteSlotModal.id}`,
                    { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() } });
                if (r.ok) { this.deleteSlotModal.show = false; await this.loadSlots(); }
            } finally {
                this.deleteSlotModal.saving = false;
            }
        },

        dayName(dow) {
            return ['{{ __("ui.reservations.sunday") }}','{{ __("ui.reservations.monday") }}','{{ __("ui.reservations.tuesday") }}',
                    '{{ __("ui.reservations.wednesday") }}','{{ __("ui.reservations.thursday") }}','{{ __("ui.reservations.friday") }}',
                    '{{ __("ui.reservations.saturday") }}'][dow] ?? dow;
        },
    };
}
</script>
@endsection
