@extends('layouts.admin')

@section('title', 'Messenger — Settings')

@section('breadcrumb')
    <a href="{{ route('tenant_admin.messenger.index') }}" style="color:var(--text-muted);text-decoration:none">Messenger</a>
    <span style="color:var(--text-muted);margin:0 .375rem">›</span>
    <span>Settings</span>
@endsection

@section('content')
<div style="max-width:900px">
    <div style="margin-bottom:1.5rem">
        <h1 style="font-size:1.375rem;font-weight:700;color:var(--text-primary)">Facebook Pages</h1>
        <p style="font-size:.875rem;color:var(--text-muted);margin-top:.25rem">
            Connect Facebook Pages so Messenger conversations land in your Wavadesk inbox.
        </p>
    </div>

    @if(session('success'))
        <div class="card" style="margin-bottom:1rem;padding:.875rem 1.125rem;border-left:3px solid #10b981;background:#ecfdf5">
            <div style="font-size:.875rem;color:#065f46">{{ session('success') }}</div>
        </div>
    @endif

    @if(session('error'))
        <div class="card" style="margin-bottom:1rem;padding:.875rem 1.125rem;border-left:3px solid #ef4444;background:#fef2f2">
            <div style="font-size:.875rem;color:#991b1b">{{ session('error') }}</div>
        </div>
    @endif

    @if(!$canConnect)
        <div class="card" style="margin-bottom:1rem;padding:1rem;border-left:3px solid #f59e0b;background:#fffbeb">
            <div style="font-size:.875rem;color:#78350f">
                <strong>Meta app not configured.</strong>
                The platform needs <code>META_APP_ID</code> and <code>META_APP_SECRET</code> set in the server's env before tenants can connect Pages. Contact your Wavadesk admin.
            </div>
        </div>
    @endif

    <div class="card" style="padding:1.25rem 1.5rem">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;gap:1rem;flex-wrap:wrap">
            <div>
                <div style="font-weight:600;font-size:.9375rem">Connected Pages</div>
                <div style="font-size:.75rem;color:var(--text-muted);margin-top:.125rem">
                    {{ $pages->count() }} {{ Str::plural('Page', $pages->count()) }} connected
                </div>
            </div>
            @if($canConnect)
                <a href="{{ route('tenant_admin.messenger.oauth.start') }}" class="btn btn-primary">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:.375rem"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                    Connect a Facebook Page
                </a>
            @endif
        </div>

        @if($pages->isEmpty())
            <div style="padding:2rem 1rem;text-align:center;color:var(--text-muted);font-size:.875rem;background:#f8fafc;border-radius:8px">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:.4;margin:0 auto .5rem"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                No Pages connected yet. Click <strong>Connect a Facebook Page</strong> above to get started.
            </div>
        @else
            <div style="display:flex;flex-direction:column;gap:.5rem">
                @foreach($pages as $page)
                    <div style="padding:.875rem 1rem;border:1px solid var(--card-border, #e5e7eb);border-radius:8px;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
                        <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#1877f2,#0866ff);color:#fff;display:grid;place-items:center;font-weight:700;font-size:.875rem;flex-shrink:0">
                            {{ mb_strtoupper(mb_substr($page->page_name, 0, 1)) }}
                        </div>
                        <div style="min-width:0;flex:1">
                            <div style="font-weight:600;font-size:.875rem">{{ $page->page_name }}</div>
                            <div style="font-size:.75rem;color:var(--text-muted);margin-top:.125rem">
                                <span style="font-family:monospace">{{ $page->page_id }}</span>
                                @if($page->subscribed_at)
                                    · connected {{ $page->subscribed_at->diffForHumans() }}
                                @endif
                            </div>
                            @if($page->disconnected_at)
                                <div style="margin-top:.25rem;font-size:.6875rem;color:#dc2626">
                                    Disconnected {{ $page->disconnected_at->diffForHumans() }}
                                    @if($page->disconnect_reason) — {{ $page->disconnect_reason }} @endif
                                </div>
                            @endif
                        </div>
                        <div style="display:flex;gap:.375rem;flex-shrink:0">
                            @if($page->disconnected_at)
                                <a href="{{ route('tenant_admin.messenger.oauth.start') }}" class="btn btn-sm btn-primary">Reconnect</a>
                            @else
                                <span class="badge badge-green" style="font-size:.6875rem">Live</span>
                            @endif
                            <form action="{{ route('tenant_admin.messenger.oauth.disconnect', $page->id) }}" method="POST" style="display:inline" onsubmit="return confirm('Disconnect {{ $page->page_name }}? Wavadesk will stop receiving Messenger events for this Page.')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline" style="color:#dc2626;border-color:#fca5a5">Disconnect</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="card" style="padding:1.25rem 1.5rem;margin-top:1rem;background:#f8fafc">
        <div style="font-weight:600;font-size:.9375rem;margin-bottom:.5rem">Before you connect</div>
        <ul style="font-size:.8125rem;color:var(--text-muted);margin:0;padding-inline-start:1.25rem;line-height:1.7">
            <li>You must be an admin of the Facebook Page you want to connect.</li>
            <li>Wavadesk requests these permissions: <code>pages_messaging</code>, <code>pages_manage_metadata</code>, <code>pages_show_list</code>, <code>pages_read_engagement</code>. All are required — declining any will fail the connection.</li>
            <li>Until Wavadesk's Meta app is approved by Facebook (App Review), only Facebook accounts that are added as Testers to the Wavadesk Meta app can trigger webhook events. Contact your Wavadesk admin to be added.</li>
            <li>Once connected, incoming messages to your Page appear in the <a href="{{ route('tenant_admin.messenger.index') }}" style="color:var(--brand)">Messenger inbox</a> within 2 seconds.</li>
        </ul>
    </div>
</div>
@endsection
