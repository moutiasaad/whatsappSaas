@extends('layouts.admin')

@section('title', 'Edit Instance')

@section('breadcrumb')
    <a href="{{ route('admin.instances.index') }}" style="color:var(--text-secondary);text-decoration:none">Instances</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ $instance->name }}</span>
@endsection

@section('content')
<form action="{{ route('admin.instances.update', $instance) }}" method="POST" data-unsaved data-loading>
    @csrf
    @method('PUT')

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;margin-bottom:1.5rem">

        {{-- Left: Connection Fields --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">Edit Instance</div>
                    <div class="card-subtitle">Update connection settings for <strong>{{ $instance->name }}</strong></div>
                </div>
                <div style="display:flex;align-items:center;gap:.5rem">
                    <span class="status-dot {{ $instance->statusColor }}"></span>
                    <span style="font-size:.875rem;color:var(--text-secondary)">{{ ucfirst($instance->status) }}</span>
                </div>
            </div>
            <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:1.25rem">

                <div class="form-group">
                    <label class="form-label" for="name">Instance Name</label>
                    <input type="text" id="name" name="name"
                           value="{{ old('name', $instance->name) }}"
                           class="form-control @error('name') error @enderror">
                    @error('name') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Gateway Provider</label>
                    <div style="padding:.625rem .875rem;background:var(--page-bg);border:1px solid var(--card-border);border-radius:.625rem;font-size:.875rem;color:var(--text-secondary);display:flex;align-items:center;gap:.5rem">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
                        {{ ucfirst(str_replace('_', ' ', $instance->gateway)) }}
                        <span style="font-size:.75rem;color:var(--text-muted);margin-left:auto">Cannot be changed after creation</span>
                    </div>
                </div>

                <input type="hidden" name="gateway_url" value="{{ $instance->gateway_url ?: config('services.whatsapp.default_url') }}">
                <input type="hidden" name="gateway_api_key" value="{{ $instance->gateway_api_key ?: config('services.whatsapp.default_api_key') }}">
                <div class="form-hint" style="margin-top:-.75rem">
                    WhatsApp gateway settings are managed automatically from the server environment.
                </div>

                <div class="form-group">
                    <label class="form-label" for="team_id">Assigned Team</label>
                    <select id="team_id" name="team_id" class="form-control @error('team_id') error @enderror">
                        <option value="">No team (general pool)</option>
                        @foreach($teams as $team)
                        <option value="{{ $team->id }}"
                            {{ old('team_id', $instance->team_id ?? '') == $team->id ? 'selected' : '' }}>
                            {{ $team->name }}
                        </option>
                        @endforeach
                    </select>
                    @error('team_id') <div class="form-error">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        {{-- Right: Webhook, Phone, Danger Zone --}}
        <div style="display:flex;flex-direction:column;gap:1.5rem">

            {{-- Webhook URL --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Webhook URL</div>
                </div>
                <div style="padding:0 1.25rem 1.25rem">
                    <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.625rem">
                        <code id="webhook-url"
                              style="flex:1;font-family:monospace;font-size:.8125rem;background:var(--page-bg);border:1px solid var(--card-border);padding:.5rem .75rem;border-radius:.5rem;word-break:break-all;color:var(--text-secondary)">
                            {{ url('/api/webhooks/whatsapp/' . $instance->webhook_token) }}
                        </code>
                        <button type="button"
                                onclick="navigator.clipboard.writeText(document.getElementById('webhook-url').textContent.trim()).then(() => window.showToast?.('success', 'Copied', 'Webhook URL copied to clipboard'))"
                                class="btn btn-ghost btn-icon" title="Copy">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                        </button>
                    </div>
                    <div style="font-size:.75rem;color:var(--text-muted)">
                        Configure this URL as the webhook endpoint in your {{ ucfirst(str_replace('_', ' ', $instance->gateway)) }} dashboard.
                    </div>
                </div>
            </div>

            {{-- Connected Phone --}}
            @if($instance->phone_number)
            <div class="card">
                <div style="padding:1rem 1.25rem;display:flex;align-items:center;gap:.875rem">
                    <div style="width:2.25rem;height:2.25rem;border-radius:.625rem;background:rgba(16,185,129,.12);display:flex;align-items:center;justify-content:center;color:var(--brand);flex-shrink:0">
                        <svg width="15" height="15" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
                    </div>
                    <div>
                        <div style="font-size:.75rem;font-weight:600;color:var(--brand);text-transform:uppercase;letter-spacing:.04em">Connected Number</div>
                        <div style="font-size:.9375rem;font-weight:600;color:var(--text-primary);margin-top:.125rem">{{ $instance->phone_number }}</div>
                    </div>
                </div>
            </div>
            @endif

            {{-- Danger Zone --}}
            <div class="card">
                <div style="padding:1rem 1.25rem;border-left:3px solid #ef4444;border-radius:0 var(--radius-lg) var(--radius-lg) 0">
                    <div style="font-size:.875rem;font-weight:600;color:#ef4444;margin-bottom:.375rem">Danger Zone</div>
                    <div style="font-size:.8125rem;color:var(--text-muted);margin-bottom:.875rem">
                        Permanently delete this instance and all associated data.
                    </div>
                    <button type="button"
                            onclick="confirmDelete('{{ route('admin.instances.destroy', $instance) }}', { title: 'Delete {{ addslashes($instance->name) }}?', message: 'This will permanently remove the instance and all associated data.' })"
                            class="btn btn-danger btn-sm">
                        Delete Instance
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <div style="display:flex;justify-content:flex-end;gap:.5rem">
        <a href="{{ route('admin.instances.index') }}" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </div>
</form>
@endsection
