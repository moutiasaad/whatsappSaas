@extends('layouts.admin')

@section('title', __('ui.instances_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.instances_page.breadcrumb') }}</span>
@endsection

@section('content')
@php
    $panelPrefix = auth()->user()->routeNamePrefix();
    $i18n = [
        'never'               => __('ui.instances_page.never'),
        'no_number'           => __('ui.instances_page.no_number'),
        'connection_failed'   => __('ui.instances_page.connection_failed'),
        'generating_qr'       => __('ui.instances_page.generating_qr'),
        'non_image_payload'   => __('ui.instances_page.non_image_payload'),
        'status_refreshed'    => __('ui.instances_page.status_refreshed'),
        'qr_connected'        => __('ui.instances_page.qr_connected'),
        'open_whatsapp'       => __('ui.instances_page.open_whatsapp'),
        'just_now'            => __('ui.conversations_page.just_now'),
        'minutes_ago'         => __('ui.conversations_page.minutes_ago'),
        'hours_ago'           => __('ui.conversations_page.hours_ago'),
        'days_ago'            => __('ui.conversations_page.days_ago'),
    ];
@endphp

<div x-data="instancesPage()" x-init="init()" x-cloak>

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.instances_page.title') }}</div>
            <div class="page-subtitle">{{ __('ui.instances_page.subtitle') }}</div>
        </div>
        @if(!($hasInstance ?? false))
        <div class="page-header-actions">
            <a href="{{ route($panelPrefix . '.instances.create') }}" class="btn btn-primary">
                <i class="ri-add-line"></i> {{ __('ui.instances_page.new_instance') }}
            </a>
        </div>
        @endif
    </div>

    <div class="stats-grid" style="margin-bottom:1.5rem">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-check-line"></i></div>
            <div class="stat-card-value" x-text="stats.connected"></div>
            <div class="stat-card-label">{{ __('ui.instances_page.connected') }}</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-card-icon"><i class="ri-loader-4-line"></i></div>
            <div class="stat-card-value" x-text="stats.connecting"></div>
            <div class="stat-card-label">{{ __('ui.instances_page.connecting') }}</div>
        </div>
        <div class="stat-card red">
            <div class="stat-card-icon"><i class="ri-wifi-off-line"></i></div>
            <div class="stat-card-value" x-text="stats.offline"></div>
            <div class="stat-card-label">{{ __('ui.instances_page.offline') }}</div>
        </div>
    </div>

    {{-- Loading --}}
    <div x-show="loading" class="spinner-wrap" style="min-height:200px">
        <div>
            <div class="spinner" style="margin:0 auto 1rem"></div>
            <div style="color:var(--text-muted);font-size:.875rem;text-align:center">{{ __('ui.conversations_page.loading') }}</div>
        </div>
    </div>

    {{-- Empty --}}
    <div class="card" x-show="!loading && instances.length === 0">
        <div class="empty-state" style="padding:3rem">
            <div class="empty-state-icon">
                <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
            </div>
            <h4>{{ __('ui.instances_page.no_instances_yet') }}</h4>
            <p>{{ __('ui.instances_page.no_instances_desc') }}</p>
            @if(!($hasInstance ?? false))
            <a href="{{ route($panelPrefix . '.instances.create') }}" class="btn btn-primary">{{ __('ui.instances_page.add_instance') }}</a>
            @endif
        </div>
    </div>

    {{-- Table --}}
    <div class="card" x-show="!loading && instances.length > 0">
        <div class="card-header">
            <div>
                <div class="card-title">{{ __('ui.instances_page.instances') }}</div>
                <div class="card-subtitle">{{ __('ui.instances_page.overview') }}</div>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('ui.instances_page.instance') }}</th>
                        @if($isSuperAdmin ?? false)
                        <th>{{ __('ui.platform_tenants_page.tenant') }}</th>
                        @endif
                        <th>{{ __('ui.instances_page.gateway') }}</th>
                        <th>{{ __('ui.instances_page.phone') }}</th>
                        <th>{{ __('ui.instances_page.activity') }}</th>
                        <th>Webhook</th>
                        <th>{{ __('ui.instances_page.status') }}</th>
                        <th style="text-align:right">{{ __('ui.instances_page.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="inst in instances" :key="inst.id">
                        <tr @click="window.location.href = showUrlTpl.replace('__ID__', String(inst.id))" style="cursor:pointer" class="tr-hover">
                            <td>
                                <a :href="showUrlTpl.replace('__ID__', inst.id)" style="display:flex;align-items:center;gap:.75rem;text-decoration:none;color:inherit">
                                    <div style="width:2.5rem;height:2.5rem;border-radius:.75rem;background:linear-gradient(135deg,#0d1117,#1a2332);border:1px solid var(--card-border);display:flex;align-items:center;justify-content:center;color:#25d366;flex-shrink:0">
                                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.127.558 4.124 1.535 5.86L.057 23.215a.75.75 0 00.906.934l5.474-1.437A11.944 11.944 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.75a9.712 9.712 0 01-4.967-1.365l-.356-.213-3.685.967.983-3.594-.232-.37A9.714 9.714 0 012.25 12C2.25 6.615 6.615 2.25 12 2.25S21.75 6.615 21.75 12 17.385 21.75 12 21.75z"/></svg>
                                    </div>
                                    <div>
                                        <div style="font-weight:600;font-size:.9375rem" x-text="inst.name"></div>
                                        <div style="font-size:.75rem;color:var(--text-muted)" x-text="inst.phone_number || i18n.no_number"></div>
                                    </div>
                                </a>
                            </td>
                            @if($isSuperAdmin ?? false)
                            <td style="font-size:.8125rem;color:var(--text-muted);" x-text="inst.tenant?.name ?? '—'"></td>
                            @endif
                            <td x-text="inst.gateway ? inst.gateway.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase()) : '—'"></td>
                            <td x-text="inst.phone_number || '—'"></td>
                            <td x-text="inst.last_message_at ? timeAgo(inst.last_message_at) : i18n.never"></td>
                            <td>
                                <span x-show="inst.webhook_enabled" style="display:flex;flex-direction:column;gap:.2rem">
                                    <span style="display:inline-flex;align-items:center;gap:.4rem">
                                        <span style="width:.45rem;height:.45rem;border-radius:50%;background:#22c55e;flex-shrink:0"></span>
                                        <span style="font-size:.8rem;color:var(--text-muted)"
                                              x-text="inst.webhook_events_max_created_at ? timeAgo(inst.webhook_events_max_created_at) : 'Pas encore'"></span>
                                    </span>
                                    <span x-show="inst.webhook_pending_count > 0"
                                          style="font-size:.75rem;color:#f59e0b;font-weight:600"
                                          x-text="'⚠ ' + inst.webhook_pending_count + ' non traité' + (inst.webhook_pending_count > 1 ? 's' : '') + ' — queue:work requis'"></span>
                                </span>
                                <span x-show="!inst.webhook_enabled" style="display:inline-flex;align-items:center;gap:.4rem">
                                    <span style="width:.45rem;height:.45rem;border-radius:50%;background:#ef4444;flex-shrink:0"></span>
                                    <span style="font-size:.8rem;color:#ef4444">Non enregistré</span>
                                </span>
                            </td>
                            <td>
                                <span :class="statusBadge(inst.status)" style="display:inline-flex;align-items:center;gap:.35rem">
                                    <span class="status-dot" :class="statusDot(inst.status)" style="width:.4rem;height:.4rem"></span>
                                    <span x-text="statusLabel(inst.status)"></span>
                                </span>
                            </td>
                            <td @click.stop>
                                <div style="display:flex;justify-content:flex-end;gap:.5rem;flex-wrap:wrap">
                                    <template x-if="inst.status === 'disconnected' || inst.status === 'error'">
                                        <button @click="connect(inst.id, inst.name)" :disabled="inst.loading" class="btn btn-primary btn-sm">
                                            <span x-show="!inst.loading">{{ __('ui.instances_page.connect') }}</span>
                                            <span x-show="inst.loading">{{ __('ui.instances_page.connecting_ellipsis') }}</span>
                                        </button>
                                    </template>

                                    <template x-if="inst.status === 'qr_pending' || inst.status === 'connecting'">
                                        <button @click="showQrCode(inst.id, inst.name)" class="btn btn-outline btn-sm">
                                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h.01M18 14h.01M14 18h.01M18 18h.01"/></svg>
                                            {{ __('ui.instances_page.show_qr') }}
                                        </button>
                                    </template>

                                    <template x-if="inst.status === 'connected'">
                                        <button @click="checkStatus(inst.id)" class="btn btn-outline btn-sm">
                                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                                            {{ __('ui.instances_page.refresh') }}
                                        </button>
                                    </template>

                                    <a :href="webhookEventsUrl(inst.id)" class="btn btn-ghost btn-icon" title="Webhook Events" style="color:#6366f1">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    </a>

                                    <a :href="editUrl(inst.id)" class="btn btn-ghost btn-icon" title="Edit">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- QR Modal --}}
    <div class="modal-overlay" :class="qr.show ? 'show' : ''" role="dialog" aria-modal="true"
         @click.self="closeQr()" @keydown.escape.window="closeQr()">
        <div class="modal-box" style="width:min(430px,calc(100vw - 32px));max-width:430px">
            <div class="modal-icon info"><i class="ri-qr-code-line"></i></div>
            <h3>{{ __('ui.instances_page.scan_qr_code') }}</h3>
            <p x-text="qr.instanceName ? qr.instanceName : i18n.open_whatsapp"></p>

            <div style="margin:1rem 0 1.25rem;display:flex;align-items:center;justify-content:center;min-height:240px">
                <div x-show="qr.data && isQrImage(qr.data)" style="background:#fff;padding:1rem;border-radius:1rem;border:1px solid var(--card-border);box-shadow:0 8px 24px rgba(15,23,42,.08)">
                    <img :src="qrImageSrc(qr.data)" style="width:220px;height:220px;display:block;object-fit:contain" alt="QR Code">
                </div>
                <div x-show="!qr.data || !isQrImage(qr.data)" style="display:flex;flex-direction:column;align-items:center;gap:.875rem;color:var(--text-muted);text-align:center">
                    <div class="spinner" style="width:2.5rem;height:2.5rem;border-width:3px"></div>
                    <span style="font-size:.8125rem" x-text="qr.data ? i18n.non_image_payload : i18n.generating_qr"></span>
                </div>
            </div>

            <div class="modal-actions">
                <button class="btn btn-outline" @click="closeQr()">
                    <i class="ri-close-line"></i> {{ __('ui.instances_page.close') }}
                </button>
                <button type="button" class="btn btn-primary" @click="refreshQr(false)" :disabled="!qr.instanceId || qr.loading">
                    <i class="ri-refresh-line" :class="{ 'ri-loader-4-line ri-spin': qr.loading }"></i> {{ __('ui.instances_page.refresh') }}
                </button>
            </div>
        </div>
    </div>

</div>

<script>
function instancesPage() {
    return {
        i18n:        @json($i18n),
        statusLabels: @js(__('ui.instances_page.status_labels')),
        indexUrl:    @json(route($panelPrefix . '.instances.index')),
        showUrlTpl:  @json(route($panelPrefix . '.instances.show',           ['instance' => '__ID__'])),
        editUrlTpl:  @json(route($panelPrefix . '.instances.edit',           ['instance' => '__ID__'])),
        webhookEventsUrlTpl: @json(route($panelPrefix . '.instances.webhook-events', ['instance' => '__ID__'])),

        instances: [],
        stats:     { connected: 0, connecting: 0, offline: 0 },
        loading:   true,

        qr: { show: false, data: null, instanceId: null, instanceName: '', loading: false },

        init() {
            this.loadData();
            this.subscribeHealth();
            window.addEventListener('pageshow', (e) => {
                if (e.persisted) this.loadData();
            });
        },

        async loadData() {
            this.loading = true;
            try {
                const res = await fetch(this.indexUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    credentials: 'same-origin',
                });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();
                this.instances = (data.data || []).map(i => ({ ...i, loading: false }));
                if (data.stats) this.stats = data.stats;
            } catch (e) {
                console.error('Instances fetch failed:', e);
                this.instances = [];
            } finally {
                this.loading = false;
            }
        },

        recalcStats() {
            this.stats.connected  = this.instances.filter(i => i.status === 'connected').length;
            this.stats.connecting = this.instances.filter(i => ['connecting', 'qr_pending'].includes(i.status)).length;
            this.stats.offline    = this.instances.filter(i => ['disconnected', 'error', 'banned'].includes(i.status)).length;
        },

        getInst(id) {
            return this.instances.find(i => i.id === id) ?? null;
        },

        subscribeHealth() {
            if (!window.Echo) return;
            const tenantId = @json(auth()->user()->tenant_id);
            if (!tenantId) return;
            window.Echo.private(`tenant.${tenantId}.instances`)
                .listen('.instance.status.changed', (e) => {
                    const inst = this.getInst(e.id);
                    if (inst) {
                        inst.status = e.status;
                        if (e.phone_number) inst.phone_number = e.phone_number;
                        this.recalcStats();
                    }
                    // QR scanned successfully: auto-hide the QR modal and report the new status.
                    if (this.qr.show && this.qr.instanceId === e.id && e.status === 'connected') {
                        const name = this.qr.instanceName || (inst ? inst.name : '');
                        this.closeQr();
                        window.showToast?.('success', this.i18n.qr_connected.replace(':name', name).trim());
                    }
                });
        },

        async connect(id, name) {
            const inst = this.getInst(id);
            if (inst) inst.loading = true;
            try {
                const res = await fetch(`/api/instances/${id}/connect`, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                });
                const data = await res.json();
                if (res.ok) {
                    if (inst) inst.status = 'qr_pending';
                    this.recalcStats();
                    this.openQr(id, name, data.qr_code ?? null);
                } else {
                    window.showToast?.('error', data.message || this.i18n.connection_failed);
                }
            } finally {
                if (inst) inst.loading = false;
            }
        },

        async showQrCode(id, name) {
            this.openQr(id, name, null);
            await this.refreshQr(true);
        },

        openQr(id, name, data) {
            this.qr.instanceId   = id;
            this.qr.instanceName = name;
            this.qr.data         = data;
            this.qr.show         = true;
        },

        async refreshQr(silent = false) {
            if (!this.qr.instanceId) return;
            this.qr.loading = true;
            try {
                const res = await fetch(`/api/instances/${this.qr.instanceId}/connect`, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                });
                const data = await res.json();
                if (data.qr_code) this.qr.data = data.qr_code;
                else if (!silent) window.showToast?.('info', this.i18n.generating_qr);
            } finally {
                this.qr.loading = false;
            }
        },

        closeQr() {
            this.qr.show = false;
            this.qr.data = null;
            this.qr.loading = false;
        },

        async checkStatus(id) {
            const inst = this.getInst(id);
            const res  = await fetch(`/api/instances/${id}/status`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (inst) {
                inst.status = data.status;
                if (data.phone_number) inst.phone_number = data.phone_number;
            }
            this.recalcStats();
            window.showToast?.('success', this.i18n.status_refreshed);
        },

        isQrImage(data) {
            if (typeof data !== 'string' || !data.trim()) return false;
            if (data.startsWith('data:image/')) return true;
            return /^[A-Za-z0-9+/=\s]+$/.test(data.trim()) && data.trim().length > 64;
        },

        qrImageSrc(data) {
            if (!data) return '';
            if (data.startsWith('data:image/')) return data;
            return `data:image/png;base64,${String(data).replace(/\s+/g, '')}`;
        },

        statusLabel(s) {
            return this.statusLabels?.[s] || (s || '').replace(/_/g, ' ');
        },

        statusBadge(s) {
            if (s === 'connected') return 'badge badge-green';
            if (s === 'qr_pending' || s === 'connecting') return 'badge badge-orange';
            if (s === 'banned') return 'badge badge-red';
            return 'badge badge-gray';
        },

        statusDot(s) {
            if (s === 'connected') return 'green';
            if (s === 'qr_pending' || s === 'connecting') return 'yellow';
            return 'red';
        },

        showUrl(id)           { return this.showUrlTpl.replace('__ID__', String(id)); },
        editUrl(id)           { return this.editUrlTpl.replace('__ID__', String(id)); },
        webhookEventsUrl(id)  { return this.webhookEventsUrlTpl.replace('__ID__', String(id)); },

        timeAgo(ts) {
            if (!ts) return '-';
            const diff = (Date.now() - new Date(ts)) / 1000;
            if (diff < 60)    return this.i18n.just_now;
            if (diff < 3600)  return `${Math.floor(diff / 60)}${this.i18n.minutes_ago}`;
            if (diff < 86400) return `${Math.floor(diff / 3600)}${this.i18n.hours_ago}`;
            return `${Math.floor(diff / 86400)}${this.i18n.days_ago}`;
        },
    };
}
</script>
@endsection
