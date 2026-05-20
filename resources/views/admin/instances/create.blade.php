@extends('layouts.admin')

@section('title', 'New Instance')

@section('breadcrumb')
    <a href="{{ route('admin.instances.index') }}" style="color:var(--text-secondary);text-decoration:none">Instances</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>New Instance</span>
@endsection

@section('content')
<form action="{{ route('admin.instances.store') }}" method="POST" data-loading>
    @csrf

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;margin-bottom:1.5rem">

        {{-- Left: Connection Fields --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">Create WhatsApp Instance</div>
                    <div class="card-subtitle">Configure a new WhatsApp connection for your team</div>
                </div>
            </div>
            <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:1.25rem">

                <div class="form-group">
                    <label class="form-label" for="name">
                        Instance Name <span style="color:#ef4444">*</span>
                    </label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}"
                           placeholder="e.g. Support — Morocco"
                           class="form-control @error('name') error @enderror"
                           required>
                    @error('name') <div class="form-error">{{ $message }}</div> @enderror
                    <div class="form-hint">A friendly name to identify this instance</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="gateway">
                        Gateway Provider <span style="color:#ef4444">*</span>
                    </label>
                    <select id="gateway" name="gateway" class="form-control @error('gateway') error @enderror" required>
                        <option value="">Select gateway…</option>
                        <option value="evolution_api" {{ old('gateway') === 'evolution_api' ? 'selected' : '' }}>Evolution API</option>
                        <option value="waha" {{ old('gateway') === 'waha' ? 'selected' : '' }}>WAHA</option>
                        <option value="cloud_api" {{ old('gateway') === 'cloud_api' ? 'selected' : '' }}>Meta Cloud API</option>
                    </select>
                    @error('gateway') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="gateway_url">
                        Gateway URL <span style="color:#ef4444">*</span>
                    </label>
                    <input type="url" id="gateway_url" name="gateway_url" value="{{ old('gateway_url') }}"
                           placeholder="https://your-evolution-api.com"
                           class="form-control @error('gateway_url') error @enderror"
                           required>
                    @error('gateway_url') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="gateway_api_key">
                        API Key <span style="color:#ef4444">*</span>
                    </label>
                    <div style="position:relative">
                        <input type="password" id="gateway_api_key" name="gateway_api_key"
                               placeholder="Your gateway API key"
                               class="form-control @error('gateway_api_key') error @enderror"
                               style="padding-right:2.75rem"
                               required>
                        <button type="button"
                                onclick="const i=document.getElementById('gateway_api_key');i.type=i.type==='password'?'text':'password'"
                                style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);padding:.25rem">
                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    @error('gateway_api_key') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="team_id">Assigned Team</label>
                    <select id="team_id" name="team_id" class="form-control @error('team_id') error @enderror">
                        <option value="">No team (conversations go to general pool)</option>
                        @foreach($teams as $team)
                        <option value="{{ $team->id }}" {{ old('team_id') == $team->id ? 'selected' : '' }}>
                            {{ $team->name }}
                        </option>
                        @endforeach
                    </select>
                    @error('team_id') <div class="form-error">{{ $message }}</div> @enderror
                    <div class="form-hint">Incoming conversations will be routed to this team's pool</div>
                </div>
            </div>
        </div>

        {{-- Right: Webhook Info + Setup Guide --}}
        <div style="display:flex;flex-direction:column;gap:1.5rem">

            {{-- Webhook URL Info --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Webhook URL</div>
                </div>
                <div style="padding:0 1.5rem 1.5rem">
                    <p style="font-size:.875rem;color:var(--text-secondary);margin-bottom:1rem">
                        After creating the instance, a unique webhook URL will be generated.
                        Configure it in your gateway dashboard to receive messages.
                    </p>
                    <div style="padding:.75rem;background:var(--page-bg);border:1px solid var(--card-border);border-radius:.625rem">
                        <div style="font-size:.75rem;color:var(--text-muted);margin-bottom:.25rem">Format</div>
                        <code style="font-size:.8125rem;color:var(--text-secondary);font-family:monospace;word-break:break-all">{{ url('/api/webhooks/whatsapp/{token}') }}</code>
                    </div>
                </div>
            </div>

            {{-- Setup Steps --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Setup Checklist</div>
                </div>
                <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:.75rem">
                    @foreach([
                        ['icon' => 'ri-server-line',    'title' => 'Deploy gateway',   'desc' => 'Set up Evolution API, WAHA, or use Meta Cloud API'],
                        ['icon' => 'ri-key-line',       'title' => 'Get your API key', 'desc' => 'From your gateway dashboard or Meta developer portal'],
                        ['icon' => 'ri-link',           'title' => 'Configure webhook','desc' => 'Point your gateway webhook to the generated URL'],
                        ['icon' => 'ri-qr-code-line',   'title' => 'Scan QR code',     'desc' => 'Link your WhatsApp account from the instance detail page'],
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

    {{-- Footer --}}
    <div style="display:flex;justify-content:flex-end;gap:.5rem">
        <a href="{{ route('admin.instances.index') }}" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary">Create Instance</button>
    </div>
</form>
@endsection
