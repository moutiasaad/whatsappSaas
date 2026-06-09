@extends('layouts.admin')

@section('title', __('ui.notifications_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.notifications_page.breadcrumb') }}</span>
@endsection

@section('content')
@php
    $panelPrefix  = auth()->user()->routeNamePrefix();
    $isSuperAdmin = auth()->user()->isSuperAdmin();
@endphp

<div x-data="notificationsPage()" x-init="init()" x-cloak>

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.notifications_page.title') }}</div>
            <div class="page-subtitle">{{ __('ui.notifications_page.subtitle') }}</div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.5rem;">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-send-plane-line"></i></div>
            <div class="stat-card-value" x-text="stats.sent_total">{{ $stats['sent_total'] }}</div>
            <div class="stat-card-label">{{ __('ui.notifications_page.total_sent') }}</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-card-icon"><i class="ri-calendar-line"></i></div>
            <div class="stat-card-value" x-text="stats.sent_today">{{ $stats['sent_today'] }}</div>
            <div class="stat-card-label">{{ __('ui.notifications_page.sent_today') }}</div>
        </div>
        <div class="stat-card amber">
            <div class="stat-card-icon"><i class="ri-notification-badge-line"></i></div>
            <div class="stat-card-value" x-text="stats.unread_total">{{ $stats['unread_total'] }}</div>
            <div class="stat-card-label">{{ __('ui.notifications_page.unread_total') }}</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1.5fr;gap:1.5rem;align-items:start;">

        {{-- Send Form --}}
        <div class="card">
            <div class="card-header">
                <div class="card-header-title">
                    <i class="ri-send-plane-line" style="color:var(--brand)"></i>
                    {{ __('ui.notifications_page.send_notification') }}
                </div>
            </div>
            <div class="card-body" style="padding:1.25rem;">

                {{-- Inline alert --}}
                <div x-show="alert.show" x-cloak
                     :class="alert.ok ? 'alert-success' : 'alert-error'"
                     style="margin-bottom:1rem;padding:.75rem 1rem;border-radius:var(--radius);font-size:.875rem;display:flex;gap:.5rem;align-items:flex-start;"
                     :style="alert.ok
                        ? 'background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46'
                        : 'background:#fef2f2;border:1px solid #fca5a5;color:#991b1b'">
                    <i :class="alert.ok ? 'ri-checkbox-circle-line' : 'ri-error-warning-line'" style="flex-shrink:0;margin-top:1px;"></i>
                    <span x-text="alert.message"></span>
                </div>

                @if($isSuperAdmin)
                {{-- Tenant selector --}}
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">{{ __('ui.notifications_page.tenant') }}</label>
                    <select class="form-control" x-model="form.tenant_id"
                            @change="onTenantChange($event.target.value)"
                            style="width:100%;">
                        <option value="">{{ __('ui.notifications_page.all_tenants') }}</option>
                        @foreach($tenants as $tenant)
                            <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                {{-- Recipient --}}
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">{{ __('ui.notifications_page.recipient') }}</label>
                    <div style="display:flex;gap:.75rem;">
                        <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.875rem;">
                            <input type="radio" name="recipient" value="all" x-model="form.recipient" style="accent-color:var(--brand);">
                            {{ __('ui.notifications_page.all_users') }}
                        </label>
                        <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.875rem;">
                            <input type="radio" name="recipient" value="specific" x-model="form.recipient" style="accent-color:var(--brand);">
                            {{ __('ui.notifications_page.specific_user') }}
                        </label>
                    </div>
                </div>

                {{-- User selector --}}
                <div class="form-group" style="margin-bottom:1rem;" x-show="form.recipient === 'specific'" x-cloak>
                    <label class="form-label">{{ __('ui.notifications_page.select_user') }}</label>
                    <select class="form-control" x-model="form.user_id" style="width:100%;" data-no-ss>
                        <option value="">{{ __('ui.notifications_page.choose_user') }}</option>
                        @if(!$isSuperAdmin)
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        @endif
                        <template x-if="{{ $isSuperAdmin ? 'true' : 'false' }}">
                            <template x-for="u in users" :key="u.id">
                                <option :value="u.id" x-text="`${u.name} (${u.email})`"></option>
                            </template>
                        </template>
                    </select>
                </div>

                {{-- Type --}}
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">{{ __('ui.notifications_page.type') }}</label>
                    <select class="form-control" x-model="form.type" style="width:100%;" data-no-ss>
                        <option value="manual">{{ __('ui.notifications_page.type_manual') }}</option>
                        <option value="renewal">{{ __('ui.notifications_page.type_renewal') }}</option>
                        <option value="system">{{ __('ui.notifications_page.type_system') }}</option>
                    </select>
                </div>

                {{-- Title --}}
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">{{ __('ui.notifications_page.notif_title') }}</label>
                    <input type="text" class="form-control" x-model="form.title"
                           placeholder="{{ __('ui.notifications_page.title_placeholder') }}"
                           maxlength="255">
                </div>

                {{-- Body --}}
                <div class="form-group" style="margin-bottom:1.25rem;">
                    <label class="form-label">{{ __('ui.notifications_page.message') }}</label>
                    <textarea class="form-control" x-model="form.body" rows="4"
                              placeholder="{{ __('ui.notifications_page.message_placeholder') }}"
                              maxlength="2000"></textarea>
                </div>

                <button type="button" class="btn btn-primary" style="width:100%;"
                        :disabled="sending" @click="send()">
                    <template x-if="!sending">
                        <span><i class="ri-send-plane-line"></i> {{ __('ui.notifications_page.send_btn') }}</span>
                    </template>
                    <template x-if="sending">
                        <span><span class="btn-spinner"></span> {{ __('ui.processing') }}</span>
                    </template>
                </button>
            </div>
        </div>

        {{-- History --}}
        <div class="card">
            <div class="card-header">
                <div class="card-header-title">
                    <i class="ri-history-line" style="color:var(--brand)"></i>
                    {{ __('ui.notifications_page.history') }}
                </div>
                <button class="btn btn-sm btn-outline" @click="loadHistory(1)">
                    <i class="ri-refresh-line"></i>
                </button>
            </div>

            <div x-show="historyLoading" style="padding:2rem;text-align:center;color:var(--text-muted);">
                <i class="ri-loader-4-line" style="font-size:1.5rem;"></i>
            </div>

            <div x-show="!historyLoading && history.length === 0" style="padding:3rem;text-align:center;color:var(--text-muted);" x-cloak>
                <i class="ri-notification-off-line" style="font-size:2.5rem;display:block;margin-bottom:.5rem;"></i>
                {{ __('ui.notifications_page.no_history') }}
            </div>

            <div x-show="!historyLoading && history.length > 0" x-cloak>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ __('ui.notifications_page.col_title') }}</th>
                                <th>{{ __('ui.notifications_page.col_type') }}</th>
                                <th>{{ __('ui.notifications_page.col_sent_by') }}</th>
                                <th>{{ __('ui.notifications_page.col_date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="n in history" :key="n.id">
                                <tr>
                                    <td>
                                        <div style="font-weight:500;font-size:.875rem;" x-text="n.title"></div>
                                        <div style="font-size:.75rem;color:var(--text-muted);margin-top:2px;" x-text="n.body_preview"></div>
                                    </td>
                                    <td>
                                        <span class="badge" :class="`badge-${typeBadge(n.type)}`" x-text="n.type_label"></span>
                                    </td>
                                    <td style="font-size:.8125rem;" x-text="n.sender_name"></td>
                                    <td style="font-size:.8125rem;color:var(--text-muted);" x-text="n.date"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.25rem;border-top:1px solid var(--card-border);">
                    <span style="font-size:.8125rem;color:var(--text-muted);">
                        {{ __('ui.page') ?? 'Page' }} <span x-text="historyPage"></span> / <span x-text="historyLastPage"></span>
                    </span>
                    <div style="display:flex;gap:.5rem;">
                        <button class="btn btn-sm btn-outline" :disabled="historyPage <= 1" @click="loadHistory(historyPage - 1)">
                            <i class="ri-arrow-left-s-line"></i>
                        </button>
                        <button class="btn btn-sm btn-outline" :disabled="historyPage >= historyLastPage" @click="loadHistory(historyPage + 1)">
                            <i class="ri-arrow-right-s-line"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
function notificationsPage() {
    return {
        form: {
            tenant_id: '',
            recipient: 'all',
            user_id:   '',
            type:      'manual',
            title:     '',
            body:      '',
        },
        sending: false,
        alert: { show: false, ok: true, message: '' },
        users: [],

        stats: {
            sent_total:   {{ $stats['sent_total'] }},
            sent_today:   {{ $stats['sent_today'] }},
            unread_total: {{ $stats['unread_total'] }},
        },

        history:         [],
        historyLoading:  true,
        historyPage:     1,
        historyLastPage: 1,

        typeBadge(type) {
            return { manual: 'green', renewal: 'amber', system: 'blue' }[type] || 'green';
        },

        init() {
            this.loadHistory(1);
        },

        async onTenantChange(tenantId) {
            this.users = [];
            this.form.user_id = '';
            if (!tenantId) return;
            try {
                const r = await fetch(`/api/notifications/tenant-users/${tenantId}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const d = await r.json();
                this.users = d.data || [];
            } catch (e) { /* silent */ }
        },

        async send() {
            if (!this.form.title.trim() || !this.form.body.trim()) {
                this.showAlert(false, '{{ __('ui.notifications_page.title_placeholder') }}');
                return;
            }
            if (this.form.recipient === 'specific' && !this.form.user_id) {
                this.showAlert(false, '{{ __('ui.notifications_page.choose_user') }}');
                return;
            }

            this.sending = true;
            this.alert.show = false;

            try {
                const r = await fetch('{{ route($panelPrefix . '.notifications.send') }}', {
                    method:  'POST',
                    headers: {
                        'Content-Type':     'application/json',
                        'Accept':           'application/json',
                        'X-CSRF-TOKEN':     document.querySelector('meta[name=csrf-token]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(this.form),
                });
                const d = await r.json();

                if (d.ok) {
                    this.showAlert(true, d.message);
                    this.stats.sent_total += d.count ?? 1;
                    this.stats.sent_today += d.count ?? 1;
                    this.form.title = '';
                    this.form.body  = '';
                    this.loadHistory(1);
                } else {
                    this.showAlert(false, d.message || '{{ __('ui.notifications_page.send_error') }}');
                }
            } catch (e) {
                this.showAlert(false, @json(__('ui.notifications_page.request_failed')));
            } finally {
                this.sending = false;
            }
        },

        showAlert(ok, message) {
            this.alert = { show: true, ok, message };
            if (ok) setTimeout(() => { this.alert.show = false; }, 4000);
        },

        async loadHistory(page) {
            this.historyLoading = true;
            try {
                const r = await fetch(`/api/notifications/history?page=${page}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const d = await r.json();
                this.history         = d.data     || [];
                this.historyPage     = d.current_page  || 1;
                this.historyLastPage = d.last_page || 1;
            } catch (e) { /* silent */ } finally {
                this.historyLoading = false;
            }
        },

        appendToHistory(n) {
            this.history.unshift(n);
            if (this.history.length > 20) this.history.pop();
            this.stats.sent_total++;
            this.stats.sent_today++;
        },
    };
}
</script>
@endsection
