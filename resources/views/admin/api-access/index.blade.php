@extends('layouts.admin')

@section('title', __('ui.api_access_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.api_access_page.title') }}</span>
@endsection

@section('content')
<div x-data="apiAccessPage()" x-cloak>

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.api_access_page.title') }}</div>
            <div class="page-subtitle">{{ __('ui.api_access_page.subtitle') }}</div>
        </div>
    </div>

    <div class="card" style="max-width:820px">
        <div class="card-header">
            <div>
                <div class="card-title">{{ __('ui.api_access_page.card_title') }}</div>
                <div class="card-subtitle">{!! __('ui.api_access_page.card_subtitle') !!}</div>
            </div>
            <div style="width:2.5rem;height:2.5rem;border-radius:.625rem;background:rgba(91,106,240,.1);display:flex;align-items:center;justify-content:center;color:#5b6af0;flex-shrink:0;">
                <i class="ri-key-2-line" style="font-size:1.25rem;"></i>
            </div>
        </div>
        <div class="card-body">

            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.25rem;">
                <div style="flex:1;position:relative;">
                    <input :type="visible ? 'text' : 'password'"
                           :value="key"
                           readonly
                           style="width:100%;font-family:monospace;font-size:.8125rem;padding:.625rem 2.5rem .625rem .875rem;background:var(--bg-secondary,#f8fafc);border:1px solid var(--border-color,#e2e8f0);border-radius:.5rem;color:var(--text-primary,#1e293b);"
                    />
                    <button @click="visible=!visible" type="button"
                            style="position:absolute;right:.625rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted,#64748b);font-size:1rem;line-height:1;">
                        <i :class="visible ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                    </button>
                </div>

                <button @click="copy()" type="button" class="btn btn-secondary" style="white-space:nowrap;display:flex;align-items:center;gap:.375rem;">
                    <i :class="copied ? 'ri-check-line' : 'ri-clipboard-line'"></i>
                    <span x-text="copied ? i18n.copied : i18n.copy"></span>
                </button>
            </div>

            <div style="padding:.875rem;background:rgba(234,179,8,.06);border:1px solid rgba(234,179,8,.2);border-radius:.625rem;font-size:.8125rem;color:#92400e;margin-bottom:1.25rem;">
                <i class="ri-shield-keyhole-line" style="margin-right:.375rem;"></i>
                {{ __('ui.api_access_page.secret_hint') }}
            </div>

            <button @click="regenerate()" type="button" class="btn btn-danger-outline" :disabled="loading"
                    style="display:flex;align-items:center;gap:.5rem;">
                <i class="ri-refresh-line" :class="{'ri-spin': loading}"></i>
                <span x-text="loading ? i18n.regenerating : i18n.regenerate"></span>
            </button>
            <p style="font-size:.75rem;color:var(--text-muted,#64748b);margin-top:.625rem;">
                {{ __('ui.api_access_page.regenerate_hint') }}
            </p>

        </div>
    </div>

    {{-- Usage hint: how to actually send the key. Compact, one example each
         for header + curl so integrators don't have to guess the format. --}}
    <div class="card" style="max-width:820px;margin-top:1rem;">
        <div class="card-header">
            <div>
                <div class="card-title">{{ __('ui.api_access_page.usage_title') }}</div>
                <div class="card-subtitle">{{ __('ui.api_access_page.usage_subtitle') }}</div>
            </div>
            <div style="width:2.5rem;height:2.5rem;border-radius:.625rem;background:rgba(34,197,94,.1);display:flex;align-items:center;justify-content:center;color:#16a34a;flex-shrink:0;">
                <i class="ri-terminal-box-line" style="font-size:1.25rem;"></i>
            </div>
        </div>
        <div class="card-body">
            <p style="font-size:.875rem;color:var(--text-secondary,#334155);margin:0 0 .5rem;">
                {{ __('ui.api_access_page.usage_header_line') }}
            </p>
            <pre style="background:var(--bg-secondary,#f8fafc);border:1px solid var(--border-color,#e2e8f0);border-radius:.5rem;padding:.75rem 1rem;font-size:.8125rem;color:var(--text-primary,#1e293b);overflow-x:auto;margin:0 0 1rem;"><code>X-Api-Key: <span x-text="key"></span></code></pre>

            <p style="font-size:.875rem;color:var(--text-secondary,#334155);margin:0 0 .5rem;">
                {{ __('ui.api_access_page.usage_curl_line') }}
            </p>
            <pre style="background:var(--bg-secondary,#f8fafc);border:1px solid var(--border-color,#e2e8f0);border-radius:.5rem;padding:.75rem 1rem;font-size:.8125rem;color:var(--text-primary,#1e293b);overflow-x:auto;margin:0;"><code>curl -H "X-Api-Key: <span x-text="key"></span>" \
     {{ url('/api/instance') }}</code></pre>
        </div>
    </div>

</div>

@push('scripts')
<script>
function apiAccessPage() {
    return {
        key:     @json($apiKey),
        visible: false,
        copied:  false,
        loading: false,
        i18n: {
            copy:         @json(__('ui.api_access_page.copy')),
            copied:       @json(__('ui.api_access_page.copied')),
            regenerate:   @json(__('ui.api_access_page.regenerate')),
            regenerating: @json(__('ui.api_access_page.regenerating')),
        },

        copy() {
            navigator.clipboard.writeText(this.key);
            this.copied = true;
            setTimeout(() => this.copied = false, 2000);
        },

        async regenerate() {
            if (!confirm(@json(__('ui.api_access_page.regenerate_confirm')))) return;
            this.loading = true;
            try {
                const res = await fetch(@json(route(auth()->user()->routeNamePrefix() . '.api-access.regenerate')), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept':       'application/json',
                    },
                });
                const data = await res.json();
                if (data.api_key) {
                    this.key     = data.api_key;
                    this.visible = true;
                }
            } finally {
                this.loading = false;
            }
        },
    }
}
</script>
@endpush
@endsection
