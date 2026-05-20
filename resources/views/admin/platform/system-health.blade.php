@extends('layouts.admin')

@section('title', 'System Health')

@section('breadcrumb')
    <span>Platform</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>System Health</span>
@endsection

@section('content')
<div>
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">System Health</div>
            <div class="page-subtitle">Platform-level status and core service checks</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem">
        <div class="card">
            <div style="font-size:.8125rem;color:var(--text-muted)">Database</div>
            <div style="margin-top:.5rem;font-size:1.125rem;font-weight:700;color:{{ $health['database'] === 'ok' ? '#10b981' : '#ef4444' }}">
                {{ strtoupper($health['database']) }}
            </div>
        </div>
        <div class="card">
            <div style="font-size:.8125rem;color:var(--text-muted)">Cache</div>
            <div style="margin-top:.5rem;font-size:1.125rem;font-weight:700;color:{{ $health['cache'] === 'ok' ? '#10b981' : '#ef4444' }}">
                {{ strtoupper($health['cache']) }}
            </div>
        </div>
        <div class="card">
            <div style="font-size:.8125rem;color:var(--text-muted)">Total Tenants</div>
            <div style="margin-top:.5rem;font-size:1.125rem;font-weight:700;color:var(--text-primary)">{{ $health['tenants'] }}</div>
        </div>
        <div class="card">
            <div style="font-size:.8125rem;color:var(--text-muted)">Total Users</div>
            <div style="margin-top:.5rem;font-size:1.125rem;font-weight:700;color:var(--text-primary)">{{ $health['users'] }}</div>
        </div>
    </div>
</div>
@endsection
