@extends('layouts.admin')

@section('title', $tenant->name)

@section('breadcrumb')
    <span>{{ __('ui.platform_tenants_show_page.breadcrumb_root') }}</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <a href="{{ route('super_admin.platform.tenants') }}" style="color:var(--text-secondary);text-decoration:none">{{ __('ui.platform_tenants_show_page.breadcrumb') }}</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ $tenant->name }}</span>
@endsection

@php
    // Which column the two "plan date" actions target, and the current value
    // in that column. `setDateDefault` seeds the date-picker with the current
    // end date when it's still in the future, otherwise tomorrow — the
    // controller rejects past dates anyway, so tomorrow is the safe fallback.
    $planColumn      = $tenant->subscription_status === 'trial' ? 'trial_ends_at' : 'subscription_ends_at';
    $currentEndDate  = $tenant->{$planColumn};
    $setDateDefault  = ($currentEndDate && $currentEndDate->isFuture())
        ? $currentEndDate->format('Y-m-d')
        : now()->addDay()->format('Y-m-d');
@endphp

@section('content')
{{-- x-data at the root so both plan-date modals (rendered at the bottom of
     the page) share the same Alpine scope as the header buttons that open
     them. `days` starts at 30 = most common operator ask. --}}
<div x-data="{
    extendOpen: false, days: 30, reason: '',
    dateOpen: false, endDate: @js($setDateDefault), dateReason: ''
}">
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ $tenant->name }}</div>
            <div class="page-subtitle">{{ __('ui.platform_tenants_show_page.tenant_slug') }}: {{ $tenant->slug }}</div>
        </div>
        <div class="page-header-actions" style="flex-wrap:wrap;gap:.5rem;">
            <a href="{{ route('super_admin.platform.tenants') }}" class="btn btn-outline">
                <i class="ri-arrow-left-line"></i> {{ __('ui.back') }}
            </a>

            {{-- Extend the tenant's plan window. Opens the modal defined at
                 the bottom of this file. Green because it's an operator
                 courtesy (granting time), not a punitive action. --}}
            <button type="button" @click="extendOpen = true; days = 30; reason = ''"
                    class="btn btn-outline" style="color:var(--brand);border-color:rgba(16,185,129,.4);">
                <i class="ri-calendar-2-line"></i> {{ __('ui.platform_tenants_show_page.extend_plan') }}
            </button>

            {{-- Replace the end date with an operator-picked date. Distinct
                 button from Extend because the two speak to different intents
                 — "give them more time" vs. "the date should be X". --}}
            <button type="button" @click="dateOpen = true; endDate = @js($setDateDefault); dateReason = ''"
                    class="btn btn-outline" style="color:var(--brand);border-color:rgba(16,185,129,.4);">
                <i class="ri-calendar-event-line"></i> {{ __('ui.platform_tenants_show_page.set_plan_date') }}
            </button>

            {{-- Block / Unblock. One form; label + color flip on tenant.is_active.
                 Plain form + native confirm — same pattern as expire-trial below. --}}
            <form method="POST" action="{{ route('super_admin.platform.tenants.toggle-active', $tenant) }}"
                  onsubmit="return confirm(@js($tenant->is_active ? __('ui.platform_tenants_page.block_prompt', ['name' => $tenant->name]) : __('ui.platform_tenants_page.unblock_prompt', ['name' => $tenant->name])))"
                  style="margin:0;">
                @csrf
                @method('PATCH')
                @if($tenant->is_active)
                    <button type="submit" class="btn btn-outline" style="color:#b91c1c;border-color:#fecaca;">
                        <i class="ri-forbid-2-line"></i> {{ __('ui.platform_tenants_page.block') }}
                    </button>
                @else
                    <button type="submit" class="btn btn-outline" style="color:var(--brand);border-color:rgba(16,185,129,.4);">
                        <i class="ri-checkbox-circle-line"></i> {{ __('ui.platform_tenants_page.unblock') }}
                    </button>
                @endif
            </form>

            {{-- Archive / Restore. Archive soft-hides the tenant from the
                 default list, block is a live suspension that stays visible. --}}
            <form method="POST" action="{{ route('super_admin.platform.tenants.toggle-archive', $tenant) }}"
                  onsubmit="return confirm(@js($tenant->archived_at ? __('ui.platform_tenants_page.restore_prompt', ['name' => $tenant->name]) : __('ui.platform_tenants_page.archive_prompt', ['name' => $tenant->name])))"
                  style="margin:0;">
                @csrf
                @method('PATCH')
                @if($tenant->archived_at)
                    <button type="submit" class="btn btn-outline" style="color:var(--brand);border-color:rgba(16,185,129,.4);">
                        <i class="ri-inbox-unarchive-line"></i> {{ __('ui.platform_tenants_page.restore') }}
                    </button>
                @else
                    <button type="submit" class="btn btn-outline">
                        <i class="ri-inbox-archive-line"></i> {{ __('ui.platform_tenants_page.archive') }}
                    </button>
                @endif
            </form>

            {{-- Delete. Uses the global confirmDelete modal from admin.blade.php
                 so the irreversible-warning wording is consistent with every
                 other destructive action in the panel. --}}
            <button type="button" class="btn btn-danger"
                    onclick="confirmDelete('{{ route('super_admin.platform.tenants.destroy', $tenant) }}', { title: @js(__('ui.platform_tenants_page.delete_prompt', ['name' => $tenant->name])), message: @js(__('ui.platform_tenants_page.delete_message')) })">
                <i class="ri-delete-bin-line"></i> {{ __('ui.platform_tenants_page.delete') }}
            </button>

            <a href="{{ route('super_admin.platform.tenants.edit', $tenant) }}" class="btn btn-primary">
                <i class="ri-pencil-line"></i> {{ __('ui.platform_tenants_show_page.edit_tenant') }}
            </a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-team-line"></i></div>
            <div class="stat-card-value">{{ number_format($tenant->users_count) }}</div>
            <div class="stat-card-label">{{ __('ui.platform_tenants_show_page.users') }}</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-card-icon"><i class="ri-group-line"></i></div>
            <div class="stat-card-value">{{ number_format($tenant->teams_count) }}</div>
            <div class="stat-card-label">{{ __('ui.platform_tenants_show_page.teams') }}</div>
        </div>
        <div class="stat-card purple">
            <div class="stat-card-icon"><i class="ri-whatsapp-line"></i></div>
            <div class="stat-card-value">{{ number_format($tenant->instances_count) }}</div>
            <div class="stat-card-label">{{ __('ui.platform_tenants_show_page.instances') }}</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-card-icon"><i class="ri-message-3-line"></i></div>
            <div class="stat-card-value">{{ number_format($tenant->conversations_count) }}</div>
            <div class="stat-card-label">{{ __('ui.platform_tenants_show_page.conversations') }}</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:1.25rem;align-items:start;">
        <div class="card">
            <div class="card-header">
                <div class="card-title">{{ __('ui.platform_tenants_show_page.tenant_information') }}</div>
            </div>
            <div style="padding:20px;">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.platform_tenants_show_page.plan') }}</label>
                        <div class="form-control" style="display:flex;align-items:center;">
                            {{ $tenant->plan?->name ?? __('ui.platform_tenants_page.no_plan') }}
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.platform_tenants_show_page.subscription_status') }}</label>
                        <div style="height:40px;display:flex;align-items:center;">
                            @php
                                $statusBadge = match($tenant->subscription_status) {
                                    'active' => 'badge-green',
                                    'trial' => 'badge-orange',
                                    'suspended' => 'badge-red',
                                    default => 'badge-gray',
                                };
                                $statusKey = 'ui.platform_tenants_page.statuses.' . $tenant->subscription_status;
                                $statusLabel = __($statusKey);
                                if ($statusLabel === $statusKey) {
                                    $statusLabel = ucfirst($tenant->subscription_status);
                                }
                            @endphp
                            <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                            @if(!$tenant->is_active)
                                <span class="badge badge-gray" style="margin-left:6px;">{{ __('ui.platform_tenants_page.inactive') }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.platform_tenants_show_page.trial_ends_at') }}</label>
                        <div class="form-control" style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                            <span>{{ $tenant->trial_ends_at?->format('M j, Y') ?? __('ui.platform_tenants_show_page.not_set') }}</span>
                            {{-- QA-only lever: back-date trial_ends_at to
                                 yesterday so post-trial behavior can be
                                 tested immediately. Only rendered when the
                                 tenant is actually on trial. --}}
                            @if($tenant->isOnTrial())
                                <form method="POST" action="{{ route('super_admin.platform.tenants.expire-trial', $tenant) }}" onsubmit="return confirm(@js(__('ui.platform_tenants_show_page.expire_trial_confirm', ['name' => $tenant->name])))" style="margin:0;">
                                    @csrf
                                    <button type="submit" class="btn btn-outline" style="padding:2px 10px;font-size:12px;color:#b91c1c;border-color:#fecaca;">
                                        <i class="ri-time-line"></i>
                                        {{ __('ui.platform_tenants_show_page.expire_trial_now') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.platform_tenants_show_page.stripe_customer_id') }}</label>
                        <div class="form-control" style="display:flex;align-items:center;font-family:monospace;">
                            {{ $tenant->stripe_id ?: __('ui.platform_tenants_show_page.not_set') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:1rem;">
            <div class="card">
                <div class="card-header" style="padding-bottom:12px;">
                    <div class="card-title">{{ __('ui.platform_tenants_show_page.meta') }}</div>
                </div>
                <div style="padding:0 18px 18px 18px;display:flex;flex-direction:column;gap:8px;font-size:13px;">
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-muted);">{{ __('ui.platform_tenants_show_page.created') }}</span>
                        <strong>{{ $tenant->created_at?->format('M j, Y') }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-muted);">{{ __('ui.platform_tenants_show_page.last_updated') }}</span>
                        <strong>{{ $tenant->updated_at?->diffForHumans() }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-muted);">{{ __('ui.platform_tenants_show_page.customers') }}</span>
                        <strong>{{ number_format($tenant->customers_count) }}</strong>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="padding-bottom:12px;">
                    <div class="card-title">{{ __('ui.platform_tenants_show_page.recent_users') }}</div>
                </div>
                <div style="padding:0 18px 18px 18px;display:flex;flex-direction:column;gap:8px;">
                    @forelse($tenant->users as $user)
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 10px;background:var(--page-bg);border-radius:8px;">
                            <div style="min-width:0;">
                                <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $user->name }}</div>
                                <div style="font-size:12px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $user->email }}</div>
                            </div>
                            @php
                                $roleKey = 'ui.roles.' . $user->role;
                                $roleLabel = __($roleKey);
                                if ($roleLabel === $roleKey) {
                                    $roleLabel = ucfirst($user->role);
                                }
                            @endphp
                            <span class="badge badge-gray">{{ $roleLabel }}</span>
                        </div>
                    @empty
                        <div style="font-size:13px;color:var(--text-muted);">{{ __('ui.platform_tenants_show_page.no_users_yet') }}</div>
                    @endforelse
                </div>
            </div>

            {{-- Plan change history — only rendered when at least one change
                 has been recorded. Rows come from the AuditLog ledger so
                 this list and /admin-control-panel/audit-log always agree.
                 Renders two action types with distinct headlines. --}}
            @if($planExtensions->isNotEmpty())
            <div class="card">
                <div class="card-header" style="padding-bottom:12px;">
                    <div class="card-title">{{ __('ui.platform_tenants_show_page.recent_plan_changes') }}</div>
                </div>
                <div style="padding:0 18px 18px 18px;display:flex;flex-direction:column;gap:10px;">
                    @foreach($planExtensions as $log)
                        @php
                            $p       = (array) ($log->payload ?? []);
                            $isSet   = $log->action === 'tenant.plan_date_set';
                            $newIso  = $p['new'] ?? null;
                            $newFmt  = $newIso ? \Carbon\Carbon::parse($newIso)->format('M j, Y') : null;
                        @endphp
                        <div style="padding:10px;background:var(--page-bg);border-radius:8px;font-size:12.5px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                                @if($isSet)
                                    <strong>
                                        <i class="ri-calendar-event-line" style="color:var(--brand);"></i>
                                        {{ __('ui.platform_tenants_show_page.set_to_label') }}: {{ $newFmt ?? '—' }}
                                    </strong>
                                @else
                                    <strong>
                                        <i class="ri-calendar-2-line" style="color:var(--brand);"></i>
                                        +{{ (int) ($p['days'] ?? 0) }} {{ __('ui.platform_tenants_show_page.days_short') }}
                                    </strong>
                                @endif
                                <span style="color:var(--text-muted);font-size:11px;white-space:nowrap;">{{ $log->created_at?->diffForHumans() }}</span>
                            </div>
                            <div style="color:var(--text-muted);margin-top:2px;">
                                {{ __('ui.platform_tenants_show_page.extension_by') }}:
                                <strong style="color:var(--text-primary);">{{ $log->user?->name ?? $log->user?->email ?? '—' }}</strong>
                            </div>
                            @if(!empty($p['reason']))
                                <div style="color:var(--text-muted);margin-top:4px;font-style:italic;">"{{ $p['reason'] }}"</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Linked accounts (fraud / abuse detection) — other tenants that
         share a WhatsApp number (or, in future, other signals) with this
         one. Empty state kept quiet: most tenants have zero linkages, and
         a wall of "no links found" clutters every profile. --}}
    @if($linkedTenants->isNotEmpty())
    <div class="card" style="margin-top:1.25rem;border-left:3px solid #f59e0b;">
        <div class="card-header">
            <div class="card-title" style="display:flex;align-items:center;gap:.5rem;">
                <i class="ri-links-line" style="color:#f59e0b;"></i>
                {{ __('ui.platform_tenants_show_page.linked_accounts') }}
            </div>
            <span style="font-size:12px;color:var(--text-muted);">
                {{ $linkedTenants->count() }} {{ __('ui.platform_tenants_show_page.linked_accounts_count') }}
            </span>
        </div>
        <div style="padding:8px 20px 20px;">
            <p style="font-size:12px;color:var(--text-muted);margin:0 0 12px;">
                {{ __('ui.platform_tenants_show_page.linked_accounts_hint') }}
            </p>
            <div style="display:flex;flex-direction:column;gap:8px;">
                @foreach($linkedTenants as $linked)
                    @php $link = $linked->pivot_link; @endphp
                    <a href="{{ route('super_admin.platform.tenants.show', $linked) }}"
                       style="display:flex;align-items:center;gap:12px;padding:10px 12px;border:1px solid var(--card-border);border-radius:8px;text-decoration:none;color:inherit;background:var(--page-bg);">
                        <div style="width:32px;height:32px;border-radius:6px;background:rgba(245,158,11,.15);color:#f59e0b;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0;">
                            {{ mb_strtoupper(mb_substr($linked->name ?? '?', 0, 1)) }}
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:600;font-size:14px;">{{ $linked->name }}</div>
                            <div style="font-size:12px;color:var(--text-muted);">
                                {{ __('ui.platform_tenants_show_page.linked_reason_' . $link->reason) }}
                                @php $phones = data_get($link->evidence, 'phone_numbers', []); @endphp
                                @if(!empty($phones))
                                    &middot; <code style="font-family:inherit;background:none;padding:0;">{{ implode(', ', $phones) }}</code>
                                @endif
                                &middot; {{ __('ui.platform_tenants_show_page.linked_first_detected') }}
                                {{ $link->first_detected_at?->diffForHumans() }}
                            </div>
                        </div>
                        <i class="ri-arrow-right-s-line" style="color:var(--text-muted);font-size:18px;flex-shrink:0;"></i>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Claude API cost — this tenant's own Anthropic spend, priced from
         the ai_api_usages ledger. Reuses the source/model labels from the
         platform-wide report so a super admin sees consistent language on
         both pages. --}}
    @php
        $fmtUsd    = fn ($n) => '$' . number_format((float) $n, 2);
        $fmtUsd4   = fn ($n) => '$' . number_format((float) $n, 4);
        $fmtTokens = function ($n) {
            $n = (int) $n;
            if ($n >= 1_000_000) return number_format($n / 1_000_000, 2) . 'M';
            if ($n >= 1_000)     return number_format($n / 1_000, 1) . 'k';
            return number_format($n);
        };
        $sourceLabels = [
            'whatsapp'  => __('ui.claude_usage.source_whatsapp'),
            'webchat'   => __('ui.claude_usage.source_webchat'),
            'messenger' => __('ui.claude_usage.source_messenger'),
            'title'     => __('ui.claude_usage.source_title'),
            'ask'       => __('ui.claude_usage.source_ask'),
        ];
    @endphp

    <div class="card" style="margin-top:1.25rem;">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <div>
                <div class="card-title">{{ __('ui.platform_tenants_show_page.claude_cost_title') }}</div>
                <div class="card-subtitle">{{ __('ui.platform_tenants_show_page.claude_cost_subtitle') }}</div>
            </div>
            <a href="{{ route('super_admin.platform.claude-usage') }}" class="btn btn-outline" style="font-size:12px;padding:4px 12px;">
                <i class="ri-external-link-line"></i> {{ __('ui.platform_tenants_show_page.claude_view_platform') }}
            </a>
        </div>

        @if(($claudeLifetime->calls ?? 0) === 0)
            <div style="padding:24px 20px;text-align:center;color:var(--text-muted);font-size:13px;">
                <i class="ri-cpu-line" style="font-size:24px;display:block;margin-bottom:8px;"></i>
                {{ __('ui.platform_tenants_show_page.claude_no_data') }}
            </div>
        @else
            {{-- KPI banner --}}
            <div style="padding:8px 20px 20px;display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
                <div style="padding:12px;background:var(--page-bg);border-radius:8px;">
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">{{ __('ui.platform_tenants_show_page.claude_spend_30d') }}</div>
                    <div style="font-size:20px;font-weight:700;margin-top:4px;">{{ $fmtUsd($claude30d->cost ?? 0) }}</div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ number_format((int) ($claude30d->calls ?? 0)) }} {{ __('ui.claude_usage.calls_word') }}</div>
                </div>
                <div style="padding:12px;background:var(--page-bg);border-radius:8px;">
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">{{ __('ui.platform_tenants_show_page.claude_spend_lifetime') }}</div>
                    <div style="font-size:20px;font-weight:700;margin-top:4px;">{{ $fmtUsd($claudeLifetime->cost ?? 0) }}</div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ number_format((int) ($claudeLifetime->calls ?? 0)) }} {{ __('ui.claude_usage.calls_word') }}</div>
                </div>
                <div style="padding:12px;background:var(--page-bg);border-radius:8px;">
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">{{ __('ui.platform_tenants_show_page.claude_input_tokens') }}</div>
                    <div style="font-size:20px;font-weight:700;margin-top:4px;">{{ $fmtTokens($claudeLifetime->in_tok ?? 0) }}</div>
                </div>
                <div style="padding:12px;background:var(--page-bg);border-radius:8px;">
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">{{ __('ui.platform_tenants_show_page.claude_output_tokens') }}</div>
                    <div style="font-size:20px;font-weight:700;margin-top:4px;">{{ $fmtTokens($claudeLifetime->out_tok ?? 0) }}</div>
                </div>
            </div>

            {{-- Two-column split: by-model / by-source --}}
            <div style="padding:0 20px 16px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div style="border:1px solid var(--card-border);border-radius:8px;padding:12px 14px;">
                    <div style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">{{ __('ui.claude_usage.by_model_title') }}</div>
                    @foreach($claudeByModel as $row)
                        <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:13px;">
                            <span style="font-family:var(--font-mono);font-size:11px;color:var(--text-muted);">{{ $row->model }}</span>
                            <strong>{{ $fmtUsd($row->cost) }}</strong>
                        </div>
                    @endforeach
                </div>
                <div style="border:1px solid var(--card-border);border-radius:8px;padding:12px 14px;">
                    <div style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">{{ __('ui.claude_usage.by_source_title') }}</div>
                    @foreach($claudeBySource as $row)
                        <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:13px;">
                            <span>{{ $sourceLabels[$row->source] ?? $row->source }} <span style="color:var(--text-muted);font-size:11px;">· {{ number_format((int) $row->calls) }}</span></span>
                            <strong>{{ $fmtUsd($row->cost) }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Last 10 calls — spot-check that fresh calls are billing correctly. --}}
            <div class="table-container" style="border-radius:0 0 12px 12px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.claude_usage.col_when') }}</th>
                            <th>{{ __('ui.claude_usage.col_source') }}</th>
                            <th>{{ __('ui.claude_usage.col_model') }}</th>
                            <th style="text-align:end;">{{ __('ui.claude_usage.col_input_tokens') }}</th>
                            <th style="text-align:end;">{{ __('ui.claude_usage.col_output_tokens') }}</th>
                            <th style="text-align:end;">{{ __('ui.claude_usage.col_spend') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($claudeRecent as $row)
                            <tr>
                                <td style="color:var(--text-muted);font-size:12px;">{{ $row->created_at?->diffForHumans() }}</td>
                                <td><span class="badge">{{ $sourceLabels[$row->source] ?? $row->source }}</span></td>
                                <td style="color:var(--text-muted);font-family:var(--font-mono);font-size:11px;">{{ $row->model }}</td>
                                <td style="text-align:end;color:var(--text-muted);">{{ number_format((int) $row->input_tokens) }}</td>
                                <td style="text-align:end;color:var(--text-muted);">{{ number_format((int) $row->output_tokens) }}</td>
                                <td style="text-align:end;font-weight:600;">{{ $fmtUsd4($row->cost_usd) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Payment history --}}
    <div class="card" style="margin-top:1.25rem;">
        <div class="card-header">
            <div class="card-title">{{ __('ui.platform_tenants_show_page.payment_history') }}</div>
            <span style="font-size:12px;color:var(--text-muted);">{{ $payments->count() }} {{ __('ui.platform_tenants_show_page.payment_records') }}</span>
        </div>

        @if($payments->isEmpty())
            <div style="padding:24px 20px;text-align:center;color:var(--text-muted);font-size:13px;">
                <i class="ri-bank-card-line" style="font-size:24px;display:block;margin-bottom:8px;"></i>
                {{ __('ui.platform_tenants_show_page.no_payments') }}
            </div>
        @else
            <div class="table-container" style="border-radius:0 0 12px 12px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.platform_tenants_show_page.payment_date') }}</th>
                            <th>{{ __('ui.platform_tenants_show_page.plan') }}</th>
                            <th>{{ __('ui.platform_tenants_show_page.amount') }}</th>
                            <th>{{ __('ui.platform_tenants_show_page.status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payments as $payment)
                            @php
                                $statusClass = match($payment->status) {
                                    'completed' => 'badge-green',
                                    'pending'   => 'badge-orange',
                                    'failed'    => 'badge-red',
                                    default     => 'badge-gray',
                                };
                            @endphp
                            <tr style="cursor:pointer;" onclick="window.location='{{ route('super_admin.billing.payment.show', $payment) }}'">
                                <td>{{ $payment->paid_at?->format('d/m/Y') ?? $payment->created_at->format('d/m/Y') }}</td>
                                <td>{{ $payment->plan?->name ?? '—' }}</td>
                                <td style="font-weight:600;">{{ number_format($payment->amount, 2) }} {{ strtoupper($payment->currency) }}</td>
                                <td><span class="badge {{ $statusClass }}">{{ ucfirst($payment->status) }}</span></td>
                                <td style="text-align:right;color:var(--text-muted);"><i class="ri-arrow-right-s-line"></i></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Extend Plan Modal — real modal-overlay so it sits above the sidebar
         and dims the page. Teleport to body so the admin layout's z-index
         doesn't clip it (feedback_admin_modal_z_index memory). --}}
    <template x-teleport="body">
        <div x-show="extendOpen" x-cloak
             class="modal-overlay show" role="dialog" aria-modal="true"
             @click.self="extendOpen = false"
             @keydown.escape.window="extendOpen = false"
             style="z-index:10000;">
            <div class="modal-box" style="max-width:480px;">
                <div class="modal-icon" style="color:var(--brand);background:rgba(16,185,129,.15);">
                    <i class="ri-calendar-2-line"></i>
                </div>
                <h3>{{ __('ui.platform_tenants_show_page.extend_plan_title', ['name' => $tenant->name]) }}</h3>
                <p style="color:var(--text-muted);font-size:13px;">
                    {{ __($tenant->subscription_status === 'trial' ? 'ui.platform_tenants_show_page.extend_plan_body_trial' : 'ui.platform_tenants_show_page.extend_plan_body_paid') }}
                </p>

                <form method="POST" action="{{ route('super_admin.platform.tenants.extend-plan', $tenant) }}"
                      style="margin-top:16px;display:flex;flex-direction:column;gap:12px;text-align:left;">
                    @csrf

                    <div>
                        <label class="form-label" style="display:block;margin-bottom:6px;">
                            {{ __('ui.platform_tenants_show_page.extend_plan_days_label') }}
                        </label>
                        {{-- Quick picks. Clicking sets `days` and gives the
                             chosen option a filled look; the input below stays
                             the source of truth so custom values still work. --}}
                        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px;">
                            @foreach([7, 14, 30, 60, 90] as $preset)
                                <button type="button" @click="days = {{ $preset }}"
                                        :class="days === {{ $preset }} ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm'"
                                        style="padding:4px 12px;">
                                    +{{ $preset }} {{ __('ui.platform_tenants_show_page.days_short') }}
                                </button>
                            @endforeach
                        </div>
                        <input type="number" name="days" x-model.number="days"
                               min="1" max="365" required
                               class="form-control" style="max-width:160px;">
                    </div>

                    <div>
                        <label class="form-label" style="display:block;margin-bottom:6px;">
                            {{ __('ui.platform_tenants_show_page.extend_plan_reason_label') }}
                            <span style="color:var(--text-muted);font-weight:400;">
                                ({{ __('ui.platform_tenants_show_page.extend_plan_reason_hint') }})
                            </span>
                        </label>
                        <textarea name="reason" x-model="reason" rows="2" maxlength="500"
                                  class="form-control"
                                  placeholder="{{ __('ui.platform_tenants_show_page.extend_plan_reason_placeholder') }}"></textarea>
                    </div>

                    <div class="modal-actions" style="margin-top:8px;">
                        <button type="button" @click="extendOpen = false" class="btn btn-outline">
                            {{ __('ui.cancel') }}
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-check-line"></i>
                            {{ __('ui.platform_tenants_show_page.extend_plan_confirm') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    {{-- Set Plan Date Modal — replaces the end date with an operator-picked
         one. Teleported for the same z-index reason as the Extend modal. --}}
    <template x-teleport="body">
        <div x-show="dateOpen" x-cloak
             class="modal-overlay show" role="dialog" aria-modal="true"
             @click.self="dateOpen = false"
             @keydown.escape.window="dateOpen = false"
             style="z-index:10000;">
            <div class="modal-box" style="max-width:480px;">
                <div class="modal-icon" style="color:var(--brand);background:rgba(16,185,129,.15);">
                    <i class="ri-calendar-event-line"></i>
                </div>
                <h3>{{ __('ui.platform_tenants_show_page.set_plan_date_title', ['name' => $tenant->name]) }}</h3>
                <p style="color:var(--text-muted);font-size:13px;">
                    {{ __($tenant->subscription_status === 'trial' ? 'ui.platform_tenants_show_page.set_plan_date_body_trial' : 'ui.platform_tenants_show_page.set_plan_date_body_paid') }}
                </p>

                @if($currentEndDate)
                    <p style="color:var(--text-muted);font-size:12px;margin-top:4px;">
                        {{ __('ui.platform_tenants_show_page.current_end_date') }}:
                        <strong style="color:var(--text-primary);">{{ $currentEndDate->format('M j, Y') }}</strong>
                    </p>
                @endif

                <form method="POST" action="{{ route('super_admin.platform.tenants.set-plan-date', $tenant) }}"
                      style="margin-top:16px;display:flex;flex-direction:column;gap:12px;text-align:left;">
                    @csrf

                    <div>
                        <label class="form-label" style="display:block;margin-bottom:6px;">
                            {{ __('ui.platform_tenants_show_page.set_plan_date_label') }}
                        </label>
                        <input type="date" name="end_date" x-model="endDate"
                               min="{{ now()->format('Y-m-d') }}" required
                               class="form-control" style="max-width:220px;">
                    </div>

                    <div>
                        <label class="form-label" style="display:block;margin-bottom:6px;">
                            {{ __('ui.platform_tenants_show_page.extend_plan_reason_label') }}
                            <span style="color:var(--text-muted);font-weight:400;">
                                ({{ __('ui.platform_tenants_show_page.extend_plan_reason_hint') }})
                            </span>
                        </label>
                        <textarea name="reason" x-model="dateReason" rows="2" maxlength="500"
                                  class="form-control"
                                  placeholder="{{ __('ui.platform_tenants_show_page.set_plan_date_reason_placeholder') }}"></textarea>
                    </div>

                    <div class="modal-actions" style="margin-top:8px;">
                        <button type="button" @click="dateOpen = false" class="btn btn-outline">
                            {{ __('ui.cancel') }}
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-check-line"></i>
                            {{ __('ui.platform_tenants_show_page.set_plan_date_confirm') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
@endsection
