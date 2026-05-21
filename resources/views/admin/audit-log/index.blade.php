@extends('layouts.admin')

@section('title', __('ui.audit_log_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.audit_log_page.breadcrumb') }}</span>
@endsection

@section('content')

    {{-- Header --}}
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.audit_log_page.page_title') }}</div>
            <div class="page-subtitle">{{ __('ui.audit_log_page.subtitle') }}</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('admin.audit-log.index', array_merge(request()->all(), ['export' => 1])) }}" class="btn btn-outline btn-sm">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                {{ __('ui.audit_log_page.export_csv') }}
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET">
        <div class="table-toolbar" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-lg);margin-bottom:1rem">
            <div class="filter-input-wrap">
                <i class="ri-search-line"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('ui.audit_log_page.search_placeholder') }}" class="filter-input">
            </div>
            <select name="user_id" onchange="this.form.submit()" class="toolbar-select">
                <option value="">{{ __('ui.audit_log_page.all_users') }}</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') }}">
            <input type="date" name="to" value="{{ request('to') }}">
            <button type="submit" class="btn btn-outline btn-sm">{{ __('ui.audit_log_page.filter') }}</button>
            @if(request()->hasAny(['search','user_id','from','to']))
                <a href="{{ route('admin.audit-log.index') }}" class="btn btn-ghost btn-sm">{{ __('ui.audit_log_page.clear') }}</a>
            @endif
        </div>
    </form>

    {{-- Table --}}
    <div class="card" style="padding:0">
        @if($logs->isEmpty())
            <div class="empty-state" style="padding:3rem">
                <div class="empty-state-icon">
                    <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <h4>{{ __('ui.audit_log_page.no_entries') }}</h4>
                <p>{{ __('ui.audit_log_page.empty_hint') }}</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.audit_log_page.when') }}</th>
                            <th>{{ __('ui.audit_log_page.user') }}</th>
                            <th>{{ __('ui.audit_log_page.action') }}</th>
                            <th>{{ __('ui.audit_log_page.target') }}</th>
                            <th>{{ __('ui.audit_log_page.ip_address') }}</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                        @php
                            $actionLabels = [
                                'ai_settings.updated' => __('ui.audit_log_page.actions.ai_settings_updated'),
                                'instance.created' => __('ui.audit_log_page.actions.instance_created'),
                                'instance.updated' => __('ui.audit_log_page.actions.instance_updated'),
                                'instance.deleted' => __('ui.audit_log_page.actions.instance_deleted'),
                                'knowledge.created' => __('ui.audit_log_page.actions.knowledge_created'),
                                'knowledge.updated' => __('ui.audit_log_page.actions.knowledge_updated'),
                                'knowledge.deleted' => __('ui.audit_log_page.actions.knowledge_deleted'),
                                'team.created' => __('ui.audit_log_page.actions.team_created'),
                                'team.updated' => __('ui.audit_log_page.actions.team_updated'),
                                'team.deleted' => __('ui.audit_log_page.actions.team_deleted'),
                                'user.created' => __('ui.audit_log_page.actions.user_created'),
                                'user.updated' => __('ui.audit_log_page.actions.user_updated'),
                                'user.deleted' => __('ui.audit_log_page.actions.user_deleted'),
                                'user.impersonated' => __('ui.audit_log_page.actions.user_impersonated'),
                                'conversation.claimed' => __('ui.audit_log_page.actions.conversation_claimed'),
                                'conversation.closed' => __('ui.audit_log_page.actions.conversation_closed'),
                                'conversation.reassigned' => __('ui.audit_log_page.actions.conversation_reassigned'),
                                'tenant.created' => __('ui.audit_log_page.actions.tenant_created'),
                                'tenant.updated' => __('ui.audit_log_page.actions.tenant_updated'),
                                'tenant.deleted' => __('ui.audit_log_page.actions.tenant_deleted'),
                                'plan.created' => __('ui.audit_log_page.actions.plan_created'),
                                'plan.updated' => __('ui.audit_log_page.actions.plan_updated'),
                            ];
                            $actionKey = $log->action;
                            $actionLabel = $actionLabels[$actionKey] ?? \Illuminate\Support\Str::of($actionKey)->replace(['.', '_'], ' ')->headline();

                            $targetLabels = [
                                'AiSettings' => __('ui.audit_log_page.targets.ai_settings'),
                                'WhatsAppInstance' => __('ui.audit_log_page.targets.whatsapp_instance'),
                                'KnowledgeEntry' => __('ui.audit_log_page.targets.knowledge_entry'),
                                'Team' => __('ui.audit_log_page.targets.team'),
                                'User' => __('ui.audit_log_page.targets.user'),
                                'Tenant' => __('ui.audit_log_page.targets.tenant'),
                                'Plan' => __('ui.audit_log_page.targets.plan'),
                                'Conversation' => __('ui.audit_log_page.targets.conversation'),
                            ];
                        @endphp
                        <tr x-data="{ expanded: false }">
                            <td style="white-space:nowrap;font-size:.8125rem">
                                <span title="{{ $log->created_at->format('Y-m-d H:i:s') }}">
                                    {{ $log->created_at->format('M j, g:i a') }}
                                </span>
                            </td>
                            <td>
                                @if($log->user)
                                <div style="display:flex;align-items:center;gap:.5rem">
                                    <img src="{{ $log->user->avatar_url }}" alt="{{ $log->user->name }}"
                                         style="width:1.5rem;height:1.5rem;border-radius:50%;object-fit:cover">
                                    <span style="font-size:.875rem">{{ $log->user->name }}</span>
                                </div>
                                @else
                                <span style="font-size:.8125rem;color:var(--text-muted)">{{ __('ui.audit_log_page.system') }}</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $actionColors = [
                                        'created' => 'badge-green', 'updated' => 'badge-blue', 'deleted' => 'badge-red',
                                        'login' => 'badge-gray', 'impersonate' => 'badge-orange', 'claimed' => 'badge-blue',
                                        'closed' => 'badge-gray', 'reassigned' => 'badge-purple',
                                    ];
                                    $actionColor = collect($actionColors)->first(fn($v, $k) => str_contains($log->action, $k)) ?? 'badge-gray';
                                @endphp
                                <span class="badge {{ $actionColor }}">{{ $actionLabel }}</span>
                            </td>
                            <td style="font-size:.8125rem">
                                @if($log->target_type && $log->target_id)
                                    <span style="color:var(--text-secondary)">{{ $targetLabels[class_basename($log->target_type)] ?? class_basename($log->target_type) }}</span>
                                    <span style="color:var(--text-muted)"> #{{ $log->target_id }}</span>
                                @else
                                    <span style="color:var(--text-muted)">—</span>
                                @endif
                            </td>
                            <td style="font-size:.8125rem;color:var(--text-muted);font-family:monospace">{{ $log->ip }}</td>
                            <td>
                                @if($log->payload)
                                <button @click="expanded = !expanded" class="action-btn" :style="expanded ? 'color:var(--brand)' : ''">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" :style="expanded ? 'transform:rotate(180deg)' : ''"><polyline points="6 9 12 15 18 9"/></svg>
                                </button>
                                @endif
                            </td>
                        </tr>
                        @if($log->payload)
                        <tr x-show="expanded" x-transition>
                            <td colspan="6" style="background:var(--page-bg);padding:.75rem 1.25rem">
                                <pre style="font-size:.75rem;color:var(--text-secondary);font-family:monospace;overflow-x:auto;margin:0;white-space:pre-wrap">{{ json_encode($log->payload, JSON_PRETTY_PRINT) }}</pre>
                            </td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($logs->hasPages())
            <div style="padding:1rem 1.25rem;border-top:1px solid var(--card-border)">
                {{ $logs->appends(request()->all())->links('admin.partials.pagination') }}
            </div>
            @endif
        @endif
    </div>

@endsection
