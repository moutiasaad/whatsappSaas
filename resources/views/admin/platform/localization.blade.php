@extends('layouts.admin')

@section('title', __('ui.platform_localization_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.platform_localization_page.breadcrumb_root') }}</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ __('ui.platform_localization_page.breadcrumb') }}</span>
@endsection

@section('content')
<div>
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.platform_localization_page.page_title') }}</div>
            <div class="page-subtitle">{{ __('ui.platform_localization_page.subtitle') }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">{{ __('ui.platform_localization_page.default_language_title') }}</div>
                <div class="card-subtitle">{{ __('ui.platform_localization_page.default_language_subtitle') }}</div>
            </div>
        </div>

        <form method="POST" action="{{ route('super_admin.platform.localization.update') }}" style="padding:20px;">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label class="form-label" for="default_locale">
                    {{ __('ui.platform_localization_page.default_locale_label') }}
                </label>

                {{-- Locale cards. Each option is a click-to-select radio card
                     showing the native name + a hint about direction. Falls
                     back to a plain <select> for keyboard/screen-reader users:
                     the radio inputs are the real form fields, the cards are
                     just <label> hit targets. --}}
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;margin-top:6px;">
                    @foreach($supported as $code => $meta)
                        <label style="display:flex;align-items:center;gap:10px;padding:12px;border:1px solid var(--card-border);border-radius:10px;cursor:pointer;background:var(--card-bg);"
                               :class="$('input[name=default_locale]:checked')?.value === '{{ $code }}' ? 'radio-card-active' : ''">
                            <input type="radio" name="default_locale" value="{{ $code }}"
                                   @checked(old('default_locale', $currentDefault) === $code)
                                   style="margin:0;accent-color:var(--brand);">
                            <div style="flex:1;min-width:0;">
                                <div style="font-weight:600;font-size:14px;" @if(($meta['rtl'] ?? false)) dir="rtl" @endif>{{ $meta['native'] ?? $code }}</div>
                                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">
                                    {{ $code }}
                                    @if($meta['rtl'] ?? false) · RTL @endif
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>

                @error('default_locale')
                    <div class="form-error" style="margin-top:8px;">{{ $message }}</div>
                @enderror

                <div class="form-hint" style="margin-top:12px;color:var(--text-muted);font-size:12.5px;line-height:1.6">
                    {{ __('ui.platform_localization_page.default_locale_hint') }}
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                <button type="submit" class="btn btn-primary">
                    <i class="ri-save-line"></i> {{ __('ui.platform_localization_page.save') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
