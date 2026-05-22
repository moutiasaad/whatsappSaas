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

{{-- Reprocess result flash --}}
@if(session('reprocess_result'))
    @php $res = session('reprocess_result'); @endphp
    <div style="background:{{ $res['status']==='ok' ? '#dcfce7' : '#fee2e2' }};border:1px solid {{ $res['status']==='ok' ? '#22c55e' : '#ef4444' }};border-radius:.75rem;padding:1rem 1.25rem;margin-bottom:1.5rem;display:flex;gap:.75rem;align-items:center">
        <i class="ri-{{ $res['status']==='ok' ? 'check-line' : 'error-warning-line' }}" style="color:{{ $res['status']==='ok' ? '#16a34a' : '#dc2626' }};flex-shrink:0"></i>
        <span style="color:{{ $res['status']==='ok' ? '#15803d' : '#b91c1c' }};font-size:.875rem">{{ $res['message'] }}</span>
    </div>
@endif

{{-- Stats --}}
<div class="stats-grid" style="margin-bottom:1.5rem">
    <div class="stat-card">
        <div class="stat-card-icon"><i class="ri-broadcast-line"></i></div>
        <div class="stat-card-value">{{ $events->count() }}</div>
        <div class="stat-card-label">Événements reçus</div>
    </div>
    <div class="stat-card {{ $events->whereNull('processed_at')->count() > 0 ? 'orange' : '' }}">
        <div class="stat-card-icon"><i class="ri-time-line"></i></div>
        <div class="stat-card-value">{{ $events->whereNull('processed_at')->whereNull('error')->count() }}</div>
        <div class="stat-card-label">En attente</div>
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
                        <th>Données extraites</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($events as $event)
                    @php
                        $payload = is_array($event->payload) ? $event->payload : (json_decode($event->payload, true) ?? []);
                        $d = $payload['data'] ?? [];
                        $d0 = (is_array($d) && isset($d[0]) && is_array($d[0])) ? $d[0] : (is_array($d) ? $d : []);
                        $isMsg = str_contains($event->event_type, 'message');
                        $remoteJid  = data_get($d0, 'key.remoteJid') ?? data_get($d0, 'remoteJid') ?? null;
                        $fromMe     = data_get($d0, 'key.fromMe') ?? data_get($d0, 'fromMe') ?? null;
                        $msgText    = data_get($d0, 'message.conversation')
                            ?? data_get($d0, 'message.extendedTextMessage.text')
                            ?? data_get($d0, 'text') ?? null;
                        $pushName   = data_get($d0, 'pushName') ?? null;
                    @endphp
                    <tr style="{{ $isMsg ? 'background:rgba(34,197,94,.04)' : '' }}">
                        <td style="color:var(--text-muted);font-size:.8rem">{{ $event->id }}</td>
                        <td>
                            <span class="badge {{ $isMsg ? 'badge-green' : 'badge-gray' }}">
                                {{ $event->event_type }}
                            </span>
                        </td>
                        <td style="font-size:.8rem;white-space:nowrap">{{ $event->created_at->diffForHumans() }}</td>
                        <td>
                            @if($event->error)
                                <span class="badge badge-red">Erreur</span>
                            @elseif($event->processed_at)
                                <span class="badge badge-green">Traité</span>
                            @else
                                <span class="badge badge-orange">En attente</span>
                            @endif
                        </td>
                        <td style="font-size:.78rem;line-height:1.6">
                            @if($remoteJid)
                                <div><span style="color:var(--text-muted)">JID:</span> <code>{{ $remoteJid }}</code></div>
                            @else
                                <div style="color:#ef4444">⚠ remoteJid introuvable</div>
                            @endif
                            @if($fromMe !== null)
                                <div><span style="color:var(--text-muted)">fromMe:</span> <code>{{ $fromMe ? 'true' : 'false' }}</code></div>
                            @endif
                            @if($pushName)
                                <div><span style="color:var(--text-muted)">pushName:</span> {{ $pushName }}</div>
                            @endif
                            @if($msgText)
                                <div><span style="color:var(--text-muted)">texte:</span> {{ Str::limit($msgText, 50) }}</div>
                            @endif
                            @if($event->error)
                                <div style="color:#ef4444;margin-top:.2rem">{{ Str::limit($event->error, 100) }}</div>
                            @endif
                            <details style="margin-top:.3rem">
                                <summary style="cursor:pointer;color:var(--text-muted);font-size:.75rem">Voir JSON complet</summary>
                                <pre style="margin-top:.4rem;padding:.6rem;background:#0f172a;color:#e2e8f0;border-radius:.4rem;font-size:.68rem;overflow-x:auto;max-height:280px;white-space:pre-wrap;word-break:break-all;border:1px solid #334155">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </details>
                        </td>
                        <td>
                            <form method="POST" action="{{ route(auth()->user()->routeNamePrefix() . '.instances.webhook-events.reprocess', [$instance, $event->id]) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline btn-sm" title="Retraiter maintenant (synchrone)">
                                    <i class="ri-refresh-line"></i> Retraiter
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
