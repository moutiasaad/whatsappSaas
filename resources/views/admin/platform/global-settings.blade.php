@extends('layouts.admin')

@section('title', 'Global Settings')

@section('breadcrumb')
    <span>Platform</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>Global Settings</span>
@endsection

@section('content')
<div>
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">Global Settings</div>
            <div class="page-subtitle">Platform-level runtime configuration overview</div>
        </div>
    </div>

    <div class="card" style="padding:1rem">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem">
            @foreach($settings as $key => $value)
                <div style="padding:.875rem;border:1px solid var(--card-border);border-radius:.75rem;background:var(--page-bg)">
                    <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em">{{ str_replace('_', ' ', $key) }}</div>
                    <div style="font-size:.9375rem;font-weight:600;color:var(--text-primary);margin-top:.25rem">{{ $value ?: '—' }}</div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
