@extends('layouts.admin')

@section('title', __('ui.dashboard_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.dashboard_page.breadcrumb') }}</span>
@endsection

@section('content')

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-chat-3-line"></i></div>
            <div class="stat-card-value">{{ number_format($stats['total_conversations']) }}</div>
            <div class="stat-card-label">{{ __('ui.dashboard_page.total_conversations') }}</div>
            <span class="stat-card-trend up">
                <i class="ri-arrow-up-s-line"></i> {{ $stats['conversations_today'] }} {{ __('ui.dashboard_page.today') }}
            </span>
        </div>

        <div class="stat-card orange">
            <div class="stat-card-icon"><i class="ri-time-line"></i></div>
            <div class="stat-card-value">{{ number_format($stats['pool_count']) }}</div>
            <div class="stat-card-label">{{ __('ui.dashboard_page.waiting_in_pool') }}</div>
            @if($stats['pool_count'] > 10)
                <span class="stat-card-trend down"><i class="ri-arrow-down-s-line"></i> {{ __('ui.dashboard_page.high_volume') }}</span>
            @else
                <span class="stat-card-trend up"><i class="ri-arrow-up-s-line"></i> {{ __('ui.dashboard_page.normal_load') }}</span>
            @endif
        </div>

        <div class="stat-card blue">
            <div class="stat-card-icon"><i class="ri-team-line"></i></div>
            <div class="stat-card-value">{{ number_format($stats['agents_online']) }}</div>
            <div class="stat-card-label">{{ __('ui.dashboard_page.agents_online') }}</div>
            <span class="stat-card-trend neutral">{{ $stats['total_agents'] }} {{ __('ui.dashboard_page.total') }}</span>
        </div>

        <div class="stat-card purple">
            <div class="stat-card-icon"><i class="ri-message-2-line"></i></div>
            <div class="stat-card-value">{{ number_format($stats['messages_today']) }}</div>
            <div class="stat-card-label">{{ __('ui.dashboard_page.messages_today') }}</div>
            <span class="stat-card-trend up"><i class="ri-arrow-up-s-line"></i> +{{ $stats['messages_growth'] }}% {{ __('ui.dashboard_page.vs_yesterday') }}</span>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 340px;gap:1.5rem;align-items:start">
        <div style="display:flex;flex-direction:column;gap:1.5rem">
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">{{ __('ui.dashboard_page.active_conversations') }}</div>
                        <div style="font-size:13px;color:var(--text-secondary);margin-top:2px">{{ __('ui.dashboard_page.active_conversations_desc') }}</div>
                    </div>
                    <a href="{{ route('admin.conversations.index') }}" class="btn btn-outline btn-sm">{{ __('ui.dashboard_page.view_all') }}</a>
                </div>

                @if($activeConversations->isEmpty())
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="ri-chat-check-line"></i></div>
                        <h4>{{ __('ui.dashboard_page.all_caught_up') }}</h4>
                        <p>{{ __('ui.dashboard_page.no_active_conversations') }}</p>
                    </div>
                @else
                    <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>{{ __('ui.dashboard_page.contact') }}</th>
                                    <th>{{ __('ui.dashboard_page.instance') }}</th>
                                    <th>{{ __('ui.dashboard_page.status') }}</th>
                                    <th>{{ __('ui.dashboard_page.agent') }}</th>
                                    <th>{{ __('ui.dashboard_page.last_message') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($activeConversations as $conv)
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:.75rem">
                                            <div class="avatar-sm">{{ strtoupper(substr($conv->customer->displayNameOrPhone, 0, 2)) }}</div>
                                            <div>
                                                <div style="font-weight:500;font-size:.875rem">{{ $conv->customer->displayNameOrPhone }}</div>
                                                <div style="color:var(--text-muted);font-size:.75rem">{{ $conv->customer->phone_e164 }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="font-size:.8125rem;color:var(--text-secondary)">{{ $conv->instance->name }}</td>
                                    <td>
                                        @if($conv->state === 'pool')
                                            <span class="badge badge-orange">{{ __('ui.dashboard_page.pool') }}</span>
                                        @else
                                            <span class="badge badge-blue">{{ __('ui.dashboard_page.claimed') }}</span>
                                        @endif
                                        @if($conv->unread_count > 0)
                                            <span class="badge badge-red" style="margin-left:.25rem">{{ $conv->unread_count }}</span>
                                        @endif
                                    </td>
                                    <td style="font-size:.8125rem">{{ $conv->ownerAgent?->name ?? '—' }}</td>
                                    <td style="font-size:.8125rem;color:var(--text-muted)">{{ $conv->last_message_at?->diffForHumans() ?? '—' }}</td>
                                    <td>
                                        <a href="{{ route('admin.conversations.show', $conv) }}" class="action-btn">
                                            <i class="ri-arrow-right-line"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            @if($aiSettings)
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">{{ __('ui.dashboard_page.ai_usage') }}</div>
                        <div style="font-size:13px;color:var(--text-secondary);margin-top:2px">{{ __('ui.dashboard_page.ai_usage_desc') }}</div>
                    </div>
                    <span class="badge {{ $aiSettings->mode === 'off' ? 'badge-gray' : 'badge-green' }}">
                        {{ ucfirst($aiSettings->mode) }}
                    </span>
                </div>
                <div style="padding:0 1.5rem 1.5rem">
                    <div style="display:flex;justify-content:space-between;margin-bottom:.5rem;font-size:.875rem">
                        <span style="color:var(--text-secondary)">{{ number_format($aiSettings->tokens_used_this_period) }} {{ __('ui.dashboard_page.tokens_used') }}</span>
                        <span style="font-weight:500">{{ $aiSettings->quotaPercentage() }}%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill {{ $aiSettings->quotaPercentage() > 90 ? 'danger' : ($aiSettings->quotaPercentage() > 70 ? 'warning' : '') }}"
                             style="width:{{ $aiSettings->quotaPercentage() }}%"></div>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-top:.5rem;font-size:.75rem;color:var(--text-muted)">
                        <span>{{ __('ui.dashboard_page.resets') }} {{ $aiSettings->quota_reset_at?->format('M j') }}</span>
                        <span>{{ __('ui.dashboard_page.quota') }}: {{ number_format($aiSettings->monthly_token_quota) }}</span>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <div style="display:flex;flex-direction:column;gap:1.5rem">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">{{ __('ui.dashboard_page.whatsapp_instances') }}</div>
                    <a href="{{ route('admin.instances.index') }}" class="btn btn-ghost btn-sm">{{ __('ui.dashboard_page.manage') }}</a>
                </div>
                <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:.75rem">
                    @forelse($instances as $instance)
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem;background:var(--page-bg);border-radius:.5rem">
                        <div style="display:flex;align-items:center;gap:.625rem">
                            <span class="status-dot {{ $instance->statusColor }}"></span>
                            <div>
                                <div style="font-size:.875rem;font-weight:500">{{ $instance->name }}</div>
                                <div style="font-size:.75rem;color:var(--text-muted)">{{ $instance->phone_number ?? __('ui.dashboard_page.not_connected') }}</div>
                            </div>
                        </div>
                        <span class="badge {{ $instance->statusColor === 'green' ? 'badge-green' : ($instance->statusColor === 'yellow' ? 'badge-orange' : 'badge-red') }}">
                            {{ ucfirst($instance->status) }}
                        </span>
                    </div>
                    @empty
                    <div style="text-align:center;padding:1rem;color:var(--text-muted);font-size:.875rem">{{ __('ui.dashboard_page.no_instances_configured') }}</div>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">{{ __('ui.dashboard_page.team_load') }}</div>
                </div>
                <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:.875rem">
                    @forelse($teamLoad as $team)
                    <div>
                        <div style="display:flex;justify-content:space-between;font-size:.8125rem;margin-bottom:.375rem">
                            <span style="font-weight:500">{{ $team->name }}</span>
                            <span style="color:var(--text-muted)">{{ $team->active_count }} {{ __('ui.dashboard_page.active') }}</span>
                        </div>
                        <div class="progress-bar" style="height:6px">
                            <div class="progress-fill" style="width:{{ min(100, ($team->active_count / max(1, $team->capacity)) * 100) }}%"></div>
                        </div>
                    </div>
                    @empty
                    <div style="text-align:center;padding:.5rem;color:var(--text-muted);font-size:.875rem">{{ __('ui.dashboard_page.no_teams') }}</div>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">{{ __('ui.dashboard_page.recent_activity') }}</div>
                </div>
                <div style="padding:0 1.5rem 1.5rem">
                    @forelse($recentEvents as $event)
                    <div style="display:flex;gap:.75rem;padding:.625rem 0;{{ $loop->last ? '' : 'border-bottom:1px solid var(--card-border)' }}">
                        <div style="width:2rem;height:2rem;border-radius:50%;background:var(--page-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--text-muted)">
                            @if($event->type === 'claimed')
                                <i class="ri-check-line" style="font-size:13px"></i>
                            @elseif($event->type === 'closed')
                                <i class="ri-close-line" style="font-size:13px"></i>
                            @else
                                <i class="ri-information-line" style="font-size:13px"></i>
                            @endif
                        </div>
                        <div style="flex:1;min-width:0">
                            <div style="font-size:.8125rem">
                                <span style="font-weight:500">{{ $event->actor?->name ?? __('ui.dashboard_page.system') }}</span>
                                <span style="color:var(--text-secondary)"> {{ $event->type === 'claimed' ? __('ui.dashboard_page.claimed_type') : ($event->type === 'closed' ? __('ui.dashboard_page.closed_type') : str_replace('_', ' ', $event->type)) }}</span>
                            </div>
                            <div style="font-size:.75rem;color:var(--text-muted)">{{ $event->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                    @empty
                    <div style="text-align:center;padding:1rem;color:var(--text-muted);font-size:.875rem">{{ __('ui.dashboard_page.no_recent_activity') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

<style>
.avatar-sm {
    width:2rem;height:2rem;border-radius:50%;
    background:linear-gradient(135deg,var(--brand),#059669);
    color:#fff;font-size:.6875rem;font-weight:600;
    display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
</style>
@endsection
