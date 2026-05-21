@extends('layouts.admin')

@section('title', __('ui.instance_create_page.title'))

@section('breadcrumb')
    <a href="{{ route('admin.instances.index') }}" style="color:var(--text-secondary);text-decoration:none">{{ __('ui.instances_page.instances') }}</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ __('ui.instance_create_page.title') }}</span>
@endsection

@section('content')
<form action="{{ route('admin.instances.store') }}" method="POST" data-loading>
    @csrf

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;margin-bottom:1.5rem">
        <div class="card" style="overflow:visible">
            <div class="card-header">
                <div>
                    <div class="card-title">{{ __('ui.instance_create_page.card_title') }}</div>
                    <div class="card-subtitle">{{ __('ui.instance_create_page.card_subtitle') }}</div>
                </div>
            </div>
            <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:1.25rem">
                <div class="form-group">
                    <label class="form-label" for="name">{{ __('ui.instance_create_page.instance_name') }}</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}"
                           placeholder="{{ __('ui.instance_create_page.instance_name_placeholder') }}"
                           class="form-control @error('name') error @enderror">
                    @error('name') <div class="form-error">{{ $message }}</div> @enderror
                    <div class="form-hint">{{ __('ui.instance_create_page.instance_name_hint') }}</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="gateway">{{ __('ui.instance_create_page.gateway_provider') }}</label>
                    <select id="gateway" name="gateway" class="form-control @error('gateway') error @enderror">
                        <option value="">{{ __('ui.instance_create_page.select_gateway') }}</option>
                        <option value="evolution_api" {{ old('gateway') === 'evolution_api' ? 'selected' : '' }}>{{ __('ui.instance_create_page.gateways.evolution_api') }}</option>
                        <option value="waha" {{ old('gateway') === 'waha' ? 'selected' : '' }}>{{ __('ui.instance_create_page.gateways.waha') }}</option>
                        <option value="cloud_api" {{ old('gateway') === 'cloud_api' ? 'selected' : '' }}>{{ __('ui.instance_create_page.gateways.cloud_api') }}</option>
                    </select>
                    @error('gateway') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <input type="hidden" name="gateway_url" value="{{ config('services.whatsapp.default_url') }}">
                <input type="hidden" name="gateway_api_key" value="{{ config('services.whatsapp.default_api_key') }}">
                <div class="form-hint" style="margin-top:-.75rem">{{ __('ui.instance_create_page.gateway_auto_hint') }}</div>

                <div class="form-group" style="overflow:visible">
                    <label class="form-label" for="team_id">{{ __('ui.instance_create_page.assigned_team') }}</label>
                    <select id="team_id" name="team_id" class="form-control @error('team_id') error @enderror">
                        <option value="">{{ __('ui.instance_create_page.no_team') }}</option>
                        @foreach($teams as $team)
                        <option value="{{ $team->id }}" {{ old('team_id') == $team->id ? 'selected' : '' }}>
                            {{ $team->name }}
                        </option>
                        @endforeach
                    </select>
                    @error('team_id') <div class="form-error">{{ $message }}</div> @enderror
                    <div class="form-hint">{{ __('ui.instance_create_page.assigned_team_hint') }}</div>
                </div>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:1.5rem">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">{{ __('ui.instance_create_page.webhook_url') }}</div>
                </div>
                <div style="padding:0 1.5rem 1.5rem">
                    <p style="font-size:.875rem;color:var(--text-secondary);margin-bottom:1rem">
                        {{ __('ui.instance_create_page.webhook_url_hint') }}
                    </p>
                    <div style="padding:.75rem;background:var(--page-bg);border:1px solid var(--card-border);border-radius:.625rem">
                        <div style="font-size:.75rem;color:var(--text-muted);margin-bottom:.25rem">{{ __('ui.instance_create_page.format') }}</div>
                        <code style="font-size:.8125rem;color:var(--text-secondary);font-family:monospace;word-break:break-all">{{ url('/api/webhooks/whatsapp/{token}') }}</code>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">{{ __('ui.instance_create_page.setup_checklist') }}</div>
                </div>
                <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:.75rem">
                    @foreach([
                        ['icon' => 'ri-server-line',    'title' => __('ui.instance_create_page.steps.deploy_gateway.title'),    'desc' => __('ui.instance_create_page.steps.deploy_gateway.desc')],
                        ['icon' => 'ri-key-line',       'title' => __('ui.instance_create_page.steps.get_api_key.title'),     'desc' => __('ui.instance_create_page.steps.get_api_key.desc')],
                        ['icon' => 'ri-link',           'title' => __('ui.instance_create_page.steps.configure_webhook.title'),'desc' => __('ui.instance_create_page.steps.configure_webhook.desc')],
                        ['icon' => 'ri-qr-code-line',   'title' => __('ui.instance_create_page.steps.scan_qr.title'),        'desc' => __('ui.instance_create_page.steps.scan_qr.desc')],
                    ] as $step)
                    <div style="display:flex;align-items:flex-start;gap:.75rem">
                        <div style="width:2rem;height:2rem;border-radius:.5rem;background:rgba(16,185,129,.1);display:flex;align-items:center;justify-content:center;color:var(--brand);flex-shrink:0">
                            <i class="{{ $step['icon'] }}"></i>
                        </div>
                        <div>
                            <div style="font-size:.875rem;font-weight:600;color:var(--text-primary)">{{ $step['title'] }}</div>
                            <div style="font-size:.75rem;color:var(--text-muted);margin-top:.125rem">{{ $step['desc'] }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:.5rem">
        <a href="{{ route('admin.instances.index') }}" class="btn btn-outline">{{ __('ui.cancel') }}</a>
        <button type="submit" class="btn btn-primary">{{ __('ui.instance_create_page.create_instance') }}</button>
    </div>
</form>
@endsection
