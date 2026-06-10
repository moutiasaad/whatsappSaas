@extends('layouts.admin')

@section('title', __('ui.reservations.settings_title'))

@section('content')
<div class="content-area">

    <div class="page-header">
        <div class="page-header-left">
            <a href="{{ route(auth()->user()->routeNamePrefix().'.reservations.index') }}" class="btn btn-outline btn-sm">
                <i class="ri-arrow-left-line"></i> {{ __('ui.back') }}
            </a>
            <div>
                <h1 class="page-title">{{ __('ui.reservations.settings_title') }}</h1>
                <p class="page-subtitle">{{ __('ui.reservations.settings_subtitle') }}</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route(auth()->user()->routeNamePrefix().'.reservations.settings.save') }}">
        @csrf
        @method('PUT')

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">

            {{-- General --}}
            <div class="card">
                <div class="card-header"><h3 class="card-title">{{ __('ui.reservations.general_settings') }}</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.reservations.service_name') }} *</label>
                        <input type="text" name="service_name" class="form-control @error('service_name') error @enderror"
                               value="{{ old('service_name', $setting->service_name ?? 'Appointment Booking') }}" required>
                        @error('service_name')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">{{ __('ui.reservations.linked_instance') }}</label>
                        <select name="instance_id" class="form-control">
                            <option value="">{{ __('ui.reservations.no_instance') }}</option>
                            @foreach($instances as $inst)
                                <option value="{{ $inst->id }}" {{ old('instance_id', $setting->instance_id ?? '') == $inst->id ? 'selected' : '' }}>
                                    {{ $inst->name }} ({{ $inst->phone_number ?? '—' }})
                                </option>
                            @endforeach
                        </select>
                        <span class="form-hint">{{ __('ui.reservations.instance_hint') }}</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">{{ __('ui.reservations.trigger_keywords') }}</label>
                        <input type="text" name="trigger_keywords" class="form-control"
                               value="{{ old('trigger_keywords', implode(', ', $setting->trigger_keywords ?? ['حجز', 'book', 'booking', 'réservation'])) }}">
                        <span class="form-hint">{{ __('ui.reservations.trigger_keywords_hint') }}</span>
                    </div>

                    <div class="form-group">
                        <input type="hidden" name="collect_notes" value="0">
                        <label class="toggle-label">
                            <input type="checkbox" name="collect_notes" value="1"
                                   {{ old('collect_notes', $setting->collect_notes ?? false) ? 'checked' : '' }}>
                            <span class="toggle-text">{{ __('ui.reservations.collect_notes') }}</span>
                        </label>
                        <span class="form-hint" style="display:block;margin-top:.25rem">{{ __('ui.reservations.collect_notes_hint') }}</span>
                    </div>

                    <div class="form-group">
                        <input type="hidden" name="is_active" value="0">
                        <label class="toggle-label">
                            <input type="checkbox" name="is_active" value="1"
                                   {{ old('is_active', $setting->is_active ?? true) ? 'checked' : '' }}>
                            <span class="toggle-text">{{ __('ui.reservations.module_active') }}</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- Bot messages --}}
            <div class="card">
                <div class="card-header"><h3 class="card-title">{{ __('ui.reservations.bot_messages') }}</h3></div>
                <div class="card-body">
                    @foreach([
                        'welcome_message'      => __('ui.reservations.welcome_message'),
                        'select_date_message'  => __('ui.reservations.select_date_message'),
                        'select_slot_message'  => __('ui.reservations.select_slot_message'),
                        'ask_name_message'     => __('ui.reservations.ask_name_message'),
                        'ask_notes_message'    => __('ui.reservations.ask_notes_message'),
                        'confirmation_message' => __('ui.reservations.confirmation_message'),
                        'cancellation_message' => __('ui.reservations.cancellation_message'),
                        'no_slots_message'     => __('ui.reservations.no_slots_message'),
                    ] as $field => $label)
                        <div class="form-group">
                            <label class="form-label">{{ $label }}</label>
                            <textarea name="{{ $field }}" class="form-control" rows="2">{{ old($field, $setting->$field ?? '') }}</textarea>
                        </div>
                    @endforeach
                    <p class="form-hint">
                        {{ __('ui.reservations.confirmation_placeholders') }}:
                        <code>{date}</code>, <code>{start}</code>, <code>{end}</code>, <code>{name}</code>, <code>{id}</code>
                    </p>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:.75rem;margin-top:1.5rem">
            <button type="submit" class="btn btn-primary">
                <i class="ri-save-line"></i> {{ __('ui.save') }}
            </button>
            <a href="{{ route(auth()->user()->routeNamePrefix().'.reservations.index') }}" class="btn btn-secondary">
                {{ __('ui.cancel') }}
            </a>
        </div>
    </form>
</div>
@endsection
