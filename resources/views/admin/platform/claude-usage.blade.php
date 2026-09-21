@extends('layouts.admin')

@section('title', __('ui.claude_usage.title'))

@section('breadcrumb')
    <span>{{ __('ui.claude_usage.breadcrumb_root') }}</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ __('ui.claude_usage.title') }}</span>
@endsection

@php
    $fmtUsd    = fn ($n) => '$' . number_format((float) $n, 2);
    $fmtUsd4   = fn ($n) => '$' . number_format((float) $n, 4);
    $fmtTokens = function ($n) {
        $n = (int) $n;
        if ($n >= 1_000_000) return number_format($n / 1_000_000, 2) . 'M';
        if ($n >= 1_000)     return number_format($n / 1_000, 1) . 'k';
        return number_format($n);
    };

    $ranges = [
        '7d'    => __('ui.claude_usage.range_7d'),
        '30d'   => __('ui.claude_usage.range_30d'),
        'month' => __('ui.claude_usage.range_month'),
        '90d'   => __('ui.claude_usage.range_90d'),
        'all'   => __('ui.claude_usage.range_all'),
    ];

    $sourceLabels = [
        'whatsapp'  => __('ui.claude_usage.source_whatsapp'),
        'webchat'   => __('ui.claude_usage.source_webchat'),
        'messenger' => __('ui.claude_usage.source_messenger'),
        'title'     => __('ui.claude_usage.source_title'),
        'ask'       => __('ui.claude_usage.source_ask'),
    ];
@endphp

@section('content')
<div>

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.claude_usage.page_title') }}</div>
            <div class="page-subtitle">{{ __('ui.claude_usage.subtitle') }}</div>
        </div>
        <div class="page-header-actions">
            {{-- Range picker: simple GET nav (no AJAX). --}}
            <form method="GET" action="{{ route('super_admin.platform.claude-usage') }}" style="display:flex;gap:.5rem;align-items:center;">
                <label style="color:var(--text-muted);font-size:.875rem;">{{ __('ui.claude_usage.range_label') }}</label>
                <select name="range" onchange="this.form.submit()" class="toolbar-select">
                    @foreach($ranges as $key => $label)
                        <option value="{{ $key }}" @selected($range === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-money-dollar-circle-line"></i></div>
            <div class="stat-card-value">{{ $fmtUsd($totals->cost ?? 0) }}</div>
            <div class="stat-card-label">{{ __('ui.claude_usage.kpi_spend') }} · {{ $rangeLabel }}</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-card-icon"><i class="ri-cpu-line"></i></div>
            <div class="stat-card-value">{{ number_format((int) ($totals->calls ?? 0)) }}</div>
            <div class="stat-card-label">{{ __('ui.claude_usage.kpi_calls') }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-download-2-line"></i></div>
            <div class="stat-card-value">{{ $fmtTokens($totals->in_tok ?? 0) }}</div>
            <div class="stat-card-label">{{ __('ui.claude_usage.kpi_input_tokens') }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="ri-upload-2-line"></i></div>
            <div class="stat-card-value">{{ $fmtTokens($totals->out_tok ?? 0) }}</div>
            <div class="stat-card-label">{{ __('ui.claude_usage.kpi_output_tokens') }}</div>
        </div>
    </div>

    {{-- Lifetime anchor + pricing note --}}
    <div class="card" style="padding:1rem 1.25rem;margin-bottom:1.25rem;display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
        <div>
            <div style="color:var(--text-muted);font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;">{{ __('ui.claude_usage.lifetime_total') }}</div>
            <div style="font-size:1.5rem;font-weight:700;">{{ $fmtUsd($lifetime->cost ?? 0) }}
                <span style="color:var(--text-muted);font-weight:400;font-size:.875rem;">
                    · {{ number_format((int) ($lifetime->calls ?? 0)) }} {{ __('ui.claude_usage.calls_word') }}
                </span>
            </div>
        </div>
        <div style="color:var(--text-muted);font-size:.75rem;text-align:end;max-width:420px;">
            <i class="ri-information-line"></i>
            {{ __('ui.claude_usage.pricing_note') }}
        </div>
    </div>

    {{-- Two-column: per-tenant table (wide) + per-model + per-source (narrow) --}}
    <div style="display:grid;grid-template-columns:1fr 360px;gap:1.25rem;align-items:start;">

        <div class="card" style="padding:0;">
            <div class="card-header">
                <div class="card-title">{{ __('ui.claude_usage.by_tenant_title') }}</div>
                <div class="card-subtitle">{{ __('ui.claude_usage.by_tenant_subtitle') }}</div>
            </div>
            <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.claude_usage.col_tenant') }}</th>
                            <th style="text-align:end;">{{ __('ui.claude_usage.col_calls') }}</th>
                            <th style="text-align:end;">{{ __('ui.claude_usage.col_input_tokens') }}</th>
                            <th style="text-align:end;">{{ __('ui.claude_usage.col_output_tokens') }}</th>
                            <th style="text-align:end;">{{ __('ui.claude_usage.col_avg_per_call') }}</th>
                            <th style="text-align:end;">{{ __('ui.claude_usage.col_spend') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($byTenant as $row)
                            @php
                                $tenant   = $tenants->get($row->tenant_id);
                                $avgCall  = $row->calls > 0 ? ((float) $row->cost / (int) $row->calls) : 0;
                            @endphp
                            <tr>
                                <td>
                                    @if($tenant)
                                        <a href="{{ route('super_admin.platform.tenants.show', $tenant->id) }}" style="font-weight:600;color:var(--text-primary);text-decoration:none;">
                                            {{ $tenant->name }}
                                        </a>
                                        <div style="color:var(--text-muted);font-size:.75rem;">{{ $tenant->slug }}</div>
                                    @else
                                        <span style="color:var(--text-muted);">#{{ $row->tenant_id }} ({{ __('ui.claude_usage.deleted_tenant') }})</span>
                                    @endif
                                </td>
                                <td style="text-align:end;">{{ number_format((int) $row->calls) }}</td>
                                <td style="text-align:end;color:var(--text-muted);">{{ $fmtTokens($row->in_tok) }}</td>
                                <td style="text-align:end;color:var(--text-muted);">{{ $fmtTokens($row->out_tok) }}</td>
                                <td style="text-align:end;color:var(--text-muted);">{{ $fmtUsd4($avgCall) }}</td>
                                <td style="text-align:end;font-weight:700;">{{ $fmtUsd($row->cost) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="empty-state" style="padding:2.5rem 1rem;">
                                    <div class="empty-state-icon"><i class="ri-cpu-line"></i></div>
                                    <h4>{{ __('ui.claude_usage.no_data_title') }}</h4>
                                    <p>{{ __('ui.claude_usage.no_data_body') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:1.25rem;">

            <div class="card" style="padding:0;">
                <div class="card-header">
                    <div class="card-title">{{ __('ui.claude_usage.by_model_title') }}</div>
                </div>
                <div style="padding:.75rem 1.25rem 1rem;">
                    @forelse($byModel as $row)
                        <div style="display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--card-border);">
                            <div>
                                <div style="font-weight:600;font-size:.875rem;">{{ $row->model }}</div>
                                <div style="color:var(--text-muted);font-size:.75rem;">
                                    {{ number_format((int) $row->calls) }} {{ __('ui.claude_usage.calls_word') }} · {{ $fmtTokens($row->in_tok + $row->out_tok) }} tok
                                </div>
                            </div>
                            <div style="font-weight:700;font-size:.875rem;">{{ $fmtUsd($row->cost) }}</div>
                        </div>
                    @empty
                        <div style="color:var(--text-muted);padding:.5rem 0;font-size:.875rem;">{{ __('ui.claude_usage.no_data_short') }}</div>
                    @endforelse
                </div>
            </div>

            <div class="card" style="padding:0;">
                <div class="card-header">
                    <div class="card-title">{{ __('ui.claude_usage.by_source_title') }}</div>
                </div>
                <div style="padding:.75rem 1.25rem 1rem;">
                    @forelse($bySource as $row)
                        <div style="display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--card-border);">
                            <div>
                                <div style="font-weight:600;font-size:.875rem;">{{ $sourceLabels[$row->source] ?? $row->source }}</div>
                                <div style="color:var(--text-muted);font-size:.75rem;">{{ number_format((int) $row->calls) }} {{ __('ui.claude_usage.calls_word') }}</div>
                            </div>
                            <div style="font-weight:700;font-size:.875rem;">{{ $fmtUsd($row->cost) }}</div>
                        </div>
                    @empty
                        <div style="color:var(--text-muted);padding:.5rem 0;font-size:.875rem;">{{ __('ui.claude_usage.no_data_short') }}</div>
                    @endforelse
                </div>
            </div>

        </div>
    </div>

    {{-- Latest 20 calls: spot-check that fresh calls are landing --}}
    <div class="card" style="padding:0;margin-top:1.25rem;">
        <div class="card-header">
            <div class="card-title">{{ __('ui.claude_usage.recent_title') }}</div>
            <div class="card-subtitle">{{ __('ui.claude_usage.recent_subtitle') }}</div>
        </div>
        <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('ui.claude_usage.col_when') }}</th>
                        <th>{{ __('ui.claude_usage.col_tenant') }}</th>
                        <th>{{ __('ui.claude_usage.col_source') }}</th>
                        <th>{{ __('ui.claude_usage.col_model') }}</th>
                        <th style="text-align:end;">{{ __('ui.claude_usage.col_input_tokens') }}</th>
                        <th style="text-align:end;">{{ __('ui.claude_usage.col_output_tokens') }}</th>
                        <th style="text-align:end;">{{ __('ui.claude_usage.col_spend') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recent as $row)
                        <tr>
                            <td style="color:var(--text-muted);font-size:.8125rem;">{{ $row->created_at?->diffForHumans() }}</td>
                            <td>
                                @if($row->tenant)
                                    <a href="{{ route('super_admin.platform.tenants.show', $row->tenant->id) }}" style="color:var(--text-primary);text-decoration:none;">{{ $row->tenant->name }}</a>
                                @else
                                    <span style="color:var(--text-muted);">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge">{{ $sourceLabels[$row->source] ?? $row->source }}</span>
                            </td>
                            <td style="color:var(--text-muted);font-family:var(--font-mono);font-size:.75rem;">{{ $row->model }}</td>
                            <td style="text-align:end;color:var(--text-muted);">{{ number_format((int) $row->input_tokens) }}</td>
                            <td style="text-align:end;color:var(--text-muted);">{{ number_format((int) $row->output_tokens) }}</td>
                            <td style="text-align:end;font-weight:600;">{{ $fmtUsd4($row->cost_usd) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="padding:2rem 1rem;text-align:center;color:var(--text-muted);">
                                {{ __('ui.claude_usage.no_data_short') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
