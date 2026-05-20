@extends('layouts.admin')

@section('title', 'WhatsApp Instances')

@section('breadcrumb')
    <span>Instances</span>
@endsection

@section('content')
<div x-data="instancesPage()" x-init="init()" x-cloak>

    {{-- Header --}}
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">WhatsApp Instances</div>
            <div class="page-subtitle">Manage your WhatsApp connections</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('admin.instances.create') }}" class="btn btn-primary">
                <i class="ri-add-line"></i> New Instance
            </a>
        </div>
    </div>

    {{-- Stats Row --}}
    <div class="stats-grid" style="margin-bottom:1.5rem">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-check-line"></i></div>
            <div class="stat-card-value">{{ $instances->where('status', 'connected')->count() }}</div>
            <div class="stat-card-label">Connected</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-card-icon"><i class="ri-loader-4-line"></i></div>
            <div class="stat-card-value">{{ $instances->whereIn('status', ['connecting','qr_pending'])->count() }}</div>
            <div class="stat-card-label">Connecting</div>
        </div>
        <div class="stat-card red">
            <div class="stat-card-icon"><i class="ri-wifi-off-line"></i></div>
            <div class="stat-card-value">{{ $instances->whereIn('status', ['disconnected','error','banned'])->count() }}</div>
            <div class="stat-card-label">Offline</div>
        </div>
    </div>

    {{-- Instance Grid --}}
    @if($instances->isEmpty())
        <div class="card">
            <div class="empty-state" style="padding:3rem">
                <div class="empty-state-icon">
                    <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
                </div>
                <h4>No instances yet</h4>
                <p>Add your first WhatsApp instance to start receiving messages</p>
                <a href="{{ route('admin.instances.create') }}" class="btn btn-primary">Add Instance</a>
            </div>
        </div>
    @else
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:1rem">
        @foreach($instances as $instance)
        <div class="card instance-card" data-instance="{{ $instance->id }}"
             x-data="{ status: '{{ $instance->status }}', loading: false }">

            {{-- Card Header --}}
            <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1rem">
                <div style="display:flex;align-items:center;gap:.75rem">
                    <div style="width:2.75rem;height:2.75rem;border-radius:.75rem;background:linear-gradient(135deg,#0d1117,#1a2332);border:1px solid var(--card-border);display:flex;align-items:center;justify-content:center;color:#25d366">
                        <svg width="22" height="22" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.127.558 4.124 1.535 5.86L.057 23.215a.75.75 0 00.906.934l5.474-1.437A11.944 11.944 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.75a9.712 9.712 0 01-4.967-1.365l-.356-.213-3.685.967.983-3.594-.232-.37A9.714 9.714 0 012.25 12C2.25 6.615 6.615 2.25 12 2.25S21.75 6.615 21.75 12 17.385 21.75 12 21.75z"/></svg>
                    </div>
                    <div>
                        <div style="font-weight:600;font-size:.9375rem">{{ $instance->name }}</div>
                        <div style="font-size:.75rem;color:var(--text-muted)">{{ $instance->phone_number ?? 'No number' }}</div>
                    </div>
                </div>

                {{-- Status Badge --}}
                <div style="display:flex;align-items:center;gap:.375rem">
                    <span :class="statusBadge(status)">
                        <span class="status-dot" :class="statusDot(status)" style="width:.4rem;height:.4rem"></span>
                        <span x-text="status.replace('_',' ')"></span>
                    </span>
                </div>
            </div>

            {{-- Details --}}
            <div style="font-size:.8125rem;color:var(--text-muted);margin-bottom:1rem;display:flex;flex-direction:column;gap:.375rem">
                <div style="display:flex;justify-content:space-between">
                    <span>Gateway</span>
                    <span style="color:var(--text-secondary);font-weight:500">{{ ucfirst(str_replace('_', ' ', $instance->gateway)) }}</span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span>Last activity</span>
                    <span style="color:var(--text-secondary)">{{ $instance->last_message_at?->diffForHumans() ?? 'Never' }}</span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span>Webhook</span>
                    <span style="color:#10b981;font-weight:500">Configured</span>
                </div>
            </div>

            {{-- Actions --}}
            <div style="display:flex;gap:.5rem;padding-top:1rem;border-top:1px solid var(--card-border)">
                <template x-if="status === 'disconnected' || status === 'error'">
                    <button @click="connect({{ $instance->id }}, '{{ addslashes($instance->name) }}')" :disabled="loading" class="btn btn-primary btn-sm" style="flex:1">
                        <span x-show="!loading">Connect</span>
                        <span x-show="loading">Connecting…</span>
                    </button>
                </template>

                <template x-if="status === 'qr_pending' || status === 'connecting'">
                    <button @click="showQrCode({{ $instance->id }}, '{{ addslashes($instance->name) }}')" class="btn btn-primary btn-sm" style="flex:1">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h.01M18 14h.01M14 18h.01M18 18h.01"/></svg>
                        Show QR
                    </button>
                </template>

                <template x-if="status === 'connected'">
                    <button @click="checkStatus({{ $instance->id }})" class="btn btn-outline btn-sm">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                        Refresh
                    </button>
                </template>

                <a href="{{ route('admin.instances.edit', $instance) }}" class="btn btn-ghost btn-icon" title="Edit">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </a>

                <button @click="logoutInstance({{ $instance->id }}, '{{ $instance->name }}')"
                        class="btn btn-ghost btn-icon" title="Logout"
                        style="color: #ef4444">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                </button>
            </div>

        </div>
        @endforeach
    </div>
    @endif

    {{-- QR Modal (shared, outer scope) --}}
    <div x-show="qr.show"
         style="display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.55);backdrop-filter:blur(3px);align-items:center;justify-content:center"
         :style="qr.show ? 'display:flex' : 'display:none'"
         @keydown.escape.window="qr.show = false">
        <div style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-xl);width:360px;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden">

            {{-- Modal Header --}}
            <div style="display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.5rem;border-bottom:1px solid var(--card-border)">
                <div>
                    <div style="font-size:1rem;font-weight:700;color:var(--text-primary)">Scan QR Code</div>
                    <div style="font-size:.75rem;color:var(--text-muted);margin-top:.125rem" x-text="qr.instanceName"></div>
                </div>
                <button @click="qr.show = false"
                        style="width:2rem;height:2rem;border:none;background:var(--page-bg);border-radius:var(--radius-sm);cursor:pointer;color:var(--text-muted);display:flex;align-items:center;justify-content:center;font-size:1.125rem"
                        onmouseenter="this.style.background='var(--card-border)'" onmouseleave="this.style.background='var(--page-bg)'">
                    <i class="ri-close-line"></i>
                </button>
            </div>

            {{-- Instructions --}}
            <div style="padding:1rem 1.5rem;background:rgba(16,185,129,.06);border-bottom:1px solid var(--card-border);font-size:.8125rem;color:var(--text-secondary);text-align:center">
                <i class="ri-information-line" style="color:var(--brand);margin-right:.375rem"></i>
                Open WhatsApp → Settings → Linked Devices → Link a Device
            </div>

            {{-- QR Area --}}
            <div style="padding:1.5rem;display:flex;align-items:center;justify-content:center;min-height:260px">
                <div x-show="qr.data" style="background:#fff;padding:1rem;border-radius:var(--radius);border:1px solid var(--card-border)">
                    <img :src="'data:image/png;base64,' + qr.data" style="width:200px;height:200px;display:block" alt="QR Code">
                </div>
                <div x-show="!qr.data" style="display:flex;flex-direction:column;align-items:center;gap:.875rem;color:var(--text-muted)">
                    <div class="spinner" style="width:2.5rem;height:2.5rem;border-width:3px"></div>
                    <span style="font-size:.8125rem">Generating QR code…</span>
                </div>
            </div>

            {{-- Footer --}}
            <div style="padding:1rem 1.5rem;border-top:1px solid var(--card-border);display:flex;gap:.5rem">
                <button @click="qr.show = false" class="btn btn-outline" style="flex:1">Close</button>
                <button @click="refreshQr()" :disabled="!qr.instanceId" class="btn btn-primary" style="flex:1">
                    <i class="ri-refresh-line"></i> Refresh
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function instancesPage() {
    return {
        qr: { show: false, data: null, instanceId: null, instanceName: '' },

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
                window.showToast?.('error', data.message || 'Connection failed');
            }
        },

        async showQrCode(id, name) {
            this.openQr(id, name, null);
            const res = await fetch(`/api/instances/${id}/status`, {
                credentials: 'same-origin', headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            this.qr.data = data.qr_code ?? null;
        },

        openQr(id, name, data) {
            this.qr.instanceId   = id;
            this.qr.instanceName = name;
            this.qr.data         = data;
            this.qr.show         = true;
        },

        async refreshQr() {
            if (!this.qr.instanceId) return;
            this.qr.data = null;
            const res = await fetch(`/api/instances/${this.qr.instanceId}/status`, {
                credentials: 'same-origin', headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            this.qr.data = data.qr_code ?? null;
        },

        async checkStatus(id) {
            const card = this.getCard(id);
            const res = await fetch(`/api/instances/${id}/status`, {
                credentials: 'same-origin', headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (card) card.status = data.status;
            window.showToast?.('success', 'Status refreshed');
        },

        async logoutInstance(id, name) {
            if (!confirm(`Logout "${name}"? This will disconnect WhatsApp.`)) return;
            await fetch(`/api/instances/${id}/logout`, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
            });
            window.showToast?.('success', 'Instance logged out');
            const card = this.getCard(id);
            if (card) card.status = 'disconnected';
        },

        getCard(id) {
            const el = document.querySelector(`[data-instance="${id}"]`);
            return el?._x_dataStack?.[0] ?? null;
        }
    }
}

// Per-card helpers (called from x-bind)
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
// Make available as Alpine methods
document.addEventListener('alpine:init', () => {
    Alpine.magic('statusBadge', () => statusBadge);
    Alpine.magic('statusDot', () => statusDot);
});
</script>
@endsection
