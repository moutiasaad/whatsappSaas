@extends('layouts.admin')

@php
    $isEdit = $country !== null;
@endphp

@section('title', $isEdit ? __('ui.platform_countries_page.edit_title', ['name' => $country->name]) : __('ui.platform_countries_page.add_country'))

@section('breadcrumb')
    <span>{{ __('ui.platform_countries_page.breadcrumb_root') }}</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <a href="{{ route('super_admin.platform.countries.index') }}" style="color:var(--text-secondary);text-decoration:none;">{{ __('ui.platform_countries_page.title') }}</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ $isEdit ? $country->name : __('ui.platform_countries_page.add_country') }}</span>
@endsection

@section('content')
<div style="max-width:720px;">
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ $isEdit ? __('ui.platform_countries_page.edit_title', ['name' => $country->name]) : __('ui.platform_countries_page.add_country') }}</div>
            <div class="page-subtitle">{{ __('ui.platform_countries_page.form_subtitle') }}</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('super_admin.platform.countries.index') }}" class="btn btn-outline">
                <i class="ri-arrow-left-line"></i> {{ __('ui.back') }}
            </a>
        </div>
    </div>

    <div class="card">
        <form method="POST"
              action="{{ $isEdit ? route('super_admin.platform.countries.update', $country) : route('super_admin.platform.countries.store') }}"
              style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            @csrf
            @if($isEdit) @method('PUT') @endif

            <div class="form-group" style="grid-column:1 / -1;">
                <label class="form-label" for="name">{{ __('ui.platform_countries_page.field_name') }}</label>
                <input id="name" name="name" type="text" required maxlength="100"
                       value="{{ old('name', $country?->name) }}"
                       placeholder="{{ __('ui.platform_countries_page.field_name_placeholder') }}"
                       class="form-control @error('name') error @enderror">
                @error('name') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="code">{{ __('ui.platform_countries_page.field_code') }}</label>
                <input id="code" name="code" type="text" required maxlength="2" minlength="2"
                       value="{{ old('code', $country?->code) }}"
                       placeholder="SA / US / FR"
                       style="text-transform:uppercase;font-family:var(--font-mono);"
                       class="form-control @error('code') error @enderror">
                <div class="form-hint" style="color:var(--text-muted);font-size:12px;margin-top:4px;">
                    {{ __('ui.platform_countries_page.field_code_hint') }}
                </div>
                @error('code') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="currency_code">{{ __('ui.platform_countries_page.field_currency_code') }}</label>
                <input id="currency_code" name="currency_code" type="text" required maxlength="3" minlength="3"
                       value="{{ old('currency_code', $country?->currency_code) }}"
                       placeholder="SAR / USD / EUR"
                       style="text-transform:uppercase;font-family:var(--font-mono);"
                       class="form-control @error('currency_code') error @enderror">
                <div class="form-hint" style="color:var(--text-muted);font-size:12px;margin-top:4px;">
                    {{ __('ui.platform_countries_page.field_currency_code_hint') }}
                </div>
                @error('currency_code') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="currency_symbol">{{ __('ui.platform_countries_page.field_currency_symbol') }}</label>
                <input id="currency_symbol" name="currency_symbol" type="text" required maxlength="8"
                       value="{{ old('currency_symbol', $country?->currency_symbol) }}"
                       placeholder="ر.س / $ / €"
                       class="form-control @error('currency_symbol') error @enderror">
                <div class="form-hint" style="color:var(--text-muted);font-size:12px;margin-top:4px;">
                    {{ __('ui.platform_countries_page.field_currency_symbol_hint') }}
                </div>
                @error('currency_symbol') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label class="form-label" style="display:flex;align-items:center;gap:8px;margin-top:26px;cursor:pointer;">
                    <input type="checkbox" name="is_active" value="1"
                           @checked(old('is_active', $country?->is_active ?? true))
                           style="width:18px;height:18px;accent-color:var(--brand);">
                    <span>{{ __('ui.platform_countries_page.field_is_active') }}</span>
                </label>
                <div class="form-hint" style="color:var(--text-muted);font-size:12px;margin-top:4px;padding-inline-start:26px;">
                    {{ __('ui.platform_countries_page.field_is_active_hint') }}
                </div>
            </div>

            <div style="grid-column:1 / -1;display:flex;justify-content:flex-end;gap:8px;margin-top:8px;">
                <a href="{{ route('super_admin.platform.countries.index') }}" class="btn btn-outline">{{ __('ui.cancel') }}</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ri-save-line"></i>
                    {{ $isEdit ? __('ui.save_changes') : __('ui.platform_countries_page.add_country') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
