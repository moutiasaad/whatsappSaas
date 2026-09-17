@extends('layouts.admin')

@section('title', 'Messenger — Choose a Page')

@section('breadcrumb')
    <a href="{{ route('tenant_admin.messenger.settings') }}" style="color:var(--text-muted);text-decoration:none">Messenger settings</a>
    <span style="color:var(--text-muted);margin:0 .375rem">›</span>
    <span>Choose a Page</span>
@endsection

@section('content')
<div style="max-width:640px">
    <div style="margin-bottom:1.5rem">
        <h1 style="font-size:1.375rem;font-weight:700;color:var(--text-primary)">Which Page should Wavadesk manage?</h1>
        <p style="font-size:.875rem;color:var(--text-muted);margin-top:.25rem">
            You manage {{ count($pages) }} Pages. Pick one to connect. You can add more later.
        </p>
    </div>

    <form action="{{ route('tenant_admin.messenger.oauth.select') }}" method="POST" x-data="{ picked: null }">
        @csrf
        <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:1.25rem">
            @foreach($pages as $p)
                <label style="display:flex;gap:.75rem;align-items:center;padding:.875rem 1rem;border:2px solid var(--card-border, #e5e7eb);border-radius:10px;cursor:pointer;background:#fff"
                       :style="picked === '{{ $p['id'] }}' ? 'border-color:var(--brand);background:#ecfdf5' : ''">
                    <input type="radio" name="page_id" value="{{ $p['id'] }}" x-model="picked" required style="flex-shrink:0">
                    <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#1877f2,#0866ff);color:#fff;display:grid;place-items:center;font-weight:700;font-size:.875rem;flex-shrink:0">
                        {{ mb_strtoupper(mb_substr($p['name'] ?? '?', 0, 1)) }}
                    </div>
                    <div style="min-width:0;flex:1">
                        <div style="font-weight:600;font-size:.875rem">{{ $p['name'] ?? 'Unnamed Page' }}</div>
                        <div style="font-size:.75rem;color:var(--text-muted);margin-top:.125rem">
                            <span style="font-family:monospace">{{ $p['id'] }}</span>
                            @if(!empty($p['category']))
                                · {{ $p['category'] }}
                            @endif
                        </div>
                    </div>
                </label>
            @endforeach
        </div>
        <div style="display:flex;gap:.5rem;justify-content:flex-end">
            <a href="{{ route('tenant_admin.messenger.settings') }}" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary" :disabled="!picked">Connect this Page</button>
        </div>
    </form>
</div>
@endsection
