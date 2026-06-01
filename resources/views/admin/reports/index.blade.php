@extends('layouts.admin')

@section('title', __('ui.reports_page.title'))

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
@endpush

@section('content')
<div x-data="reportsPage()" x-init="init()" class="page-root">

    {{-- Page header --}}
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">{{ __('ui.reports_page.title') }}</h1>
            <p class="page-subtitle">{{ __('ui.reports_page.subtitle') }}</p>
        </div>
        <div class="page-header-actions">
            <div class="btn-group" role="group">
                <template x-for="p in [7, 30, 90]" :key="p">
                    <button type="button"
                            :class="period === p ? 'btn btn-primary btn-sm' : 'btn btn-outline-secondary btn-sm'"
                            @click="period = p; load()">
                        <span x-text="p + ' {{ __('ui.reports_page.days') }}'"></span>
                    </button>
                </template>
            </div>
        </div>
    </div>

    {{-- Loading state --}}
    <div x-show="loading" class="d-flex justify-content-center align-items-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div x-show="!loading">

        {{-- KPI cards --}}
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); margin-bottom: 24px;">
            <div class="stat-card">
                <div class="stat-icon stat-icon--blue"><i class="ri-message-3-line"></i></div>
                <div class="stat-value" x-text="kpi.total_conversations ?? '—'"></div>
                <div class="stat-label">{{ __('ui.reports_page.kpi_total') }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon--green"><i class="ri-checkbox-circle-line"></i></div>
                <div class="stat-value" x-text="(kpi.resolution_rate ?? '—') + (kpi.resolution_rate != null ? '%' : '')"></div>
                <div class="stat-label">{{ __('ui.reports_page.kpi_resolution') }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon--yellow"><i class="ri-timer-line"></i></div>
                <div class="stat-value" x-text="kpi.avg_first_response_min != null ? kpi.avg_first_response_min + ' mn' : '—'"></div>
                <div class="stat-label">{{ __('ui.reports_page.kpi_avg_response') }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon--purple"><i class="ri-time-line"></i></div>
                <div class="stat-value" x-text="kpi.avg_resolution_min != null ? kpi.avg_resolution_min + ' mn' : '—'"></div>
                <div class="stat-label">{{ __('ui.reports_page.kpi_avg_resolution') }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon--teal"><i class="ri-arrow-down-circle-line"></i></div>
                <div class="stat-value" x-text="kpi.inbound_messages ?? '—'"></div>
                <div class="stat-label">{{ __('ui.reports_page.kpi_inbound') }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon--orange"><i class="ri-arrow-up-circle-line"></i></div>
                <div class="stat-value" x-text="kpi.outbound_messages ?? '—'"></div>
                <div class="stat-label">{{ __('ui.reports_page.kpi_outbound') }}</div>
            </div>
        </div>

        {{-- Charts row 1: Daily volume + State breakdown --}}
        <div class="row g-4 mb-4">
            <div class="col-xl-8 col-12">
                <div class="card h-100">
                    <div class="card-header">
                        <span class="fw-semibold">{{ __('ui.reports_page.chart_daily_volume') }}</span>
                    </div>
                    <div class="card-body" style="position:relative; min-height:260px;">
                        <canvas id="chartDailyVolume"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-12">
                <div class="card h-100">
                    <div class="card-header">
                        <span class="fw-semibold">{{ __('ui.reports_page.chart_state') }}</span>
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center" style="min-height:260px;">
                        <canvas id="chartState" style="max-height:220px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- Charts row 2: Hourly heatmap + AI vs Agent --}}
        <div class="row g-4 mb-4">
            <div class="col-xl-8 col-12">
                <div class="card h-100">
                    <div class="card-header">
                        <span class="fw-semibold">{{ __('ui.reports_page.chart_hourly') }}</span>
                    </div>
                    <div class="card-body" style="position:relative; min-height:220px;">
                        <canvas id="chartHourly"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-12">
                <div class="card h-100">
                    <div class="card-header">
                        <span class="fw-semibold">{{ __('ui.reports_page.chart_ai_agent') }}</span>
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center" style="min-height:220px;">
                        <canvas id="chartAiAgent" style="max-height:200px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- Leaderboard row --}}
        <div class="row g-4">

            {{-- Team leaderboard --}}
            <div class="col-xl-6 col-12">
                <div class="card h-100">
                    <div class="card-header">
                        <span class="fw-semibold">{{ __('ui.reports_page.team_leaderboard') }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-header">
                                    <tr>
                                        <th>{{ __('ui.reports_page.col_team') }}</th>
                                        <th class="text-center">{{ __('ui.reports_page.col_total') }}</th>
                                        <th class="text-center">{{ __('ui.reports_page.col_closed') }}</th>
                                        <th>{{ __('ui.reports_page.col_resolution') }}</th>
                                        <th class="text-center">{{ __('ui.reports_page.col_avg_response') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-if="team_leaderboard.length === 0">
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">{{ __('ui.reports_page.no_data') }}</td>
                                        </tr>
                                    </template>
                                    <template x-for="(row, idx) in team_leaderboard" :key="row.name">
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="badge-rank" :class="'rank-' + (idx + 1)" x-text="idx + 1"></span>
                                                    <span class="fw-medium" x-text="row.name"></span>
                                                </div>
                                            </td>
                                            <td class="text-center" x-text="row.total"></td>
                                            <td class="text-center" x-text="row.closed"></td>
                                            <td style="min-width:120px;">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress flex-grow-1" style="height:6px;">
                                                        <div class="progress-bar bg-success" :style="'width:' + row.resolution_rate + '%'"></div>
                                                    </div>
                                                    <span class="text-muted small" x-text="row.resolution_rate + '%'"></span>
                                                </div>
                                            </td>
                                            <td class="text-center">
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

            {{-- Agent leaderboard --}}
            <div class="col-xl-6 col-12">
                <div class="card h-100">
                    <div class="card-header">
                        <span class="fw-semibold">{{ __('ui.reports_page.agent_leaderboard') }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-header">
                                    <tr>
                                        <th>{{ __('ui.reports_page.col_agent') }}</th>
                                        <th class="text-center">{{ __('ui.reports_page.col_claimed') }}</th>
                                        <th class="text-center">{{ __('ui.reports_page.col_closed') }}</th>
                                        <th class="text-center">{{ __('ui.reports_page.col_messages') }}</th>
                                        <th class="text-center">{{ __('ui.reports_page.col_avg_response') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-if="agent_leaderboard.length === 0">
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">{{ __('ui.reports_page.no_data') }}</td>
                                        </tr>
                                    </template>
                                    <template x-for="(row, idx) in agent_leaderboard" :key="row.name">
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="badge-rank" :class="'rank-' + (idx + 1)" x-text="idx + 1"></span>
                                                    <span class="fw-medium" x-text="row.name"></span>
                                                </div>
                                            </td>
                                            <td class="text-center" x-text="row.claimed"></td>
                                            <td class="text-center" x-text="row.closed"></td>
                                            <td class="text-center" x-text="row.messages_sent"></td>
                                            <td class="text-center">
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
    </div>
</div>

<style>
.badge-rank {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    font-size: 11px;
    font-weight: 700;
    background: var(--color-border, #e5e7eb);
    color: var(--color-text-muted, #6b7280);
    flex-shrink: 0;
}
.badge-rank.rank-1 { background: #ffd700; color: #7a5800; }
.badge-rank.rank-2 { background: #c0c0c0; color: #555; }
.badge-rank.rank-3 { background: #cd7f32; color: #fff; }
.table-header th {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--color-text-muted, #6b7280);
    padding: 10px 16px;
    white-space: nowrap;
}
</style>

@push('scripts')
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

        charts: {},

        init() {
            this.load();
        },

        load() {
            this.loading = true;

            fetch(`{{ route($panelPrefix . '.reports.data') }}?period=${this.period}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(data => {
                this.kpi              = data.kpi;
                this.team_leaderboard = data.team_leaderboard;
                this.agent_leaderboard= data.agent_leaderboard;
                this.daily_volume     = data.daily_volume;
                this.hourly_heatmap   = data.hourly_heatmap;
                this.state_breakdown  = data.state_breakdown;
                this.ai_vs_agent      = data.ai_vs_agent;
                this.loading = false;
                this.$nextTick(() => this.renderCharts());
            })
            .catch(() => { this.loading = false; });
        },

        renderCharts() {
            this.renderDailyVolume();
            this.renderState();
            this.renderHourly();
            this.renderAiAgent();
        },

        destroyChart(key) {
            if (this.charts[key]) {
                this.charts[key].destroy();
                delete this.charts[key];
            }
        },

        renderDailyVolume() {
            this.destroyChart('daily');
            const ctx = document.getElementById('chartDailyVolume');
            if (!ctx) return;
            const labels = this.daily_volume.map(r => r.day);
            const data   = this.daily_volume.map(r => r.count);
            this.charts['daily'] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: '{{ __('ui.reports_page.chart_daily_volume') }}',
                        data,
                        fill: true,
                        tension: 0.4,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59,130,246,.12)',
                        pointRadius: labels.length > 30 ? 0 : 3,
                        pointHoverRadius: 5,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } },
                        x: { ticks: { maxTicksLimit: 10, maxRotation: 0 } }
                    }
                }
            });
        },

        renderState() {
            this.destroyChart('state');
            const ctx = document.getElementById('chartState');
            if (!ctx) return;
            const sd = this.state_breakdown;
            this.charts['state'] = new Chart(ctx, {
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
                        borderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    cutout: '65%',
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 14 } }
                    }
                }
            });
        },

        renderHourly() {
            this.destroyChart('hourly');
            const ctx = document.getElementById('chartHourly');
            if (!ctx) return;
            const labels = this.hourly_heatmap.map(r => r.hour);
            const data   = this.hourly_heatmap.map(r => r.count);
            this.charts['hourly'] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: '{{ __('ui.reports_page.kpi_inbound') }}',
                        data,
                        backgroundColor: 'rgba(99,102,241,.75)',
                        borderRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } },
                        x: { ticks: { maxRotation: 0, font: { size: 10 } } }
                    }
                }
            });
        },

        renderAiAgent() {
            this.destroyChart('ai');
            const ctx = document.getElementById('chartAiAgent');
            if (!ctx) return;
            const av = this.ai_vs_agent;
            this.charts['ai'] = new Chart(ctx, {
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
                        borderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    cutout: '65%',
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 14 } }
                    }
                }
            });
        },
    }
}
</script>
@endpush
@endsection
