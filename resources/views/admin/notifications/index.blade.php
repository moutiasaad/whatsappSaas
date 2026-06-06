@extends('layouts.admin')

@section('title', __('ui.notifications_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.notifications_page.breadcrumb') }}</span>
@endsection

@section('content')
@php
    $panelPrefix = auth()->user()->routeNamePrefix();
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
            <div class="stat-card-value">{{ $stats['sent_total'] }}</div>
            <div class="stat-card-label">{{ __('ui.notifications_page.total_sent') }}</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-card-icon"><i class="ri-calendar-line"></i></div>
            <div class="stat-card-value">{{ $stats['sent_today'] }}</div>
            <div class="stat-card-label">{{ __('ui.notifications_page.sent_today') }}</div>
        </div>
        <div class="stat-card amber">
            <div class="stat-card-icon"><i class="ri-notification-badge-line"></i></div>
            <div class="stat-card-value">{{ $stats['unread_total'] }}</div>
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

                @if(session('success'))
                    <div class="alert alert-success" style="margin-bottom:1rem;padding:.75rem 1rem;background:#ecfdf5;border:1px solid #6ee7b7;border-radius:var(--radius);color:#065f46;font-size:.875rem;display:flex;gap:.5rem;align-items:flex-start;">
                        <i class="ri-checkbox-circle-line" style="flex-shrink:0;margin-top:1px;"></i>
                        {{ session('success') }}
                    </div>
                @endif

                <form method="POST" action="{{ route($panelPrefix . '.notifications.send') }}" @submit.prevent="submit($event)">
                    @csrf

                    @if($isSuperAdmin)
                    {{-- Tenant selector for super admin --}}
                    <div class="form-group" style="margin-bottom:1rem;">
                        <label class="form-label">{{ __('ui.notifications_page.tenant') }}</label>
                        <select name="tenant_id" class="form-control"
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
                                <input type="radio" name="recipient" value="all" x-model="recipient" style="accent-color:var(--brand);">
                                {{ __('ui.notifications_page.all_users') }}
                            </label>
                            <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.875rem;">
                                <input type="radio" name="recipient" value="specific" x-model="recipient" style="accent-color:var(--brand);">
                                {{ __('ui.notifications_page.specific_user') }}
                            </label>
                        </div>
                    </div>

                    {{-- User selector (visible when specific) --}}
                    <div class="form-group" style="margin-bottom:1rem;" x-show="recipient === 'specific'" x-cloak>
                        <label class="form-label">{{ __('ui.notifications_page.select_user') }}</label>
                        @if($isSuperAdmin)
                        <select name="user_id" class="form-control" style="width:100%;" x-ref="userSelect">
                            <option value="">{{ __('ui.notifications_page.choose_user') }}</option>
                            <template x-for="u in users" :key="u.id">
                                <option :value="u.id" x-text="`${u.name} (${u.email})`"></option>
                            </template>
                        </select>
                        @else
                        <select name="user_id" class="form-control" style="width:100%;">
                            <option value="">{{ __('ui.notifications_page.choose_user') }}</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                        @endif
                        @error('user_id')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Type --}}
                    <div class="form-group" style="margin-bottom:1rem;">
                        <label class="form-label">{{ __('ui.notifications_page.type') }}</label>
                        <select name="type" class="form-control" style="width:100%;">
                            <option value="manual">{{ __('ui.notifications_page.type_manual') }}</option>
                            <option value="renewal">{{ __('ui.notifications_page.type_renewal') }}</option>
                            <option value="system">{{ __('ui.notifications_page.type_system') }}</option>
                        </select>
                    </div>

                    {{-- Title --}}
                    <div class="form-group" style="margin-bottom:1rem;">
                        <label class="form-label">{{ __('ui.notifications_page.notif_title') }}</label>
                        <input type="text" name="title" class="form-control"
                               placeholder="{{ __('ui.notifications_page.title_placeholder') }}"
                               value="{{ old('title') }}" maxlength="255">
                        @error('title')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Body --}}
                    <div class="form-group" style="margin-bottom:1.25rem;">
                        <label class="form-label">{{ __('ui.notifications_page.message') }}</label>
                        <textarea name="body" class="form-control" rows="4"
                                  placeholder="{{ __('ui.notifications_page.message_placeholder') }}"
                                  maxlength="2000">{{ old('body') }}</textarea>
                        @error('body')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%;" :disabled="sending">
                        <i class="ri-send-plane-line"></i>
                        <span x-text="sending ? '{{ __('ui.processing') }}' : '{{ __('ui.notifications_page.send_btn') }}'"></span>
                    </button>
                </form>
            </div>
        </div>

        {{-- History --}}
        <div class="card">
            <div class="card-header">
                <div class="card-header-title">
                    <i class="ri-history-line" style="color:var(--brand)"></i>
                    {{ __('ui.notifications_page.history') }}
                </div>
            </div>

            @if($history->isEmpty())
                <div style="padding:3rem;text-align:center;color:var(--text-muted);">
                    <i class="ri-notification-off-line" style="font-size:2.5rem;display:block;margin-bottom:.5rem;"></i>
                    {{ __('ui.notifications_page.no_history') }}
                </div>
            @else
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
                            @foreach($history as $notif)
                            <tr>
                                <td>
                                    <div style="font-weight:500;font-size:.875rem;">{{ $notif->title }}</div>
                                    <div style="font-size:.75rem;color:var(--text-muted);margin-top:2px;">
                                        {{ \Illuminate\Support\Str::limit($notif->body, 80) }}
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $badgeClass = match($notif->type) {
                                            'renewal' => 'badge-amber',
                                            'system'  => 'badge-blue',
                                            default   => 'badge-green',
                                        };
                                    @endphp
                                    <span class="badge {{ $badgeClass }}">
                                        {{ __('ui.notifications_page.type_' . $notif->type) }}
                                    </span>
                                </td>
                                <td style="font-size:.8125rem;">
                                    {{ $notif->sender?->name ?? __('ui.notifications_page.system') }}
                                </td>
                                <td style="font-size:.8125rem;color:var(--text-muted);">
                                    {{ $notif->created_at->diffForHumans() }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($history->hasPages())
                    <div style="padding:1rem 1.25rem;border-top:1px solid var(--card-border);">
                        {{ $history->links() }}
                    </div>
                @endif
            @endif
        </div>

    </div>

</div>

<script>
function notificationsPage() {
    return {
        recipient: 'all',
        sending: false,
        users: [],

        init() {
            @if(session('success'))
                // scroll to top on success
                window.scrollTo(0, 0);
            @endif
        },

        async onTenantChange(tenantId) {
            this.users = [];
            if (!tenantId) return;
            try {
                const r = await fetch(`/api/notifications/tenant-users/${tenantId}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const json = await r.json();
                this.users = json.data || [];
            } catch (e) { /* ignore */ }
        },

        submit(e) {
            this.sending = true;
            e.target.submit();
        },
    };
}
</script>
@endsection
