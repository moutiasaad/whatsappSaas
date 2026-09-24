@extends('layouts.admin')

@section('title', __('ui.signup_protection.title'))

@section('breadcrumb')
    <span style="color:var(--text-muted)">Platform</span>
    <span style="color:var(--text-muted);margin:0 .375rem">›</span>
    <span>{{ __('ui.signup_protection.nav') }}</span>
@endsection

@section('content')
<div style="max-width:820px">
    <div style="margin-bottom:1.5rem">
        <h1 style="font-size:1.375rem;font-weight:700;color:var(--text-primary)">{{ __('ui.signup_protection.title') }}</h1>
        <p style="font-size:.875rem;color:var(--text-muted);margin-top:.25rem;line-height:1.55">
            {{ __('ui.signup_protection.intro') }}
        </p>
    </div>

    @if(session('success'))
        <div class="card" style="margin-bottom:1rem;padding:.875rem 1.125rem;border-left:3px solid #10b981;background:#ecfdf5">
            <div style="font-size:.875rem;color:#065f46">{{ session('success') }}</div>
        </div>
    @endif

    @if($errors->any())
        <div class="card" style="margin-bottom:1rem;padding:.875rem 1.125rem;border-left:3px solid #ef4444;background:#fef2f2">
            <ul style="margin:0;padding-inline-start:1.25rem;font-size:.8125rem;color:#7f1d1d;line-height:1.55">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Switched on but missing a key is a state the operator has to be told
         about: the switch reads "on" while no signup is actually challenged. --}}
    @if($enabled && !$active)
        <div class="card" style="margin-bottom:1rem;padding:.875rem 1.125rem;border-left:3px solid #d97706;background:#fef3e2">
            <div style="font-size:.875rem;color:#92400e">{{ __('ui.signup_protection.incomplete') }}</div>
        </div>
    @endif

    <form action="{{ route('super_admin.platform.signup-protection.update') }}" method="POST" data-loading>
        @csrf
        @method('PUT')

        <div class="card" style="padding:1.25rem 1.5rem;margin-bottom:1rem">
            <div style="display:flex;align-items:flex-start;gap:1rem">
                <div style="flex:1;min-width:0">
                    <div style="font-weight:600;font-size:.9375rem;margin-bottom:.125rem">{{ __('ui.signup_protection.toggle_title') }}</div>
                    <p style="font-size:.8125rem;color:var(--text-muted);line-height:1.55;margin:0;max-width:60ch">
                        {{ __('ui.signup_protection.toggle_desc') }}
                    </p>
                </div>
                <label style="display:inline-flex;align-items:center;gap:.5rem;cursor:pointer;flex-shrink:0">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $enabled))
                           style="width:18px;height:18px;accent-color:var(--brand);cursor:pointer">
                    <span style="font-size:.875rem;font-weight:600">{{ __('ui.signup_protection.toggle_label') }}</span>
                </label>
            </div>

            <div style="margin-top:.875rem;font-size:.8125rem;color:var(--text-muted)">
                {{ __('ui.signup_protection.status') }}
                <strong style="color:{{ $active ? '#047857' : 'var(--text-muted)' }}">
                    {{ $active ? __('ui.signup_protection.status_on') : __('ui.signup_protection.status_off') }}
                </strong>
            </div>
        </div>

        <div class="card" style="padding:1.25rem 1.5rem;margin-bottom:1rem">
            <div style="font-weight:600;font-size:.9375rem;margin-bottom:.125rem">{{ __('ui.signup_protection.keys_title') }}</div>
            <p style="font-size:.75rem;color:var(--text-muted);margin-bottom:1.125rem">
                {!! __('ui.signup_protection.keys_desc', [
                    'link' => '<a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener" style="color:var(--brand)">dash.cloudflare.com → Turnstile</a>',
                ]) !!}
            </p>

            <div class="form-group" style="margin-bottom:1rem">
                <label class="form-label">
                    {{ __('ui.signup_protection.site_key') }}
                    @if($siteKeyInDb)<span class="badge badge-green" style="font-size:.6875rem;margin-inline-start:.375rem">{{ __('ui.signup_protection.in_db') }}</span>
                    @elseif($siteKey !== '')<span class="badge badge-gray" style="font-size:.6875rem;margin-inline-start:.375rem">{{ __('ui.signup_protection.from_env') }}</span>@endif
                </label>
                <input type="text" name="site_key" value="{{ old('site_key', $siteKey) }}"
                       placeholder="0x4AAAAAAA…" autocomplete="off" dir="ltr"
                       class="form-control @error('site_key') error @enderror">
                <div class="form-hint">{{ __('ui.signup_protection.site_key_hint') }}</div>
                @error('site_key')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group" style="margin:0">
                <label class="form-label">
                    {{ __('ui.signup_protection.secret_key') }}
                    @if($secretInDb)<span class="badge badge-green" style="font-size:.6875rem;margin-inline-start:.375rem">{{ __('ui.signup_protection.in_db') }}</span>
                    @elseif($secretSet)<span class="badge badge-gray" style="font-size:.6875rem;margin-inline-start:.375rem">{{ __('ui.signup_protection.from_env') }}</span>@endif
                </label>
                <input type="password" name="secret_key" value="" autocomplete="new-password" dir="ltr"
                       placeholder="{{ $secretSet ? $secretMask : '0x4AAAAAAA…' }}"
                       class="form-control @error('secret_key') error @enderror">
                <div class="form-hint">
                    {{ $secretSet ? __('ui.signup_protection.secret_stored') : __('ui.signup_protection.secret_empty') }}
                </div>
                @error('secret_key')<div class="form-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="ri-save-line"></i> {{ __('ui.signup_protection.save') }}
        </button>
    </form>
</div>
@endsection
