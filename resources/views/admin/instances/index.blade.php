@extends('layouts.admin')

@section('title', __('ui.instances_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.instances_page.breadcrumb') }}</span>
@endsection

@section('content')
<div x-data="instancesPage()" x-init="init()" x-cloak>

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.instances_page.title') }}</div>
            <div class="page-subtitle">{{ __('ui.instances_page.subtitle') }}</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('admin.instances.create') }}" class="btn btn-primary">
                <i class="ri-add-line"></i> {{ __('ui.instances_page.new_instance') }}
            </a>
        </div>
    </div>

    <div class="stats-grid" style="margin-bottom:1.5rem">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-check-line"></i></div>
            <div class="stat-card-value">{{ $instances->where('status', 'connected')->count() }}</div>
            <div class="stat-card-label">{{ __('ui.instances_page.connected') }}</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-card-icon"><i class="ri-loader-4-line"></i></div>
            <div class="stat-card-value">{{ $instances->whereIn('status', ['connecting', 'qr_pending'])->count() }}</div>
            <div class="stat-card-label">{{ __('ui.instances_page.connecting') }}</div>
        </div>
        <div class="stat-card red">
            <div class="stat-card-icon"><i class="ri-wifi-off-line"></i></div>
            <div class="stat-card-value">{{ $instances->whereIn('status', ['disconnected', 'error', 'banned'])->count() }}</div>
            <div class="stat-card-label">{{ __('ui.instances_page.offline') }}</div>
        </div>
    </div>

    @if($instances->isEmpty())
        <div class="card">
            <div class="empty-state" style="padding:3rem">
                <div class="empty-state-icon">
                    <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
                </div>
                <h4>{{ __('ui.instances_page.no_instances_yet') }}</h4>
                <p>{{ __('ui.instances_page.no_instances_desc') }}</p>
                <a href="{{ route('admin.instances.create') }}" class="btn btn-primary">{{ __('ui.instances_page.add_instance') }}</a>
            </div>
        </div>
    @else
        <div class="card">
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
                            <th>{{ __('ui.instances_page.gateway') }}</th>
                            <th>{{ __('ui.instances_page.phone') }}</th>
                            <th>{{ __('ui.instances_page.activity') }}</th>
                            <th>{{ __('ui.instances_page.status') }}</th>
                            <th style="text-align:right">{{ __('ui.instances_page.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($instances as $instance)
                        <tr data-instance="{{ $instance->id }}" x-data="{ status: '{{ $instance->status }}', loading: false }">
                            <td>
                                <div style="display:flex;align-items:center;gap:.75rem">
                                    <div style="width:2.5rem;height:2.5rem;border-radius:.75rem;background:linear-gradient(135deg,#0d1117,#1a2332);border:1px solid var(--card-border);display:flex;align-items:center;justify-content:center;color:#25d366;flex-shrink:0">
                                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.127.558 4.124 1.535 5.86L.057 23.215a.75.75 0 00.906.934l5.474-1.437A11.944 11.944 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.75a9.712 9.712 0 01-4.967-1.365l-.356-.213-3.685.967.983-3.594-.232-.37A9.714 9.714 0 012.25 12C2.25 6.615 6.615 2.25 12 2.25S21.75 6.615 21.75 12 17.385 21.75 12 21.75z"/></svg>
                                    </div>
                                    <div>
                                        <div style="font-weight:600;font-size:.9375rem">{{ $instance->name }}</div>
                                        <div style="font-size:.75rem;color:var(--text-muted)">{{ $instance->phone_number ?? __('ui.instances_page.no_number') }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ ucfirst(str_replace('_', ' ', $instance->gateway)) }}</td>
                            <td>{{ $instance->phone_number ?? '—' }}</td>
                            <td>{{ $instance->last_message_at?->diffForHumans() ?? __('ui.instances_page.never') }}</td>
                            <td>
                                <span :class="statusBadge(status)" style="display:inline-flex;align-items:center;gap:.35rem">
                                    <span class="status-dot" :class="statusDot(status)" style="width:.4rem;height:.4rem"></span>
                                    <span x-text="statusLabel(status)"></span>
                                </span>
                            </td>
                            <td>
                                <div style="display:flex;justify-content:flex-end;gap:.5rem;flex-wrap:wrap">
                                    <template x-if="status === 'disconnected' || status === 'error'">
                                        <button @click="connect({{ $instance->id }}, '{{ addslashes($instance->name) }}')" :disabled="loading" class="btn btn-primary btn-sm">
                                            <span x-show="!loading">{{ __('ui.instances_page.connect') }}</span>
                                            <span x-show="loading">{{ __('ui.instances_page.connecting_ellipsis') }}</span>
                                        </button>
                                    </template>

                                    <template x-if="status === 'qr_pending' || status === 'connecting'">
                                        <button @click="showQrCode({{ $instance->id }}, '{{ addslashes($instance->name) }}')" class="btn btn-outline btn-sm">
                                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h.01M18 14h.01M14 18h.01M18 18h.01"/></svg>
                                            {{ __('ui.instances_page.show_qr') }}
                                        </button>
                                    </template>

                                    <template x-if="status === 'connected'">
                                        <button @click="checkStatus({{ $instance->id }})" class="btn btn-outline btn-sm">
                                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                                            {{ __('ui.instances_page.refresh') }}
                                        </button>
                                    </template>

                                    <a href="{{ route('admin.instances.edit', $instance) }}" class="btn btn-ghost btn-icon" title="Edit">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </a>

                                    <button @click="logoutInstance({{ $instance->id }}, '{{ $instance->name }}')" class="btn btn-ghost btn-icon" title="{{ __('ui.instances_page.logout') }}" style="color:#ef4444">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                                    </button>

                                    <button
                                        type="button"
                                        onclick="confirmDelete('{{ route('admin.instances.destroy', $instance) }}', { title: @js(__('ui.instance_edit_page.delete_prompt', ['name' => $instance->name])), message: @js(__('ui.instance_edit_page.delete_warning')) })"
                                        class="btn btn-ghost btn-icon"
                                        title="{{ __('ui.instance_edit_page.delete_instance') }}"
                                        style="color:#ef4444">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4a1 1 0 011-1h6a1 1 0 011 1v2"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="modal-overlay" :class="qr.show ? 'show' : ''" role="dialog" aria-modal="true"
         @click.self="closeQr()" @keydown.escape.window="closeQr()">
        <div class="modal-box" style="width:min(430px,calc(100vw - 32px));max-width:430px">
            <div class="modal-icon info"><i class="ri-qr-code-line"></i></div>
            <h3>{{ __('ui.instances_page.scan_qr_code') }}</h3>
            <p x-text="qr.instanceName ? qr.instanceName : @js(__('ui.instances_page.open_whatsapp'))"></p>

            <div style="margin:1rem 0 1.25rem;display:flex;align-items:center;justify-content:center;min-height:240px">
                <div x-show="qr.data && isQrImage(qr.data)" style="background:#fff;padding:1rem;border-radius:1rem;border:1px solid var(--card-border);box-shadow:0 8px 24px rgba(15,23,42,.08)">
                    <img :src="qrImageSrc(qr.data)" style="width:220px;height:220px;display:block;object-fit:contain" alt="QR Code">
                </div>
                <div x-show="!qr.data || !isQrImage(qr.data)" style="display:flex;flex-direction:column;align-items:center;gap:.875rem;color:var(--text-muted);text-align:center">
                    <div class="spinner" style="width:2.5rem;height:2.5rem;border-width:3px"></div>
                    <span style="font-size:.8125rem" x-text="qr.data ? @js(__('ui.instances_page.non_image_payload')) : @js(__('ui.instances_page.generating_qr'))"></span>
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
        statusLabels: @js(__('ui.instances_page.status_labels')),
        qr: { show: false, data: null, instanceId: null, instanceName: '', loading: false },

        init() {
            this.subscribeHealth();
        },

        subscribeHealth() {
            if (!window.Echo) return;
            const tenantId = {{ auth()->user()->tenant_id }};
            window.Echo.private(`tenant.${tenantId}.instances`)
                .listen('.instance.status.changed', (e) => {
                    const card = this.getCard(e.instance_id);
                    if (card) card.status = e.status;
                });
        },

        async connect(id, name) {
            const card = this.getCard(id);
            if (!card) return;
            card.loading = true;

            const res = await fetch(`/api/instances/${id}/connect`, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
            });
            const data = await res.json();
            card.loading = false;

            if (res.ok) {
                card.status = 'qr_pending';
                this.openQr(id, name, data.qr_code ?? null);
            } else {
                window.showToast?.('error', data.message || @js(__('ui.instances_page.connection_failed')));
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
            const res = await fetch(`/api/instances/${this.qr.instanceId}/connect`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
            });
            const data = await res.json();
            this.qr.loading = false;
            if (data.qr_code) {
                this.qr.data = data.qr_code;
                return;
            }

            if (!silent) {
                window.showToast?.('info', @js(__('ui.instances_page.generating_qr')));
            }
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
            return this.statusLabels?.[s] || s.replace(/_/g, ' ');
        },

        async checkStatus(id) {
            const card = this.getCard(id);
            const res = await fetch(`/api/instances/${id}/status`, {
                credentials: 'same-origin', headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (card) card.status = data.status;
            window.showToast?.('success', @js(__('ui.instances_page.status_refreshed')));
        },

        async logoutInstance(id, name) {
            if (!confirm(`${@js(__('ui.instances_page.logout_confirm_prefix'))} "${name}"? ${@js(__('ui.instances_page.logout_confirm_suffix'))}`)) return;
            await fetch(`/api/instances/${id}/logout`, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
            });
            window.showToast?.('success', @js(__('ui.instances_page.instance_logged_out')));
            const card = this.getCard(id);
            if (card) card.status = 'disconnected';
        },

        getCard(id) {
            const el = document.querySelector(`[data-instance="${id}"]`);
            return el?._x_dataStack?.[0] ?? null;
        },

        closeQr() {
            this.qr.show = false;
            this.qr.data = null;
            this.qr.loading = false;
        }
    }
}

function statusBadge(s) {
    if (s === 'connected') return 'badge badge-green';
    if (s === 'qr_pending' || s === 'connecting') return 'badge badge-orange';
    if (s === 'banned') return 'badge badge-red';
    return 'badge badge-gray';
}
function statusDot(s) {
    if (s === 'connected') return 'green';
    if (s === 'qr_pending' || s === 'connecting') return 'yellow';
    return 'red';
}
document.addEventListener('alpine:init', () => {
    Alpine.magic('statusBadge', () => statusBadge);
    Alpine.magic('statusDot', () => statusDot);
});
</script>
@endsection
