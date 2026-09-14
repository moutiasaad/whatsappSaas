@extends('layouts.admin')

@section('title', __('ui.platform_conversation_settings_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.platform_conversation_settings_page.breadcrumb_root') }}</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ __('ui.platform_conversation_settings_page.breadcrumb') }}</span>
@endsection

@section('content')
<div>
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.platform_conversation_settings_page.page_title') }}</div>
            <div class="page-subtitle">{{ __('ui.platform_conversation_settings_page.subtitle') }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">{{ __('ui.platform_conversation_settings_page.idle_title') }}</div>
                <div class="card-subtitle">{{ __('ui.platform_conversation_settings_page.idle_subtitle') }}</div>
            </div>
        </div>

        <form method="POST" action="{{ route('super_admin.platform.conversation-settings.update') }}" style="padding:20px;">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label class="form-label" for="ai_idle_close_minutes">
                    {{ __('ui.platform_conversation_settings_page.idle_label') }}
                </label>

                <select id="ai_idle_close_minutes" name="ai_idle_close_minutes"
                        class="form-control @error('ai_idle_close_minutes') error @enderror">
                    @foreach ($presets as $minutes)
                        <option value="{{ $minutes }}" @selected(old('ai_idle_close_minutes', $idleMinutes) == $minutes)>
                            @if ($minutes === 0)
                                {{ __('ui.platform_conversation_settings_page.idle_disabled') }}
                            @else
                                {{ __('ui.platform_conversation_settings_page.idle_minutes', ['minutes' => $minutes]) }}
                            @endif
                        </option>
                    @endforeach

                    {{-- A value set outside these presets stays selectable rather than
                         being silently rounded to the nearest one on the next save. --}}
                    @unless (in_array($idleMinutes, $presets, true))
                        <option value="{{ $idleMinutes }}" selected>
                            {{ __('ui.platform_conversation_settings_page.idle_minutes', ['minutes' => $idleMinutes]) }}
                        </option>
                    @endunless
                </select>

                @error('ai_idle_close_minutes')
                    <div class="form-error">{{ $message }}</div>
                @enderror

                <div class="form-hint" style="margin-top:8px;color:var(--text-muted);font-size:12.5px;line-height:1.6">
                    {{ __('ui.platform_conversation_settings_page.idle_hint') }}
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                <button type="submit" class="btn btn-primary">
                    <i class="ri-save-line"></i> {{ __('ui.platform_conversation_settings_page.save') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
