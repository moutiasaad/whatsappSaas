@extends('layouts.admin')

@section('title', $instance->name)

@section('breadcrumb')
    @php $panelPrefix = auth()->user()->routeNamePrefix(); @endphp
    <a href="{{ route($panelPrefix . '.instances.index') }}" style="color:var(--text-secondary);text-decoration:none">{{ __('ui.instances_page.breadcrumb') }}</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ $instance->name }}</span>
@endsection

@section('content')
@php
    $panelPrefix = auth()->user()->routeNamePrefix();
    $isSuperAdmin = auth()->user()->role === 'super_admin';

    $statusBadge = match($instance->status) {
        'connected'   => 'badge badge-green',
        'qr_pending', 'connecting' => 'badge badge-orange',
        'banned'      => 'badge badge-red',
        default       => 'badge badge-gray',
    };
    $statusDot = match($instance->status) {
        'connected'   => 'green',
        'qr_pending', 'connecting' => 'yellow',
        default       => 'red',
    };
    $statusLabels = __('ui.instances_page.status_labels');
    $statusLabel  = $statusLabels[$instance->status] ?? str_replace('_', ' ', $instance->status);
@endphp

<div>
    {{-- Page Header --}}
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ $instance->name }}</div>
            <div class="page-subtitle">
                <span class="{{ $statusBadge }}" style="display:inline-flex;align-items:center;gap:.35rem;font-size:.75rem">
                    <span class="status-dot {{ $statusDot }}" style="width:.45rem;height:.45rem"></span>
                    {{ $statusLabel }}
                </span>
                @if($instance->phone_number)
                    <span style="margin-left:.75rem;color:var(--text-muted);font-size:.8125rem">
                        <i class="ri-phone-line" style="margin-right:.25rem"></i>{{ $instance->phone_number }}
                    </span>
                @endif
            </div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route($panelPrefix . '.instances.index') }}" class="btn btn-outline">
                <i class="ri-arrow-left-line"></i> {{ __('ui.back') }}
            </a>
            @if($showGatewayInternals ?? false)
            <a href="{{ route($panelPrefix . '.instances.webhook-events', $instance) }}" class="btn btn-outline">
                <i class="ri-flashlight-line"></i> Webhook Events
            </a>
            @endif
            <a href="{{ route($panelPrefix . '.instances.edit', $instance) }}" class="btn btn-primary">
                <i class="ri-pencil-line"></i> {{ __('ui.edit') }}
            </a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem">

        {{-- Connection Info --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="ri-smartphone-line" style="margin-right:.5rem;color:#25d366"></i>{{ __('ui.instances_page.instance') }}</div>
            </div>
            <div class="card-body">
                <dl style="display:grid;grid-template-columns:auto 1fr;gap:.5rem 1.5rem;font-size:.875rem;margin:0">
                    <dt style="color:var(--text-muted);white-space:nowrap">{{ __('ui.instances_page.status') }}</dt>
                    <dd style="margin:0">
                        <span class="{{ $statusBadge }}" style="display:inline-flex;align-items:center;gap:.35rem">
                            <span class="status-dot {{ $statusDot }}" style="width:.4rem;height:.4rem"></span>
                            {{ $statusLabel }}
                        </span>
                    </dd>

                    <dt style="color:var(--text-muted)">{{ __('ui.instances_page.phone') }}</dt>
                    <dd style="margin:0">{{ $instance->phone_number ?: '—' }}</dd>

                    @if($isSuperAdmin && $instance->tenant)
                    <dt style="color:var(--text-muted)">{{ __('ui.platform_tenants_page.tenant') }}</dt>
                    <dd style="margin:0">{{ $instance->tenant->name }}</dd>
                    @endif

                    @if($instance->team)
                    <dt style="color:var(--text-muted)">{{ __('ui.teams_page.team') }}</dt>
                    <dd style="margin:0">{{ $instance->team->name }}</dd>
                    @endif

                    @if($showGatewayInternals ?? false)
                    <dt style="color:var(--text-muted)">{{ __('ui.instances_page.gateway') }}</dt>
                    <dd style="margin:0">{{ ucwords(str_replace('_', ' ', $instance->gateway ?? '—')) }}</dd>

                    <dt style="color:var(--text-muted)">Instance ID</dt>
                    <dd style="margin:0;font-family:monospace;font-size:.8125rem">{{ $instance->gateway_instance_id ?: '—' }}</dd>
                    @endif

                    <dt style="color:var(--text-muted)">{{ __('ui.instances_page.activity') }}</dt>
                    <dd style="margin:0">
                        @if($instance->last_message_at)
                            {{ $instance->last_message_at->diffForHumans() }}
                        @else
                            {{ __('ui.instances_page.never') }}
                        @endif
                    </dd>

                    <dt style="color:var(--text-muted)">{{ __('ui.instances_page.status') }} sync</dt>
                    <dd style="margin:0">
                        @if($instance->last_status_at)
                            {{ $instance->last_status_at->diffForHumans() }}
                        @else
                            —
                        @endif
                    </dd>
                </dl>
            </div>
        </div>

        @if($showGatewayInternals ?? false)
        {{-- Webhook Config --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="ri-flashlight-line" style="margin-right:.5rem;color:#0f7e7a"></i>Webhook</div>
            </div>
            <div class="card-body">
                <dl style="display:grid;grid-template-columns:auto 1fr;gap:.5rem 1.5rem;font-size:.875rem;margin:0">
                    <dt style="color:var(--text-muted)">Statut</dt>
                    <dd style="margin:0">
                        @if($instance->webhook_enabled)
                            <span class="badge badge-green" style="display:inline-flex;align-items:center;gap:.35rem">
                                <span style="width:.4rem;height:.4rem;border-radius:50%;background:#22c55e;flex-shrink:0"></span>
                                Enregistré
                            </span>
                        @else
                            <span class="badge badge-gray" style="display:inline-flex;align-items:center;gap:.35rem">
                                <span style="width:.4rem;height:.4rem;border-radius:50%;background:#94a3b8;flex-shrink:0"></span>
                                Non enregistré
                            </span>
                        @endif
                    </dd>

                    <dt style="color:var(--text-muted)">URL</dt>
                    <dd style="margin:0;font-family:monospace;font-size:.75rem;word-break:break-all;color:var(--text-secondary)">
                        {{ $instance->webhook_url ?: '—' }}
                    </dd>

                    <dt style="color:var(--text-muted)">Token</dt>
                    <dd style="margin:0;font-family:monospace;font-size:.75rem;color:var(--text-muted)">
                        {{ $instance->webhook_token ? substr($instance->webhook_token, 0, 12) . '…' : '—' }}
                    </dd>

                    <dt style="color:var(--text-muted)">Dernier event</dt>
                    <dd style="margin:0">
                        @php $lastEvent = $recentEvents->first(); @endphp
                        @if($lastEvent)
                            {{ $lastEvent->created_at->diffForHumans() }}
                        @else
                            {{ __('ui.instances_page.never') }}
                        @endif
                    </dd>

                    <dt style="color:var(--text-muted)">Events non traités</dt>
                    <dd style="margin:0">
                        @php $pending = $recentEvents->whereNull('processed_at')->count(); @endphp
                        @if($pending > 0)
                            <span style="color:#f59e0b;font-weight:600">⚠ {{ $pending }}</span>
                        @else
                            <span style="color:#22c55e">✓ 0</span>
                        @endif
                    </dd>
                </dl>
            </div>
        </div>
        @endif

    </div>

    @if($showGatewayInternals ?? false)
    {{-- Gateway Config --}}
    <div class="card" style="margin-bottom:1.5rem">
        <div class="card-header">
            <div class="card-title"><i class="ri-server-line" style="margin-right:.5rem;color:var(--text-muted)"></i>Configuration Gateway</div>
        </div>
        <div class="card-body">
            <dl style="display:grid;grid-template-columns:auto 1fr;gap:.5rem 1.5rem;font-size:.875rem;margin:0">
                <dt style="color:var(--text-muted)">Gateway URL</dt>
                <dd style="margin:0;font-family:monospace;font-size:.8125rem;word-break:break-all">
                    {{ $instance->effectiveGatewayUrl() ?: '—' }}
                </dd>

                <dt style="color:var(--text-muted)">API Key</dt>
                <dd style="margin:0;font-family:monospace;font-size:.8125rem;color:var(--text-muted)">
                    @php $key = $instance->effectiveGatewayApiKey(); @endphp
                    {{ $key ? substr($key, 0, 8) . str_repeat('•', 12) : '—' }}
                </dd>
            </dl>
        </div>
    </div>

    {{-- Recent Webhook Events --}}
    <div class="card">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
            <div class="card-title"><i class="ri-history-line" style="margin-right:.5rem"></i>Derniers Webhook Events</div>
            <a href="{{ route($panelPrefix . '.instances.webhook-events', $instance) }}" class="btn btn-outline btn-sm">
                Voir tout <i class="ri-arrow-right-line"></i>
            </a>
        </div>
        <div class="card-body" style="padding:0">
            @if($recentEvents->isEmpty())
                <div style="padding:2rem;text-align:center;color:var(--text-muted);font-size:.875rem">
                    <i class="ri-inbox-line" style="font-size:1.5rem;display:block;margin-bottom:.5rem"></i>
                    Aucun événement reçu
                </div>
            @else
                <div class="data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Statut</th>
                                <th>Reçu</th>
                                <th>Erreur</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentEvents as $event)
                            <tr>
                                <td>
                                    <span style="font-family:monospace;font-size:.8125rem;color:var(--text-secondary);background:var(--input-bg,rgba(0,0,0,.04));border:1px solid var(--card-border);padding:.15rem .45rem;border-radius:.375rem">
                                        {{ $event->event_type }}
                                    </span>
                                </td>
                                <td>
                                    @if($event->processed_at)
                                        <span class="badge badge-green" style="font-size:.7rem">Traité</span>
                                    @elseif($event->error)
                                        <span class="badge badge-red" style="font-size:.7rem">Erreur</span>
                                    @else
                                        <span class="badge badge-orange" style="font-size:.7rem">En attente</span>
                                    @endif
                                </td>
                                <td style="font-size:.8125rem;color:var(--text-muted)">{{ $event->created_at->diffForHumans() }}</td>
                                <td style="font-size:.8125rem;color:#ef4444;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                    {{ $event->error ?: '—' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
    @endif

</div>

@endsection
