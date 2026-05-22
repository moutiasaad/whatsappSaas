@extends('layouts.admin')

@section('title', 'Webhook Events — ' . $instance->name)

@section('breadcrumb')
    <a href="{{ route(auth()->user()->routeNamePrefix() . '.instances.index') }}">Instances</a>
    <span>/ {{ $instance->name }} / Webhook Events</span>
@endsection

@section('content')
<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">Webhook Events</div>
        <div class="page-subtitle">{{ $instance->name }} — derniers {{ $events->count() }} événements reçus</div>
    </div>
    <div class="page-header-actions">
        <a href="{{ route(auth()->user()->routeNamePrefix() . '.instances.index') }}" class="btn btn-outline">
            <i class="ri-arrow-left-line"></i> Retour
        </a>
        <button onclick="location.reload()" class="btn btn-primary">
            <i class="ri-refresh-line"></i> Actualiser
        </button>
    </div>
</div>

{{-- Status summary --}}
<div class="stats-grid" style="margin-bottom:1.5rem">
    <div class="stat-card">
        <div class="stat-card-icon"><i class="ri-broadcast-line"></i></div>
        <div class="stat-card-value">{{ $events->count() }}</div>
        <div class="stat-card-label">Événements reçus</div>
    </div>
    <div class="stat-card {{ $events->whereNull('processed_at')->count() > 0 ? 'orange' : '' }}">
        <div class="stat-card-icon"><i class="ri-time-line"></i></div>
        <div class="stat-card-value">{{ $events->whereNull('processed_at')->whereNull('error')->count() }}</div>
        <div class="stat-card-label">En attente (queue non lancée)</div>
    </div>
    <div class="stat-card {{ $events->whereNotNull('error')->count() > 0 ? 'red' : '' }}">
        <div class="stat-card-icon"><i class="ri-error-warning-line"></i></div>
        <div class="stat-card-value">{{ $events->whereNotNull('error')->count() }}</div>
        <div class="stat-card-label">Erreurs</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><i class="ri-check-double-line"></i></div>
        <div class="stat-card-value">{{ $events->whereNotNull('processed_at')->count() }}</div>
        <div class="stat-card-label">Traités</div>
    </div>
</div>

@if($events->whereNull('processed_at')->whereNull('error')->count() > 0)
<div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:.75rem;padding:1rem 1.25rem;margin-bottom:1.5rem;display:flex;align-items:flex-start;gap:.75rem">
    <i class="ri-alert-line" style="color:#d97706;margin-top:.1rem;flex-shrink:0"></i>
    <div>
        <strong style="color:#92400e">Queue worker non lancée</strong><br>
        <span style="color:#92400e;font-size:.875rem">Les événements arrivent mais ne sont pas traités. Sur le serveur de production, lancez :</span><br>
        <code style="background:#fde68a;padding:.2rem .5rem;border-radius:.35rem;font-size:.8rem;margin-top:.35rem;display:inline-block">php artisan queue:work --queue=whatsapp,default</code>
    </div>
</div>
@endif

@if($events->isEmpty())
    <div class="card">
        <div class="empty-state" style="padding:3rem">
            <div class="empty-state-icon"><i class="ri-broadcast-line" style="font-size:2rem"></i></div>
            <h4>Aucun événement reçu</h4>
            <p>Le webhook n'a encore reçu aucun événement de la passerelle WhatsApp.</p>
        </div>
    </div>
@else
    <div class="card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Reçu</th>
                        <th>Statut</th>
                        <th>Expéditeur (extrait)</th>
                        <th>Payload</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($events as $event)
                    @php
                        $payload = is_array($event->payload) ? $event->payload : json_decode($event->payload, true) ?? [];
                        // iStoreBox sends data as a list — unwrap first element
                        $d = $payload['data'] ?? [];
                        $d0 = (is_array($d) && isset($d[0]) && is_array($d[0])) ? $d[0] : $d;
                        $from = data_get($d0, 'key.remoteJid')
                            ?? data_get($d0, 'remoteJid')
                            ?? data_get($d0, 'from')
                            ?? '—';
                        $msgText = data_get($d0, 'message.conversation')
                            ?? data_get($d0, 'message.extendedTextMessage.text')
                            ?? data_get($d0, 'text')
                            ?? '—';
                    @endphp
                    <tr>
                        <td style="color:var(--text-muted);font-size:.8rem">{{ $event->id }}</td>
                        <td>
                            <span class="badge {{ str_contains($event->event_type, 'message') ? 'badge-green' : 'badge-gray' }}">
                                {{ $event->event_type }}
                            </span>
                        </td>
                        <td style="font-size:.8rem;white-space:nowrap">{{ $event->created_at->diffForHumans() }}</td>
                        <td>
                            @if($event->error)
                                <span class="badge badge-red" title="{{ $event->error }}">Erreur</span>
                            @elseif($event->processed_at)
                                <span class="badge badge-green">Traité</span>
                            @else
                                <span class="badge badge-orange">En attente</span>
                            @endif
                        </td>
                        <td style="font-size:.8rem">
                            <span style="font-family:monospace">{{ $from }}</span>
                            @if($msgText !== '—')
                                <div style="color:var(--text-muted);margin-top:.15rem">{{ Str::limit($msgText, 40) }}</div>
                            @endif
                        </td>
                        <td>
                            <details>
                                <summary style="cursor:pointer;font-size:.8rem;color:var(--text-muted)">Voir JSON</summary>
                                <pre style="margin-top:.5rem;padding:.75rem;background:#0f172a;color:#e2e8f0;border-radius:.5rem;font-size:.7rem;overflow-x:auto;max-height:300px;white-space:pre-wrap;word-break:break-all;border:1px solid #334155">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </details>
                            @if($event->error)
                                <div style="margin-top:.35rem;font-size:.75rem;color:#ef4444">{{ Str::limit($event->error, 120) }}</div>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
