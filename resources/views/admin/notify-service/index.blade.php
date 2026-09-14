@extends('layouts.admin')

@section('title', __('notify.page_title'))

@section('breadcrumb')
    <span>{{ __('notify.breadcrumb') }}</span>
@endsection

@section('content')
@php
    $panelPrefix = auth()->user()->routeNamePrefix();
    $apiKey      = $user->api_key ?: '';
    $baseUrl     = rtrim(config('app.url'), '/');
    $sendUrl     = $baseUrl . '/api/notify/send';
@endphp

<style>
.notify-code-block {
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
.notify-code-block .copy-btn {
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
.notify-code-block .copy-btn:hover { background: rgba(255,255,255,.14); }
.notify-tabs { display:flex; gap:.25rem; border-bottom:1px solid var(--card-border); margin-bottom:1rem; }
.notify-tab {
    padding:.5rem .875rem; font-size:.8125rem; font-weight:600; cursor:pointer;
    color:var(--text-muted); border:none; background:none; border-bottom:2px solid transparent;
    transition:color .15s, border-color .15s;
}
.notify-tab.active { color:#5b6af0; border-bottom-color:#5b6af0; }
.status-pill {
    display:inline-flex; align-items:center; gap:.375rem;
    padding:.25rem .625rem; border-radius:9999px; font-size:.75rem; font-weight:600;
}
.status-pill.ok   { background:rgba(16,185,129,.1); color:#059669; }
.status-pill.warn { background:rgba(234,179,8,.1);  color:#a16207; }
</style>

<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">{{ __('notify.title') }}</div>
        <div class="page-subtitle">{{ __('notify.subtitle') }}</div>
    </div>
    @if($connectedInstance)
        <span class="status-pill ok">
            <i class="ri-checkbox-circle-line"></i>
            {{ __('notify.instance_connected', ['name' => $connectedInstance->name]) }}
        </span>
    @else
        <span class="status-pill warn">
            <i class="ri-error-warning-line"></i>
            {{ __('notify.no_connected_instance') }}
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
    <form action="{{ route($panelPrefix . '.notify-service.update') }}" method="POST" data-unsaved data-loading>
        @csrf @method('PUT')

        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">{{ __('notify.config_title') }}</div>
                    <div class="card-subtitle">{{ __('notify.config_subtitle') }}</div>
                </div>
                <div style="width:2.25rem;height:2.25rem;border-radius:.625rem;background:rgba(91,106,240,.1);display:flex;align-items:center;justify-content:center;color:#5b6af0;flex-shrink:0;">
                    <i class="ri-send-plane-line"></i>
                </div>
            </div>
            <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:1.25rem">

                <div style="display:flex;justify-content:space-between;align-items:center;padding:.75rem .875rem;background:var(--page-bg);border:1px solid var(--card-border);border-radius:.625rem;">
                    <div>
                        <div style="font-weight:600;font-size:.875rem;">{{ __('notify.enable_label') }}</div>
                        <div style="font-size:.75rem;color:var(--text-muted);">{{ __('notify.enable_hint') }}</div>
                    </div>
                    <label style="position:relative;display:inline-block;width:2.75rem;height:1.5rem;">
                        <input type="checkbox" name="enabled" value="1" {{ $settings['enabled'] ? 'checked' : '' }}
                               style="opacity:0;width:0;height:0;" class="notify-toggle-input">
                        <span style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#cbd5e1;border-radius:1rem;transition:.2s;"
                              class="notify-toggle-track"></span>
                    </label>
                </div>

                <div class="form-group">
                    <label class="form-label" for="admin_phone">{{ __('notify.admin_phone_label') }}</label>
                    <input type="text" id="admin_phone" name="admin_phone"
                           placeholder="+212600000000"
                           value="{{ old('admin_phone', $settings['admin_phone']) }}"
                           class="form-control"
                           style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;">
                    <div class="form-hint">{{ __('notify.admin_phone_hint') }}</div>
                    @error('admin_phone') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="prefix">{{ __('notify.prefix_label') }}</label>
                    <input type="text" id="prefix" name="prefix"
                           maxlength="64"
                           placeholder="[Wavadesk]"
                           value="{{ old('prefix', $settings['prefix']) }}"
                           class="form-control">
                    <div class="form-hint">{{ __('notify.prefix_hint') }}</div>
                    @error('prefix') <div class="form-error">{{ $message }}</div> @enderror
                </div>

            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:.5rem;margin-top:1rem;">
            <button type="submit" class="btn btn-primary">
                <i class="ri-save-line"></i> {{ __('notify.save') }}
            </button>
        </div>
    </form>

    {{-- RIGHT: Credentials + integration --}}
    <div style="display:flex;flex-direction:column;gap:1.5rem;">

        {{-- API key card --}}
        <div class="card" x-data="notifyKeyCard()">
            <div class="card-header">
                <div>
                    <div class="card-title">{{ __('notify.credentials_title') }}</div>
                    <div class="card-subtitle">{{ __('notify.credentials_subtitle') }}</div>
                </div>
                <div style="width:2.25rem;height:2.25rem;border-radius:.625rem;background:rgba(91,106,240,.1);display:flex;align-items:center;justify-content:center;color:#5b6af0;flex-shrink:0;">
                    <i class="ri-key-2-line"></i>
                </div>
            </div>
            <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:1rem;">

                <div class="form-group" style="margin:0;">
                    <label class="form-label">{{ __('notify.endpoint_label') }}</label>
                    <div style="display:flex;gap:.5rem;">
                        <input type="text" value="{{ $sendUrl }}" readonly class="form-control"
                               style="font-family:ui-monospace,Menlo,monospace;font-size:.8125rem;background:var(--page-bg);">
                        <button type="button" class="btn btn-secondary" @click="copyText('{{ $sendUrl }}', $event)"
                                style="white-space:nowrap;">
                            <i class="ri-clipboard-line"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label">{{ __('notify.api_key_label') }}</label>
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
                    <div class="form-hint">{{ __('notify.api_key_hint') }}
                        <a href="{{ route($panelPrefix . '.profile.show') }}" style="color:#5b6af0;">{{ __('notify.regenerate_from_profile') }}</a>
                    </div>
                    @else
                    <div style="padding:.875rem;background:rgba(234,179,8,.08);border:1px solid rgba(234,179,8,.25);border-radius:.625rem;font-size:.8125rem;color:#92400e;">
                        <i class="ri-error-warning-line"></i> {{ __('notify.no_api_key') }}
                        <a href="{{ route($panelPrefix . '.profile.show') }}" style="color:#a16207;font-weight:600;">{{ __('notify.generate_api_key') }}</a>
                    </div>
                    @endif
                </div>

            </div>
        </div>

        {{-- Test send card --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">{{ __('notify.test_title') }}</div>
                    <div class="card-subtitle">{{ __('notify.test_subtitle') }}</div>
                </div>
                <div style="width:2.25rem;height:2.25rem;border-radius:.625rem;background:rgba(16,185,129,.1);display:flex;align-items:center;justify-content:center;color:#10b981;flex-shrink:0;">
                    <i class="ri-flask-line"></i>
                </div>
            </div>
            <form action="{{ route($panelPrefix . '.notify-service.test') }}" method="POST" style="padding:0 1.5rem 1.5rem;">
                @csrf
                <div class="form-group">
                    <textarea name="message" rows="2" class="form-control"
                              placeholder="{{ __('notify.test_placeholder') }}"
                              required maxlength="4000">{{ __('notify.test_default') }}</textarea>
                    @error('message') <div class="form-error">{{ $message }}</div> @enderror
                </div>
                <button type="submit" class="btn btn-secondary" style="width:100%;">
                    <i class="ri-send-plane-2-line"></i> {{ __('notify.test_button') }}
                </button>
            </form>
        </div>

        {{-- Integration snippets --}}
        <div class="card" x-data="notifySnippets()">
            <div class="card-header">
                <div>
                    <div class="card-title">{{ __('notify.integration_title') }}</div>
                    <div class="card-subtitle">{{ __('notify.integration_subtitle') }}</div>
                </div>
                <div style="width:2.25rem;height:2.25rem;border-radius:.625rem;background:rgba(21,182,168,.1);display:flex;align-items:center;justify-content:center;color:#15b6a8;flex-shrink:0;">
                    <i class="ri-code-s-slash-line"></i>
                </div>
            </div>
            <div style="padding:0 1.5rem 1.5rem;">

                <div class="notify-tabs">
                    <button type="button" class="notify-tab" :class="{'active': tab==='curl'}"    @click="tab='curl'">cURL</button>
                    <button type="button" class="notify-tab" :class="{'active': tab==='php'}"     @click="tab='php'">PHP</button>
                    <button type="button" class="notify-tab" :class="{'active': tab==='laravel'}" @click="tab='laravel'">Laravel</button>
                    <button type="button" class="notify-tab" :class="{'active': tab==='node'}"    @click="tab='node'">Node.js</button>
                </div>

                {{-- cURL --}}
                <div x-show="tab==='curl'">
                    <div class="notify-code-block">
<button class="copy-btn" @click="copyBlock($event)"><i class="ri-clipboard-line"></i></button><span>curl -X POST {{ $sendUrl }} \
  -H "X-Api-Key: {{ $apiKey ?: 'YOUR_API_KEY' }}" \
  -H "Content-Type: application/json" \
  -d '{"message":"New order #123 from John - $50"}'</span></div>
                </div>

                {{-- PHP (plain) --}}
                <div x-show="tab==='php'" style="display:none;">
                    <div class="notify-code-block">
<button class="copy-btn" @click="copyBlock($event)"><i class="ri-clipboard-line"></i></button><span>&lt;?php
$ch = curl_init('{{ $sendUrl }}');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'X-Api-Key: {{ $apiKey ?: 'YOUR_API_KEY' }}',
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'message' => "New order #{$order->id} from {$order->name} - {$order->total} MAD",
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);</span></div>
                </div>

                {{-- Laravel --}}
                <div x-show="tab==='laravel'" style="display:none;">
                    <div style="font-size:.75rem;font-weight:600;color:var(--text-muted);margin-bottom:.375rem;">{{ __('notify.laravel_hint') }}</div>
                    <div class="notify-code-block">
<button class="copy-btn" @click="copyBlock($event)"><i class="ri-clipboard-line"></i></button><span>use Illuminate\Support\Facades\Http;

Http::withHeaders([
        'X-Api-Key' => env('WAVADESK_API_KEY'),
    ])
    ->post('{{ $sendUrl }}', [
        'message' => "New order #{$order->id} from {$order->name} - {$order->total} MAD",
    ]);</span></div>
                </div>

                {{-- Node --}}
                <div x-show="tab==='node'" style="display:none;">
                    <div class="notify-code-block">
<button class="copy-btn" @click="copyBlock($event)"><i class="ri-clipboard-line"></i></button><span>await fetch('{{ $sendUrl }}', {
    method: 'POST',
    headers: {
        'X-Api-Key': process.env.WAVADESK_API_KEY,
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        message: `New order #${order.id} from ${order.name} - ${order.total} MAD`,
    }),
});</span></div>
                </div>

                {{-- Response reference --}}
                <div style="margin-top:1.25rem;padding:.75rem .875rem;background:var(--page-bg);border:1px solid var(--card-border);border-radius:.625rem;font-size:.75rem;color:var(--text-muted);">
                    <div style="font-weight:600;color:var(--text-primary);margin-bottom:.375rem;">{{ __('notify.response_ref_title') }}</div>
                    <div><code>ok</code>: {{ __('notify.response_ref_ok') }}</div>
                    <div><code>error</code>: service_disabled | no_admin_phone | no_instance | gateway_error | empty_message | message_too_long</div>
                </div>

            </div>
        </div>

    </div>
</div>

@if($recent->count() > 0)
<div class="card" style="margin-top:1.5rem;">
    <div class="card-header">
        <div>
            <div class="card-title">{{ __('notify.recent_title') }}</div>
            <div class="card-subtitle">{{ __('notify.recent_subtitle') }}</div>
        </div>
    </div>
    <div style="padding:0 1.5rem 1.5rem;">
        <table class="data-table" style="width:100%;">
            <thead>
                <tr>
                    <th style="width:11rem;">{{ __('notify.col_sent_at') }}</th>
                    <th>{{ __('notify.col_message') }}</th>
                    <th style="width:6rem;text-align:right;">{{ __('notify.col_status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recent as $msg)
                <tr>
                    <td style="color:var(--text-muted);font-size:.8125rem;">
                        {{ optional($msg->sent_at)->format('Y-m-d H:i') ?? $msg->created_at->format('Y-m-d H:i') }}
                    </td>
                    <td style="font-size:.875rem;">{{ \Illuminate\Support\Str::limit($msg->body, 140) }}</td>
                    <td style="text-align:right;">
                        <span class="badge" style="font-size:.6875rem;">{{ $msg->status }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.notify-toggle-input').forEach(input => {
        const track = input.closest('label').querySelector('.notify-toggle-track');
        const thumb = document.createElement('span');
        thumb.style.cssText = 'position:absolute;top:2px;left:2px;width:1.25rem;height:1.25rem;background:#fff;border-radius:50%;transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.15);';
        track.appendChild(thumb);
        const paint = () => {
            if (input.checked) {
                track.style.background = '#5b6af0';
                thumb.style.transform = 'translateX(1.25rem)';
            } else {
                track.style.background = '#cbd5e1';
                thumb.style.transform = 'translateX(0)';
            }
        };
        paint();
        input.addEventListener('change', () => {
            paint();
            const form = input.closest('form');
            if (!form) return;
            if (typeof form.requestSubmit === 'function') form.requestSubmit();
            else form.submit();
        });
    });
});

function notifyKeyCard() {
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

function notifySnippets() {
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
