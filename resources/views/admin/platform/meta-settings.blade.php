@extends('layouts.admin')

@section('title', 'Meta / Messenger Settings')

@section('breadcrumb')
    <span style="color:var(--text-muted)">Platform</span>
    <span style="color:var(--text-muted);margin:0 .375rem">›</span>
    <span>Meta / Messenger</span>
@endsection

@section('content')
<div style="max-width:820px">
    <div style="margin-bottom:1.5rem">
        <h1 style="font-size:1.375rem;font-weight:700;color:var(--text-primary)">Meta / Messenger settings</h1>
        <p style="font-size:.875rem;color:var(--text-muted);margin-top:.25rem;line-height:1.55">
            Credentials for the Facebook Messenger channel. Applies platform-wide — every tenant's Messenger integration reads these values.
            Secrets are encrypted with the app's <code>APP_KEY</code> before storage.
        </p>
    </div>

    @if(session('success'))
        <div class="card" style="margin-bottom:1rem;padding:.875rem 1.125rem;border-left:3px solid #10b981;background:#ecfdf5">
            <div style="font-size:.875rem;color:#065f46">{{ session('success') }}</div>
        </div>
    @endif

    @if($errors->any())
        <div class="card" style="margin-bottom:1rem;padding:.875rem 1.125rem;border-left:3px solid #ef4444;background:#fef2f2">
            <div style="font-weight:600;font-size:.875rem;color:#991b1b;margin-bottom:.25rem">Please fix these:</div>
            <ul style="margin:0;padding-inline-start:1.25rem;font-size:.8125rem;color:#7f1d1d;line-height:1.55">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('super_admin.platform.meta-settings.update') }}" method="POST" data-loading>
        @csrf
        @method('PUT')

        {{-- ─── Public values ─────────────────────────────────────────── --}}
        <div class="card" style="padding:1.25rem 1.5rem;margin-bottom:1rem">
            <div style="font-weight:600;font-size:.9375rem;margin-bottom:.125rem">Meta App</div>
            <p style="font-size:.75rem;color:var(--text-muted);margin-bottom:1.125rem">
                Public values from <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener" style="color:var(--brand)">developers.facebook.com</a> → your app → App Settings → Basic.
            </p>

            <div class="form-group" style="margin-bottom:1rem">
                <label class="form-label">
                    App ID
                    @if($appIdInDb) <span class="badge badge-green" style="font-size:.6875rem;margin-inline-start:.375rem">Stored in DB</span>
                    @elseif($appId !== '') <span class="badge badge-gray" style="font-size:.6875rem;margin-inline-start:.375rem">From env</span>
                    @endif
                </label>
                <input type="text" name="app_id" value="{{ old('app_id', $appId) }}"
                       placeholder="1016732254755210"
                       inputmode="numeric"
                       autocomplete="off"
                       class="form-control @error('app_id') error @enderror"
                       style="font-family:monospace">
                <div style="font-size:.75rem;color:var(--text-muted);margin-top:.375rem">
                    15-16 digit numeric. Safe to expose — it's public.
                </div>
                @error('app_id') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">
                    Graph API version
                    @if($graphVersionInDb) <span class="badge badge-green" style="font-size:.6875rem;margin-inline-start:.375rem">Stored in DB</span>
                    @else <span class="badge badge-gray" style="font-size:.6875rem;margin-inline-start:.375rem">Default</span>
                    @endif
                </label>
                <input type="text" name="graph_version" value="{{ old('graph_version', $graphVersion) }}"
                       placeholder="v21.0"
                       autocomplete="off"
                       class="form-control @error('graph_version') error @enderror"
                       style="font-family:monospace;max-width:200px">
                <div style="font-size:.75rem;color:var(--text-muted);margin-top:.375rem">
                    Format <code>vNN.N</code>. Bump when Meta releases a new stable version. See <a href="https://developers.facebook.com/docs/graph-api/changelog/versions" target="_blank" rel="noopener" style="color:var(--brand)">Meta's changelog</a>.
                </div>
                @error('graph_version') <div class="form-error">{{ $message }}</div> @enderror
            </div>
        </div>

        {{-- ─── Secret values ─────────────────────────────────────────── --}}
        <div class="card" style="padding:1.25rem 1.5rem;margin-bottom:1rem;border-left:3px solid #f59e0b">
            <div style="font-weight:600;font-size:.9375rem;margin-bottom:.125rem">Secrets</div>
            <p style="font-size:.75rem;color:var(--text-muted);margin-bottom:1.125rem;line-height:1.55">
                Treat these like passwords. They're encrypted with <code>APP_KEY</code> before storage.
                Leave a field blank to keep the current value unchanged.
                <br>Rotating the App Secret does not invalidate existing Page tokens — safe to rotate at any time.
                Rotating the Verify Token requires re-pasting the new value into Meta's dashboard on the same call.
            </p>

            <div class="form-group" style="margin-bottom:1rem">
                <label class="form-label">
                    App Secret
                    @if($appSecretSet) <span class="badge badge-green" style="font-size:.6875rem;margin-inline-start:.375rem">Configured</span>
                    @else <span class="badge badge-orange" style="font-size:.6875rem;margin-inline-start:.375rem">Not set</span>
                    @endif
                    @if($appSecretInDb) <span class="badge badge-gray" style="font-size:.6875rem;margin-inline-start:.375rem">DB</span>
                    @elseif($appSecretSet) <span class="badge badge-gray" style="font-size:.6875rem;margin-inline-start:.375rem">env</span>
                    @endif
                </label>
                @if($appSecretSet)
                    <div style="font-family:monospace;color:var(--text-muted);font-size:.8125rem;padding:.375rem 0;margin-bottom:.375rem">Current: <span style="color:var(--text-primary)">{{ $appSecretMask }}</span></div>
                @endif
                <input type="password" name="app_secret" value=""
                       placeholder="{{ $appSecretSet ? 'Leave blank to keep current' : 'Paste your Meta App Secret' }}"
                       autocomplete="new-password"
                       class="form-control @error('app_secret') error @enderror"
                       style="font-family:monospace">
                <div style="font-size:.75rem;color:var(--text-muted);margin-top:.375rem">
                    Get from your Meta app → App Settings → Basic → App Secret → Show.
                </div>
                @error('app_secret') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">
                    Webhook Verify Token
                    @if($verifyTokenSet) <span class="badge badge-green" style="font-size:.6875rem;margin-inline-start:.375rem">Configured</span>
                    @else <span class="badge badge-orange" style="font-size:.6875rem;margin-inline-start:.375rem">Not set</span>
                    @endif
                    @if($verifyTokenInDb) <span class="badge badge-gray" style="font-size:.6875rem;margin-inline-start:.375rem">DB</span>
                    @elseif($verifyTokenSet) <span class="badge badge-gray" style="font-size:.6875rem;margin-inline-start:.375rem">env</span>
                    @endif
                </label>
                @if($verifyTokenSet)
                    <div style="font-family:monospace;color:var(--text-muted);font-size:.8125rem;padding:.375rem 0;margin-bottom:.375rem">Current: <span style="color:var(--text-primary)">{{ $verifyTokenMask }}</span></div>
                @endif
                <input type="password" name="verify_token" value=""
                       placeholder="{{ $verifyTokenSet ? 'Leave blank to keep current' : 'Random string 16+ chars' }}"
                       autocomplete="new-password"
                       class="form-control @error('verify_token') error @enderror"
                       style="font-family:monospace">
                <div style="font-size:.75rem;color:var(--text-muted);margin-top:.375rem">
                    Any random string 16+ characters. Paste the SAME value into Meta's Messenger webhook config.
                    Generate one: <code>bin2hex(random_bytes(24))</code>.
                </div>
                @error('verify_token') <div class="form-error">{{ $message }}</div> @enderror
            </div>
        </div>

        <div style="display:flex;gap:.5rem;justify-content:flex-end">
            <a href="{{ route('super_admin.dashboard') }}" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Meta settings</button>
        </div>
    </form>

    {{-- ─── Info panel ────────────────────────────────────────────────── --}}
    <div class="card" style="padding:1rem 1.25rem;margin-top:1.25rem;background:#f8fafc">
        <div style="font-weight:600;font-size:.9375rem;margin-bottom:.5rem">How this integrates</div>
        <ul style="font-size:.8125rem;color:var(--text-muted);margin:0;padding-inline-start:1.25rem;line-height:1.75">
            <li>DB values take precedence over <code>.env</code>. Empty DB values fall back to env — a fresh install works without a save here.</li>
            <li>Changes take effect on the <strong>next</strong> Meta API call. No restart, no cache-clear.</li>
            <li>Webhook URL to paste in Meta: <code>https://app.wavadesk.com/api/webhooks/messenger</code></li>
            <li>OAuth redirect URI to whitelist in Meta's Facebook Login for Business: <code>https://app.wavadesk.com/tenant-admin/messenger/oauth/callback</code></li>
            <li>Rotating the App Secret does not invalidate existing Page tokens — tenants keep receiving/sending messages during the rotation. Rotating the Verify Token requires updating Meta's Webhook settings on the same call.</li>
        </ul>
    </div>
</div>
@endsection
