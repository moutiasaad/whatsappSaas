@extends('layouts.admin')

@section('title', __('ui.reservations.slots_title'))

@section('content')
<div x-data="slotsPage()" x-init="init()">
<div class="content-area">

    <div class="page-header">
        <div class="page-header-left">
            <a href="{{ route(auth()->user()->routeNamePrefix().'.reservations.index') }}" class="page-back">
                <i class="ri-arrow-left-line"></i>
            </a>
            <div>
                <h1 class="page-title">{{ __('ui.reservations.slots_title') }}</h1>
                <p class="page-subtitle">{{ __('ui.reservations.slots_subtitle') }}</p>
            </div>
        </div>
        <div class="page-header-actions">
            <button class="btn btn-primary" @click="addModal.show = true">
                <i class="ri-add-line"></i> {{ __('ui.reservations.add_slot') }}
            </button>
        </div>
    </div>

    {{-- Recurring slots --}}
    <div class="table-card" style="margin-bottom:1.5rem">
        <div style="padding:.875rem 1.25rem;border-bottom:1px solid var(--card-border);display:flex;align-items:center;gap:.5rem">
            <i class="ri-repeat-line" style="color:var(--brand)"></i>
            <span style="font-weight:600;font-size:.9375rem">{{ __('ui.reservations.recurring_slots') }}</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('ui.reservations.col_day') }}</th>
                    <th>{{ __('ui.reservations.col_time') }}</th>
                    <th>{{ __('ui.reservations.col_max') }}</th>
                    <th>{{ __('ui.reservations.col_active') }}</th>
                    <th>{{ __('ui.reservations.col_actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="slot in recurringSlots" :key="slot.id">
                    <tr>
                        <td x-text="dayName(slot.day_of_week)" class="font-medium"></td>
                        <td x-text="slot.start_time.slice(0,5) + ' — ' + slot.end_time.slice(0,5)"></td>
                        <td x-text="slot.max_bookings"></td>
                        <td>
                            <span class="badge" :class="slot.is_active ? 'badge-green' : 'badge-gray'"
                                  x-text="slot.is_active ? '{{ __('ui.active') }}' : '{{ __('ui.inactive') }}'"></span>
                        </td>
                        <td>
                            <div style="display:flex;gap:4px;align-items:center">
                                <button class="action-btn" title="{{ __('ui.edit') }}" @click="editSlot(slot)">
                                    <i class="ri-edit-line" style="color:var(--brand)"></i>
                                </button>
                                <button class="action-btn danger" title="{{ __('ui.delete') }}"
                                        @click="deleteSlotModal = {show:true, id:slot.id, label: dayName(slot.day_of_week)+' '+slot.start_time.slice(0,5)}">
                                    <i class="ri-delete-bin-6-line"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
                <tr x-show="!loading && recurringSlots.length === 0">
                    <td colspan="5" class="empty-state">
                        <i class="ri-repeat-line"></i>
                        <p>{{ __('ui.reservations.no_recurring') }}</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Specific date slots --}}
    <div class="table-card">
        <div style="padding:.875rem 1.25rem;border-bottom:1px solid var(--card-border);display:flex;align-items:center;gap:.5rem">
            <i class="ri-calendar-event-line" style="color:var(--brand)"></i>
            <span style="font-weight:600;font-size:.9375rem">{{ __('ui.reservations.specific_slots') }}</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('ui.reservations.col_date') }}</th>
                    <th>{{ __('ui.reservations.col_time') }}</th>
                    <th>{{ __('ui.reservations.col_max') }}</th>
                    <th>{{ __('ui.reservations.col_active') }}</th>
                    <th>{{ __('ui.reservations.col_actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="slot in specificSlots" :key="slot.id">
                    <tr>
                        <td x-text="slot.specific_date" class="font-medium"></td>
                        <td x-text="slot.start_time.slice(0,5) + ' — ' + slot.end_time.slice(0,5)"></td>
                        <td x-text="slot.max_bookings"></td>
                        <td>
                            <span class="badge" :class="slot.is_active ? 'badge-green' : 'badge-gray'"
                                  x-text="slot.is_active ? '{{ __('ui.active') }}' : '{{ __('ui.inactive') }}'"></span>
                        </td>
                        <td>
                            <div style="display:flex;gap:4px;align-items:center">
                                <button class="action-btn" title="{{ __('ui.edit') }}" @click="editSlot(slot)">
                                    <i class="ri-edit-line" style="color:var(--brand)"></i>
                                </button>
                                <button class="action-btn danger" title="{{ __('ui.delete') }}"
                                        @click="deleteSlotModal = {show:true, id:slot.id, label: slot.specific_date+' '+slot.start_time.slice(0,5)}">
                                    <i class="ri-delete-bin-6-line"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
                <tr x-show="!loading && specificSlots.length === 0">
                    <td colspan="5" class="empty-state">
                        <i class="ri-calendar-line"></i>
                        <p>{{ __('ui.reservations.no_specific') }}</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>{{-- end .content-area --}}

{{-- Add / Edit modal --}}
<div class="modal-overlay" x-show="addModal.show" x-cloak @click.self="closeModal()">
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
                <input type="hidden" name="_active_hidden" value="0">
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
<div class="modal-overlay" x-show="deleteSlotModal.show" x-cloak @click.self="deleteSlotModal.show=false">
    <div class="modal-card">
        <div class="modal-header">
            <h3>{{ __('ui.reservations.delete_slot_confirm') }}</h3>
            <button class="modal-close" @click="deleteSlotModal.show=false"><i class="ri-close-line"></i></button>
        </div>
        <div class="modal-body"><p x-text="deleteSlotModal.label"></p></div>
        <div class="modal-footer">
            <button class="btn btn-secondary" @click="deleteSlotModal.show=false">{{ __('ui.cancel') }}</button>
            <button class="btn btn-danger" :disabled="deleteSlotModal.saving" @click="confirmDeleteSlot()">
                <span x-show="deleteSlotModal.saving" class="spinner-sm"></span>{{ __('ui.delete') }}
            </button>
        </div>
    </div>
</div>

<script>
function slotsPage() {
    const csrf = () => document.querySelector('meta[name=csrf-token]').content;
    const apiBase = '{{ route(auth()->user()->routeNamePrefix().'.reservations.slots') }}';

    return {
        loading: false,
        slots: [],
        addModal: { show: false, id: null, type: 'recurring', day_of_week: 1, specific_date: '', start_time: '09:00', end_time: '10:00', max_bookings: 1, is_active: true, error: '', saving: false },
        deleteSlotModal: { show: false, id: null, label: '', saving: false },

        get recurringSlots() { return this.slots.filter(s => s.type === 'recurring'); },
        get specificSlots()  { return this.slots.filter(s => s.type === 'specific'); },

        async init() { await this.loadSlots(); },

        async loadSlots() {
            this.loading = true;
            const r = await fetch(apiBase, { headers: { 'Accept': 'application/json' } });
            const d = await r.json();
            this.slots = d.data ?? [];
            this.loading = false;
        },

        editSlot(slot) {
            this.addModal = { show: true, id: slot.id, type: slot.type, day_of_week: slot.day_of_week ?? 1,
                specific_date: slot.specific_date ?? '', start_time: slot.start_time?.slice(0,5) ?? '09:00',
                end_time: slot.end_time?.slice(0,5) ?? '10:00', max_bookings: slot.max_bookings, is_active: slot.is_active,
                error: '', saving: false };
        },

        closeModal() { this.addModal.show = false; },

        async saveSlot() {
            this.addModal.saving = true;
            this.addModal.error = '';
            const payload = {
                type: this.addModal.type, day_of_week: this.addModal.day_of_week,
                specific_date: this.addModal.specific_date, start_time: this.addModal.start_time,
                end_time: this.addModal.end_time, max_bookings: parseInt(this.addModal.max_bookings),
                is_active: this.addModal.is_active,
            };
            const url = this.addModal.id ? `${apiBase}/${this.addModal.id}` : apiBase;
            const method = this.addModal.id ? 'PUT' : 'POST';
            const r = await fetch(url, {
                method, headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify(payload),
            });
            const d = await r.json();
            if (!r.ok) { this.addModal.error = d.message ?? 'Error'; this.addModal.saving = false; return; }
            this.addModal.show = false;
            await this.loadSlots();
            this.addModal.saving = false;
        },

        async confirmDeleteSlot() {
            this.deleteSlotModal.saving = true;
            const r = await fetch(`${apiBase}/${this.deleteSlotModal.id}`,
                { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() } });
            if (r.ok) { this.deleteSlotModal.show = false; await this.loadSlots(); }
            this.deleteSlotModal.saving = false;
        },

        dayName(dow) {
            return ['{{ __("ui.reservations.sunday") }}','{{ __("ui.reservations.monday") }}','{{ __("ui.reservations.tuesday") }}',
                    '{{ __("ui.reservations.wednesday") }}','{{ __("ui.reservations.thursday") }}','{{ __("ui.reservations.friday") }}',
                    '{{ __("ui.reservations.saturday") }}'][dow] ?? dow;
        },
    };
}
</script>
</div>{{-- end x-data wrapper --}}
@endsection
