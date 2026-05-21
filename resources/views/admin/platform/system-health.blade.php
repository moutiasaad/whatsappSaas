@extends('layouts.admin')

@section('title', __('ui.platform_system_health_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.platform_system_health_page.breadcrumb_root') }}</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ __('ui.platform_system_health_page.breadcrumb') }}</span>
@endsection

@section('content')
<div>
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.platform_system_health_page.page_title') }}</div>
            <div class="page-subtitle">{{ __('ui.platform_system_health_page.subtitle') }}</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('super_admin.platform.system-health') }}" class="btn btn-outline">
                <i class="ri-refresh-line"></i> {{ __('ui.platform_system_health_page.refresh') }}
            </a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-shield-check-line"></i></div>
            <div class="stat-card-value">{{ $health['summary']['ok'] }}</div>
            <div class="stat-card-label">{{ __('ui.platform_system_health_page.healthy_checks') }}</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-card-icon"><i class="ri-alert-line"></i></div>
            <div class="stat-card-value">{{ $health['summary']['warning'] }}</div>
            <div class="stat-card-label">{{ __('ui.platform_system_health_page.warnings') }}</div>
        </div>
        <div class="stat-card red">
            <div class="stat-card-icon"><i class="ri-close-circle-line"></i></div>
            <div class="stat-card-value">{{ $health['summary']['error'] }}</div>
            <div class="stat-card-label">{{ __('ui.platform_system_health_page.errors') }}</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-card-icon"><i class="ri-timer-line"></i></div>
            <div class="stat-card-value">{{ $health['summary']['total'] }}</div>
            <div class="stat-card-label">{{ __('ui.platform_system_health_page.total_checks') }}</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 360px;gap:1.25rem;align-items:start;">
        <div class="card" style="padding:0;">
            <div class="card-header">
                <div class="card-title">{{ __('ui.platform_system_health_page.service_checks') }}</div>
                <div class="card-subtitle">{{ __('ui.platform_system_health_page.last_checked_prefix') }} {{ $health['checked_at']->diffForHumans() }}</div>
            </div>
            <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.platform_system_health_page.check') }}</th>
                            <th>{{ __('ui.platform_system_health_page.status') }}</th>
                            <th>{{ __('ui.platform_system_health_page.detail') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($health['checks'] as $check)
                            <tr>
                                <td style="font-weight:600;">{{ $check['label'] }}</td>
                                <td>
                                    @if($check['status'] === 'ok')
                                        <span class="badge badge-green"><i class="ri-checkbox-circle-line"></i> {{ __('ui.platform_system_health_page.ok') }}</span>
                                    @elseif($check['status'] === 'warning')
                                        <span class="badge badge-orange"><i class="ri-alert-line"></i> {{ __('ui.platform_system_health_page.warning') }}</span>
                                    @else
                                        <span class="badge badge-red"><i class="ri-close-circle-line"></i> {{ __('ui.platform_system_health_page.error') }}</span>
                                    @endif
                                </td>
                                <td style="color:var(--text-muted);">{{ $check['detail'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:1rem;">
            <div class="card">
                <div class="card-header" style="padding-bottom:12px;">
                    <div class="card-title">{{ __('ui.platform_system_health_page.platform_metrics') }}</div>
                </div>
                <div style="padding:0 18px 18px 18px;display:flex;flex-direction:column;gap:8px;font-size:13px;">
                    <div style="display:flex;justify-content:space-between;"><span style="color:var(--text-muted);">{{ __('ui.platform_system_health_page.tenants') }}</span><strong>{{ number_format($health['metrics']['tenants']) }}</strong></div>
                    <div style="display:flex;justify-content:space-between;"><span style="color:var(--text-muted);">{{ __('ui.platform_system_health_page.users') }}</span><strong>{{ number_format($health['metrics']['users']) }}</strong></div>
                    <div style="display:flex;justify-content:space-between;"><span style="color:var(--text-muted);">{{ __('ui.platform_system_health_page.plans') }}</span><strong>{{ number_format($health['metrics']['plans']) }}</strong></div>
                    <div style="display:flex;justify-content:space-between;"><span style="color:var(--text-muted);">{{ __('ui.platform_system_health_page.active_plans') }}</span><strong>{{ number_format($health['metrics']['active_plans']) }}</strong></div>
                    <div style="display:flex;justify-content:space-between;"><span style="color:var(--text-muted);">{{ __('ui.platform_system_health_page.conversations') }}</span><strong>{{ number_format($health['metrics']['conversations']) }}</strong></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="padding-bottom:12px;">
                    <div class="card-title">{{ __('ui.platform_system_health_page.runtime_snapshot') }}</div>
                </div>
                <div style="padding:0 18px 18px 18px;display:flex;flex-direction:column;gap:8px;font-size:13px;">
                    @foreach($health['runtime'] as $key => $value)
                        <div style="display:flex;justify-content:space-between;gap:10px;">
                            <span style="color:var(--text-muted);">{{ __('ui.platform_system_health_page.runtime_labels.' . $key) }}</span>
                            <strong style="text-align:right;">{{ $value }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
