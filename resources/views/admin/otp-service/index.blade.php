@extends('layouts.admin')

@section('title', __('otp.page_title'))

@section('breadcrumb')
    <span>{{ __('otp.breadcrumb') }}</span>
@endsection

@section('content')
@php
    $panelPrefix = auth()->user()->routeNamePrefix();
    $apiKey      = $user->api_key ?: '';
    $baseUrl     = rtrim(config('app.url'), '/');
    $sendUrl     = $baseUrl . '/api/otp/send';
    $verifyUrl   = $baseUrl . '/api/otp/verify';
@endphp

<style>
.otp-code-block {
    background: #0d1117;
    color: #e6edf3;
    border-radius: .75rem;
    padding: 1rem 1.125rem;
    font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, monospace;
    font-size: .8125rem;
    line-height: 1.55;
    overflow-x: auto;
    white-space: pre;
    position: relative;
}
.otp-code-block .copy-btn {
    position: absolute; top: .625rem; right: .625rem;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.14);
    color: #cbd5e1;
    padding: .25rem .5rem;
    border-radius: .375rem;
    font-size: .75rem;
    cursor: pointer;
    transition: background .15s;
}
.otp-code-block .copy-btn:hover { background: rgba(255,255,255,.14); }
.otp-tabs { display:flex; gap:.25rem; border-bottom:1px solid var(--card-border); margin-bottom:1rem; }
.otp-tab {
    padding:.5rem .875rem; font-size:.8125rem; font-weight:600; cursor:pointer;
    color:var(--text-muted); border:none; background:none; border-bottom:2px solid transparent;
    transition:color .15s, border-color .15s;
}
.otp-tab.active { color:#10b981; border-bottom-color:#10b981; }
.status-pill {
    display:inline-flex; align-items:center; gap:.375rem;
    padding:.25rem .625rem; border-radius:9999px; font-size:.75rem; font-weight:600;
}
.status-pill.ok   { background:rgba(16,185,129,.1); color:#059669; }
.status-pill.warn { background:rgba(234,179,8,.1);  color:#a16207; }
</style>

<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">{{ __('otp.title') }}</div>
        <div class="page-subtitle">{{ __('otp.subtitle') }}</div>
    </div>
    @if($connectedInstance)
        <span class="status-pill ok">
            <i class="ri-checkbox-circle-line"></i>
            {{ __('otp.instance_connected', ['name' => $connectedInstance->name]) }}
        </span>
    @else
        <span class="status-pill warn">
            <i class="ri-error-warning-line"></i>
            {{ __('otp.no_connected_instance') }}
        </span>
    @endif
</div>

@if(session('success'))
<div style="padding:.75rem 1rem;background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.25);color:#065f46;border-radius:.625rem;margin-bottom:1.25rem;font-size:.875rem;">
    <i class="ri-check-line"></i> {{ session('success') }}
</div>
@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start">

    {{-- LEFT: Configuration form --}}
    <form action="{{ route($panelPrefix . '.otp-service.update') }}" method="POST" data-unsaved data-loading>
        @csrf @method('PUT')

        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">{{ __('otp.config_title') }}</div>
                    <div class="card-subtitle">{{ __('otp.config_subtitle') }}</div>
                </div>
                <div style="width:2.25rem;height:2.25rem;border-radius:.625rem;background:rgba(16,185,129,.1);display:flex;align-items:center;justify-content:center;color:#10b981;flex-shrink:0;">
                    <i class="ri-shield-keyhole-line"></i>
                </div>
            </div>
            <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:1.25rem">

                <div style="display:flex;justify-content:space-between;align-items:center;padding:.75rem .875rem;background:var(--page-bg);border:1px solid var(--card-border);border-radius:.625rem;">
                    <div>
                        <div style="font-weight:600;font-size:.875rem;">{{ __('otp.enable_label') }}</div>
                        <div style="font-size:.75rem;color:var(--text-muted);">{{ __('otp.enable_hint') }}</div>
                    </div>
                    <label style="position:relative;display:inline-block;width:2.75rem;height:1.5rem;">
                        <input type="checkbox" name="enabled" value="1" {{ $settings['enabled'] ? 'checked' : '' }}
                               style="opacity:0;width:0;height:0;peer" class="otp-toggle-input">
                        <span style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#cbd5e1;border-radius:1rem;transition:.2s;"
                              class="otp-toggle-track"></span>
                    </label>
                </div>

                <div class="form-group">
                    <label class="form-label" for="code_length">{{ __('otp.code_length_label') }}</label>
                    <select id="code_length" name="code_length" class="form-control">
                        @foreach([4, 6, 8] as $n)
                            <option value="{{ $n }}" {{ (int)$settings['code_length'] === $n ? 'selected' : '' }}>{{ $n }} {{ __('otp.digits') }}</option>
                        @endforeach
                    </select>
                    @error('code_length') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="ttl_minutes">{{ __('otp.ttl_label') }}</label>
                    <input type="number" id="ttl_minutes" name="ttl_minutes"
                           min="1" max="60"
                           value="{{ old('ttl_minutes', $settings['ttl_minutes']) }}"
                           class="form-control">
                    <div class="form-hint">{{ __('otp.ttl_hint') }}</div>
                    @error('ttl_minutes') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="template">{{ __('otp.template_label') }}</label>
                    <textarea id="template" name="template" rows="3" class="form-control"
                              style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.8125rem;">{{ old('template', $settings['template']) }}</textarea>
                    <div class="form-hint">{{ __('otp.template_hint') }}</div>
                    @error('template') <div class="form-error">{{ $message }}</div> @enderror
                </div>

            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:.5rem;margin-top:1rem;">
            <button type="submit" class="btn btn-primary">
                <i class="ri-save-line"></i> {{ __('otp.save') }}
            </button>
        </div>
    </form>

    {{-- RIGHT: Credentials + integration --}}
    <div style="display:flex;flex-direction:column;gap:1.5rem;">

        {{-- API key card --}}
        <div class="card" x-data="otpKeyCard()">
            <div class="card-header">
                <div>
                    <div class="card-title">{{ __('otp.credentials_title') }}</div>
                    <div class="card-subtitle">{{ __('otp.credentials_subtitle') }}</div>
                </div>
                <div style="width:2.25rem;height:2.25rem;border-radius:.625rem;background:rgba(91,106,240,.1);display:flex;align-items:center;justify-content:center;color:#5b6af0;flex-shrink:0;">
                    <i class="ri-key-2-line"></i>
                </div>
            </div>
            <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:1rem;">

                <div class="form-group" style="margin:0;">
                    <label class="form-label">{{ __('otp.base_url_label') }}</label>
                    <div style="display:flex;gap:.5rem;">
                        <input type="text" value="{{ $baseUrl }}" readonly class="form-control"
                               style="font-family:ui-monospace,Menlo,monospace;font-size:.8125rem;background:var(--page-bg);">
                        <button type="button" class="btn btn-secondary" @click="copyText('{{ $baseUrl }}', $event)"
                                style="white-space:nowrap;">
                            <i class="ri-clipboard-line"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label">{{ __('otp.api_key_label') }}</label>
                    @if($apiKey)
                    <div style="display:flex;gap:.5rem;">
                        <input :type="visible ? 'text' : 'password'"
                               :value="key" readonly class="form-control"
                               style="font-family:ui-monospace,Menlo,monospace;font-size:.8125rem;background:var(--page-bg);">
                        <button @click="visible=!visible" type="button" class="btn btn-secondary" style="white-space:nowrap;">
                            <i :class="visible ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                        </button>
                        <button @click="copyText(key, $event)" type="button" class="btn btn-secondary" style="white-space:nowrap;">
                            <i class="ri-clipboard-line"></i>
                        </button>
                    </div>
                    <div class="form-hint">{{ __('otp.api_key_hint') }}
                        <a href="{{ route($panelPrefix . '.profile.show') }}" style="color:#10b981;">{{ __('otp.regenerate_from_profile') }}</a>
                    </div>
                    @else
                    <div style="padding:.875rem;background:rgba(234,179,8,.08);border:1px solid rgba(234,179,8,.25);border-radius:.625rem;font-size:.8125rem;color:#92400e;">
                        <i class="ri-error-warning-line"></i> {{ __('otp.no_api_key') }}
                        <a href="{{ route($panelPrefix . '.profile.show') }}" style="color:#a16207;font-weight:600;">{{ __('otp.generate_api_key') }}</a>
                    </div>
                    @endif
                </div>

            </div>
        </div>

        {{-- Integration snippets --}}
        <div class="card" x-data="otpSnippets()">
            <div class="card-header">
                <div>
                    <div class="card-title">{{ __('otp.integration_title') }}</div>
                    <div class="card-subtitle">{{ __('otp.integration_subtitle') }}</div>
                </div>
                <div style="width:2.25rem;height:2.25rem;border-radius:.625rem;background:rgba(139,92,246,.1);display:flex;align-items:center;justify-content:center;color:#8b5cf6;flex-shrink:0;">
                    <i class="ri-code-s-slash-line"></i>
                </div>
            </div>
            <div style="padding:0 1.5rem 1.5rem;">

                <div class="otp-tabs">
                    <button type="button" class="otp-tab" :class="{'active': tab==='curl'}"    @click="tab='curl'">cURL</button>
                    <button type="button" class="otp-tab" :class="{'active': tab==='laravel'}" @click="tab='laravel'">Laravel</button>
                    <button type="button" class="otp-tab" :class="{'active': tab==='node'}"    @click="tab='node'">Node.js</button>
                </div>

                {{-- cURL --}}
                <div x-show="tab==='curl'">
                    <div style="font-size:.75rem;font-weight:600;color:var(--text-muted);margin-bottom:.375rem;">{{ __('otp.send_code') }}</div>
                    <div class="otp-code-block">
<button class="copy-btn" @click="copyBlock($event)"><i class="ri-clipboard-line"></i></button><span>curl -X POST {{ $sendUrl }} \
  -H "X-Api-Key: {{ $apiKey ?: 'YOUR_API_KEY' }}" \
  -H "Content-Type: application/json" \
  -d '{"phone":"+212600000000"}'</span></div>

                    <div style="font-size:.75rem;font-weight:600;color:var(--text-muted);margin:1rem 0 .375rem;">{{ __('otp.verify_code') }}</div>
                    <div class="otp-code-block">
<button class="copy-btn" @click="copyBlock($event)"><i class="ri-clipboard-line"></i></button><span>curl -X POST {{ $verifyUrl }} \
  -H "X-Api-Key: {{ $apiKey ?: 'YOUR_API_KEY' }}" \
  -H "Content-Type: application/json" \
  -d '{"phone":"+212600000000","code":"123456"}'</span></div>
                </div>

                {{-- Laravel --}}
                <div x-show="tab==='laravel'" style="display:none;">
                    <div style="font-size:.75rem;font-weight:600;color:var(--text-muted);margin-bottom:.375rem;">{{ __('otp.laravel_hint') }}</div>
                    <div class="otp-code-block">
<button class="copy-btn" @click="copyBlock($event)"><i class="ri-clipboard-line"></i></button><span>use Illuminate\Support\Facades\Http;

// Send OTP
$response = Http::withHeaders([
        'X-Api-Key' => env('WAVADESK_API_KEY'),
    ])
    ->post('{{ $sendUrl }}', [
        'phone' => $request->input('phone'),
    ]);

if (! $response->json('ok')) {
    return back()->withErrors(['phone' => $response->json('message')]);
}

// Verify OTP
$verify = Http::withHeaders([
        'X-Api-Key' => env('WAVADESK_API_KEY'),
    ])
    ->post('{{ $verifyUrl }}', [
        'phone' => $request->input('phone'),
        'code'  => $request->input('code'),
    ]);

return $verify->json('ok')
    ? redirect()->route('home')
    : back()->withErrors(['code' => $verify->json('message')]);</span></div>
                </div>

                {{-- Node --}}
                <div x-show="tab==='node'" style="display:none;">
                    <div class="otp-code-block">
<button class="copy-btn" @click="copyBlock($event)"><i class="ri-clipboard-line"></i></button><span>const send = await fetch('{{ $sendUrl }}', {
    method: 'POST',
    headers: {
        'X-Api-Key': process.env.WAVADESK_API_KEY,
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({ phone: '+212600000000' }),
});
const sendJson = await send.json();

const verify = await fetch('{{ $verifyUrl }}', {
    method: 'POST',
    headers: {
        'X-Api-Key': process.env.WAVADESK_API_KEY,
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({ phone: '+212600000000', code: '123456' }),
});
const verifyJson = await verify.json();</span></div>
                </div>

                {{-- Response reference --}}
                <div style="margin-top:1.25rem;padding:.75rem .875rem;background:var(--page-bg);border:1px solid var(--card-border);border-radius:.625rem;font-size:.75rem;color:var(--text-muted);">
                    <div style="font-weight:600;color:var(--text-primary);margin-bottom:.375rem;">{{ __('otp.response_ref_title') }}</div>
                    <div><code>ok</code>: {{ __('otp.response_ref_ok') }}</div>
                    <div><code>error</code>: cooldown | invalid_code | expired | too_many_attempts | no_instance | otp_disabled</div>
                    <div><code>retry_after</code>: {{ __('otp.response_ref_retry') }}</div>
                </div>

            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Toggle switch visual
    document.querySelectorAll('.otp-toggle-input').forEach(input => {
        const paint = () => {
            const track = input.closest('label').querySelector('.otp-toggle-track');
            if (input.checked) {
                track.style.background = '#10b981';
                track.style.boxShadow = 'inset 1.25rem 0 0 0 #10b981';
            } else {
                track.style.background = '#cbd5e1';
                track.style.boxShadow = 'none';
            }
        };
        // Fake the thumb via a pseudo-like element built in JS
        const track = input.closest('label').querySelector('.otp-toggle-track');
        const thumb = document.createElement('span');
        thumb.style.cssText = 'position:absolute;top:2px;left:2px;width:1.25rem;height:1.25rem;background:#fff;border-radius:50%;transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.15);';
        track.appendChild(thumb);
        const paintThumb = () => { thumb.style.transform = input.checked ? 'translateX(1.25rem)' : 'translateX(0)'; };
        paint(); paintThumb();
        input.addEventListener('change', () => { paint(); paintThumb(); });
    });
});

function otpKeyCard() {
    return {
        key: @json($apiKey),
        visible: false,
        copyText(text, ev) {
            if (!text) return;
            navigator.clipboard.writeText(text);
            const btn = ev.currentTarget;
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="ri-check-line"></i>';
            setTimeout(() => btn.innerHTML = original, 1200);
        },
    };
}

function otpSnippets() {
    return {
        tab: 'curl',
        copyBlock(ev) {
            const block = ev.currentTarget.parentElement;
            const text = block.querySelector('span').innerText;
            navigator.clipboard.writeText(text);
            const btn = ev.currentTarget;
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="ri-check-line"></i>';
            setTimeout(() => btn.innerHTML = original, 1200);
        },
    };
}
</script>
@endpush
@endsection
