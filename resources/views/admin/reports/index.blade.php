@extends('layouts.admin')

@section('title', __('ui.reports_page.title'))

@php $panelPrefix = auth()->user()->routeNamePrefix(); @endphp

@section('content')
<div x-data="reportsPage()" x-init="init()" x-cloak>

    {{-- Page header --}}
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.reports_page.title') }}</div>
            <div class="page-subtitle">{{ __('ui.reports_page.subtitle') }}</div>
        </div>
        <div class="page-header-actions">
            <div style="display:flex;gap:.375rem;">
                @foreach([7, 30, 90] as $p)
                <button type="button"
                        :class="period === {{ $p }} ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm'"
                        @click="period = {{ $p }}; load()">
                    {{ $p }} {{ __('ui.reports_page.days') }}
                </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Loading --}}
    <div x-show="loading" class="spinner-wrap" style="min-height:300px">
        <div>
            <div class="spinner" style="margin:0 auto 1rem"></div>
        </div>
    </div>

    <div x-show="!loading">

        {{-- KPI stat cards --}}
        <div class="stats-grid" style="margin-bottom:1.5rem">
            <div class="stat-card">
                <div class="stat-card-icon"><i class="ri-message-3-line"></i></div>
                <div class="stat-card-value" x-text="kpi.total_conversations ?? '—'"></div>
                <div class="stat-card-label">{{ __('ui.reports_page.kpi_total') }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon"><i class="ri-checkbox-circle-line"></i></div>
                <div class="stat-card-value" x-text="kpi.resolution_rate != null ? kpi.resolution_rate + '%' : '—'"></div>
                <div class="stat-card-label">{{ __('ui.reports_page.kpi_resolution') }}</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-card-icon"><i class="ri-timer-line"></i></div>
                <div class="stat-card-value" x-text="kpi.avg_first_response_min != null ? kpi.avg_first_response_min + ' mn' : '—'"></div>
                <div class="stat-card-label">{{ __('ui.reports_page.kpi_avg_response') }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon"><i class="ri-time-line"></i></div>
                <div class="stat-card-value" x-text="kpi.avg_resolution_min != null ? kpi.avg_resolution_min + ' mn' : '—'"></div>
                <div class="stat-card-label">{{ __('ui.reports_page.kpi_avg_resolution') }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon"><i class="ri-arrow-down-circle-line"></i></div>
                <div class="stat-card-value" x-text="kpi.inbound_messages ?? '—'"></div>
                <div class="stat-card-label">{{ __('ui.reports_page.kpi_inbound') }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon"><i class="ri-arrow-up-circle-line"></i></div>
                <div class="stat-card-value" x-text="kpi.outbound_messages ?? '—'"></div>
                <div class="stat-card-label">{{ __('ui.reports_page.kpi_outbound') }}</div>
            </div>
        </div>

        {{-- Charts row 1 --}}
        <div style="display:grid;grid-template-columns:1fr 340px;gap:1rem;margin-bottom:1rem;">
            <div class="card" style="padding:0;">
                <div style="padding:.875rem 1.125rem .625rem;border-bottom:1px solid var(--card-border);font-weight:600;font-size:.875rem;">
                    {{ __('ui.reports_page.chart_daily_volume') }}
                </div>
                <div style="padding:1rem;height:260px;position:relative;">
                    <canvas id="chartDailyVolume" style="width:100%;height:100%;"></canvas>
                </div>
            </div>
            <div class="card" style="padding:0;">
                <div style="padding:.875rem 1.125rem .625rem;border-bottom:1px solid var(--card-border);font-weight:600;font-size:.875rem;">
                    {{ __('ui.reports_page.chart_state') }}
                </div>
                <div style="padding:1rem;height:260px;display:flex;align-items:center;justify-content:center;position:relative;">
                    <canvas id="chartState" style="max-height:230px;"></canvas>
                </div>
            </div>
        </div>

        {{-- Charts row 2 --}}
        <div style="display:grid;grid-template-columns:1fr 340px;gap:1rem;margin-bottom:1.5rem;">
            <div class="card" style="padding:0;">
                <div style="padding:.875rem 1.125rem .625rem;border-bottom:1px solid var(--card-border);font-weight:600;font-size:.875rem;">
                    {{ __('ui.reports_page.chart_hourly') }}
                </div>
                <div style="padding:1rem;height:220px;position:relative;">
                    <canvas id="chartHourly" style="width:100%;height:100%;"></canvas>
                </div>
            </div>
            <div class="card" style="padding:0;">
                <div style="padding:.875rem 1.125rem .625rem;border-bottom:1px solid var(--card-border);font-weight:600;font-size:.875rem;">
                    {{ __('ui.reports_page.chart_ai_agent') }}
                </div>
                <div style="padding:1rem;height:220px;display:flex;align-items:center;justify-content:center;position:relative;">
                    <canvas id="chartAiAgent" style="max-height:190px;"></canvas>
                </div>
            </div>
        </div>

        {{-- Leaderboard tables --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">

            {{-- Team leaderboard --}}
            <div class="card" style="padding:0;">
                <div style="padding:.875rem 1.125rem;border-bottom:1px solid var(--card-border);display:flex;align-items:center;justify-content:space-between;">
                    <span style="font-weight:600;font-size:.875rem;">{{ __('ui.reports_page.team_leaderboard') }}</span>
                    <span class="badge badge-gray" style="font-size:.72rem;" x-text="team_leaderboard.length + ' {{ __('ui.reports_page.teams_label') }}'"></span>
                </div>
                <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:2rem;">#</th>
                                <th>{{ __('ui.reports_page.col_team') }}</th>
                                <th style="text-align:center;">{{ __('ui.reports_page.col_total') }}</th>
                                <th style="text-align:center;">{{ __('ui.reports_page.col_closed') }}</th>
                                <th style="min-width:120px;">{{ __('ui.reports_page.col_resolution') }}</th>
                                <th style="text-align:center;">{{ __('ui.reports_page.col_avg_response') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-if="team_leaderboard.length === 0">
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state" style="padding:2.5rem 1rem;">
                                            <div class="empty-state-icon"><i class="ri-bar-chart-2-line"></i></div>
                                            <p>{{ __('ui.reports_page.no_data') }}</p>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <template x-for="(row, idx) in team_leaderboard" :key="row.name">
                                <tr>
                                    <td>
                                        <span class="rank-badge" :class="rankClass(idx)" x-text="idx + 1"></span>
                                    </td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:.625rem;">
                                            <div style="width:2rem;height:2rem;border-radius:.5rem;background:linear-gradient(135deg,rgba(16,185,129,.15),rgba(5,150,105,.25));display:flex;align-items:center;justify-content:center;color:var(--brand);flex-shrink:0;">
                                                <i class="ri-team-line" style="font-size:.875rem;"></i>
                                            </div>
                                            <span style="font-weight:600;" x-text="row.name"></span>
                                        </div>
                                    </td>
                                    <td style="text-align:center;font-weight:600;" x-text="row.total"></td>
                                    <td style="text-align:center;">
                                        <span class="badge badge-green"><i class="ri-checkbox-circle-line"></i> <span x-text="row.closed"></span></span>
                                    </td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:.5rem;">
                                            <div style="flex:1;height:6px;border-radius:3px;background:var(--color-border,#e5e7eb);overflow:hidden;">
                                                <div style="height:100%;border-radius:3px;background:#10b981;transition:width .4s;"
                                                     :style="'width:' + row.resolution_rate + '%'"></div>
                                            </div>
                                            <span style="font-size:.75rem;color:var(--text-muted);white-space:nowrap;" x-text="row.resolution_rate + '%'"></span>
                                        </div>
                                    </td>
                                    <td style="text-align:center;color:var(--text-muted);font-size:.85rem;">
                                        <span x-text="row.avg_response_min != null ? row.avg_response_min + ' mn' : '—'"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Agent leaderboard --}}
            <div class="card" style="padding:0;">
                <div style="padding:.875rem 1.125rem;border-bottom:1px solid var(--card-border);display:flex;align-items:center;justify-content:space-between;">
                    <span style="font-weight:600;font-size:.875rem;">{{ __('ui.reports_page.agent_leaderboard') }}</span>
                    <span class="badge badge-gray" style="font-size:.72rem;" x-text="agent_leaderboard.length + ' {{ __('ui.reports_page.agents_label') }}'"></span>
                </div>
                <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:2rem;">#</th>
                                <th>{{ __('ui.reports_page.col_agent') }}</th>
                                <th style="text-align:center;">{{ __('ui.reports_page.col_claimed') }}</th>
                                <th style="text-align:center;">{{ __('ui.reports_page.col_closed') }}</th>
                                <th style="text-align:center;">{{ __('ui.reports_page.col_messages') }}</th>
                                <th style="text-align:center;">{{ __('ui.reports_page.col_avg_response') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-if="agent_leaderboard.length === 0">
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state" style="padding:2.5rem 1rem;">
                                            <div class="empty-state-icon"><i class="ri-user-line"></i></div>
                                            <p>{{ __('ui.reports_page.no_data') }}</p>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <template x-for="(row, idx) in agent_leaderboard" :key="row.name">
                                <tr>
                                    <td>
                                        <span class="rank-badge" :class="rankClass(idx)" x-text="idx + 1"></span>
                                    </td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:.625rem;">
                                            <div style="width:2rem;height:2rem;border-radius:50%;background:linear-gradient(135deg,rgba(99,102,241,.15),rgba(79,70,229,.25));display:flex;align-items:center;justify-content:center;color:#6366f1;flex-shrink:0;font-size:.75rem;font-weight:700;"
                                                 x-text="row.name.charAt(0).toUpperCase()"></div>
                                            <span style="font-weight:600;" x-text="row.name"></span>
                                        </div>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="badge badge-blue"><i class="ri-hand-heart-line"></i> <span x-text="row.claimed"></span></span>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="badge badge-green"><i class="ri-checkbox-circle-line"></i> <span x-text="row.closed"></span></span>
                                    </td>
                                    <td style="text-align:center;font-weight:600;" x-text="row.messages_sent"></td>
                                    <td style="text-align:center;color:var(--text-muted);font-size:.85rem;">
                                        <span x-text="row.avg_response_min != null ? row.avg_response_min + ' mn' : '—'"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

@push('styles')
<style>
.rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 50%;
    font-size: .7rem;
    font-weight: 700;
    background: var(--color-border, #e5e7eb);
    color: var(--text-muted, #6b7280);
}
.rank-badge.rank-1 { background: #fbbf24; color: #78350f; }
.rank-badge.rank-2 { background: #9ca3af; color: #1f2937; }
.rank-badge.rank-3 { background: #cd7f32; color: #fff; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
function reportsPage() {
    return {
        period: 30,
        loading: true,
        kpi: {},
        team_leaderboard: [],
        agent_leaderboard: [],
        daily_volume: [],
        hourly_heatmap: [],
        state_breakdown: {},
        ai_vs_agent: {},
        _charts: {},

        init() {
            this.load();
        },

        rankClass(idx) {
            if (idx === 0) return 'rank-1';
            if (idx === 1) return 'rank-2';
            if (idx === 2) return 'rank-3';
            return '';
        },

        load() {
            this.loading = true;
            fetch(`{{ route($panelPrefix . '.reports.data') }}?period=${this.period}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(data => {
                this.kpi               = data.kpi;
                this.team_leaderboard  = data.team_leaderboard;
                this.agent_leaderboard = data.agent_leaderboard;
                this.daily_volume      = data.daily_volume;
                this.hourly_heatmap    = data.hourly_heatmap;
                this.state_breakdown   = data.state_breakdown;
                this.ai_vs_agent       = data.ai_vs_agent;
                this.loading = false;
                // Wait for DOM repaint before measuring canvas dimensions
                setTimeout(() => this._renderCharts(), 80);
            })
            .catch(() => { this.loading = false; });
        },

        _destroy(key) {
            if (this._charts[key]) {
                this._charts[key].destroy();
                delete this._charts[key];
            }
        },

        _renderCharts() {
            this._renderDaily();
            this._renderState();
            this._renderHourly();
            this._renderAi();
        },

        _renderDaily() {
            this._destroy('daily');
            const ctx = document.getElementById('chartDailyVolume');
            if (!ctx) return;
            const labels = this.daily_volume.map(r => r.day);
            const data   = this.daily_volume.map(r => r.count);
            this._charts['daily'] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: '{{ __('ui.reports_page.chart_daily_volume') }}',
                        data,
                        fill: true,
                        tension: 0.4,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59,130,246,.1)',
                        pointRadius: labels.length > 30 ? 0 : 3,
                        pointHoverRadius: 5,
                        borderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } },
                        x: { ticks: { maxTicksLimit: 10, maxRotation: 0 } }
                    }
                }
            });
        },

        _renderState() {
            this._destroy('state');
            const ctx = document.getElementById('chartState');
            if (!ctx) return;
            const sd = this.state_breakdown;
            this._charts['state'] = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: [
                        '{{ __('ui.reports_page.state_pool') }}',
                        '{{ __('ui.reports_page.state_claimed') }}',
                        '{{ __('ui.reports_page.state_closed') }}'
                    ],
                    datasets: [{
                        data: [sd.pool ?? 0, sd.claimed ?? 0, sd.closed ?? 0],
                        backgroundColor: ['#f59e0b', '#3b82f6', '#10b981'],
                        borderWidth: 0,
                        hoverOffset: 4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '68%',
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 10, padding: 12, font: { size: 12 } } }
                    }
                }
            });
        },

        _renderHourly() {
            this._destroy('hourly');
            const ctx = document.getElementById('chartHourly');
            if (!ctx) return;
            const labels = this.hourly_heatmap.map(r => r.hour);
            const data   = this.hourly_heatmap.map(r => r.count);
            this._charts['hourly'] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: '{{ __('ui.reports_page.kpi_inbound') }}',
                        data,
                        backgroundColor: 'rgba(99,102,241,.7)',
                        borderRadius: 3,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } },
                        x: { ticks: { maxRotation: 0, font: { size: 10 } } }
                    }
                }
            });
        },

        _renderAi() {
            this._destroy('ai');
            const ctx = document.getElementById('chartAiAgent');
            if (!ctx) return;
            const av = this.ai_vs_agent;
            this._charts['ai'] = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: [
                        '{{ __('ui.reports_page.author_agent') }}',
                        '{{ __('ui.reports_page.author_ai') }}',
                        '{{ __('ui.reports_page.author_system') }}'
                    ],
                    datasets: [{
                        data: [av.agent ?? 0, av.ai ?? 0, av.system ?? 0],
                        backgroundColor: ['#6366f1', '#10b981', '#94a3b8'],
                        borderWidth: 0,
                        hoverOffset: 4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '68%',
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 10, padding: 12, font: { size: 12 } } }
                    }
                }
            });
        },
    }
}
</script>
@endpush
@endsection
