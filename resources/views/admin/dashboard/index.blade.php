@extends('layouts.admin')

@section('title', __('ui.dashboard'))

@section('breadcrumb')
    <span>{{ __('ui.dashboard') }}</span>
@endsection

@push('styles')
<style>
/* ════════════════════════════════════════════════════════════════
   DASHBOARD — wavadesk dashboard design, scoped under .dsh
════════════════════════════════════════════════════════════════ */
.dsh{
  --teal:#0f7e7a;--teal-l:#15b6a8;--teal-d:#0a5e5b;--teal-50:#ecf7f6;--teal-100:#d6efed;
  --txt:#0f172a;--mut:#64748b;--mut-2:#94a3b8;
  --bd:#e6ebf0;--bd-2:#cbd5e1;--soft:#f7f9fa;--soft-2:#eef2f5;
  --wa:#25a35a;--wa-50:#e9f7ee;--lc:#4f6bed;--lc-50:#eef1fe;
  --amber:#d97706;--amber-50:#fef3e2;--amber-100:#fcd9a4;
  --red:#dc2626;--red-50:#fef2f2;
  display:flex;flex-direction:column;gap:16px;max-width:1420px;margin:0 auto;
  font-size:14px;line-height:1.5;color:var(--txt);
}
.dsh button{font-family:inherit;cursor:pointer}
.dsh .btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;height:36px;padding:0 15px;border-radius:9px;font-size:13px;font-weight:600;transition:.13s;white-space:nowrap;border:none}
.dsh .btn.p{background:var(--teal);color:#fff}
.dsh .btn.p:hover{background:var(--teal-d);color:#fff}
.dsh .btn.g{background:#fff;color:var(--txt);border:1px solid var(--bd)}
.dsh .btn.g:hover{border-color:var(--bd-2);background:var(--soft);color:var(--txt)}
.dsh .btn.sm{height:31px;font-size:12.5px;padding:0 12px}

/* header row */
.dsh .dhead{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.dsh .dhead .sp{flex:1}
.dsh .sel{height:36px;border:1px solid var(--bd);border-radius:9px;padding:0 32px 0 12px;font-size:13px;font-weight:500;color:var(--txt);background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none'%3E%3Cpath d='M6 9l6 6 6-6' stroke='%2364748b' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") no-repeat right 11px center;appearance:none;cursor:pointer;outline:none;font-family:inherit}
.dsh .sel:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(15,126,122,.1)}

/* trial */
.dsh .trial{border-radius:16px;padding:18px 22px;display:flex;align-items:center;gap:20px;border:1px solid;position:relative;overflow:hidden;flex-wrap:wrap}
.dsh .trial.ok{background:linear-gradient(100deg,var(--teal-50),#fff 62%);border-color:var(--teal-100)}
.dsh .trial.warn{background:linear-gradient(100deg,var(--amber-50),#fff 62%);border-color:var(--amber-100)}
.dsh .trial.crit{background:linear-gradient(100deg,var(--red-50),#fff 62%);border-color:#fecaca}
.dsh .trial .ic{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;flex-shrink:0;color:#fff}
.dsh .trial.ok .ic{background:var(--teal)}
.dsh .trial.warn .ic{background:var(--amber)}
.dsh .trial.crit .ic{background:var(--red)}
.dsh .trial .m{flex:1;min-width:200px}
.dsh .trial .h{font-size:15.5px;font-weight:700;letter-spacing:-.01em;display:flex;align-items:center;gap:9px;flex-wrap:wrap}
.dsh .trial .pill{font-size:10.5px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;padding:3px 8px;border-radius:5px}
.dsh .trial.ok .pill{background:var(--teal);color:#fff}
.dsh .trial.warn .pill{background:var(--amber);color:#fff}
.dsh .trial.crit .pill{background:var(--red);color:#fff}
.dsh .trial .s{font-size:13px;color:var(--mut);margin-top:3px}
.dsh .trial .bar{height:6px;border-radius:99px;background:rgba(15,23,42,.09);margin-top:11px;overflow:hidden;max-width:520px}
.dsh .trial .bar i{display:block;height:100%;border-radius:99px;transition:width .5s}
.dsh .trial.ok .bar i{background:var(--teal)}
.dsh .trial.warn .bar i{background:var(--amber)}
.dsh .trial.crit .bar i{background:var(--red)}
.dsh .trial .days{text-align:center;flex-shrink:0;padding:0 6px}
.dsh .trial .days b{display:block;font-size:34px;font-weight:800;letter-spacing:-.045em;line-height:1}
.dsh .trial.ok .days b{color:var(--teal)}
.dsh .trial.warn .days b{color:var(--amber)}
.dsh .trial.crit .days b{color:var(--red)}
.dsh .trial .days span{font-size:11px;font-weight:600;color:var(--mut);letter-spacing:.06em;text-transform:uppercase}
.dsh .trial .acts{display:flex;gap:8px;flex-shrink:0}

/* kpi */
.dsh .kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.dsh .kpi{background:#fff;border:1px solid var(--bd);border-radius:15px;padding:17px 18px;display:flex;flex-direction:column;gap:12px;transition:.15s}
.dsh .kpi:hover{border-color:var(--bd-2);box-shadow:0 8px 24px -14px rgba(13,20,23,.18)}
.dsh .kpi .r1{display:flex;align-items:center;gap:9px}
.dsh .kpi .ic{width:31px;height:31px;border-radius:9px;display:grid;place-items:center;flex-shrink:0}
.dsh .kpi .lb{font-size:12.5px;color:var(--mut);font-weight:600}
.dsh .kpi .r2{display:flex;align-items:flex-end;gap:9px}
.dsh .kpi .v{font-size:31px;font-weight:800;letter-spacing:-.045em;line-height:1}
.dsh .kpi .v small{font-size:16px;font-weight:600;color:var(--mut-2);letter-spacing:-.02em}
.dsh .kpi .dl{font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:2px;padding-bottom:3px}
.dsh .kpi .dl.up{color:var(--wa)}
.dsh .kpi .dl.dn{color:var(--red)}
.dsh .kpi .dl.flat{color:var(--mut-2)}
.dsh .kpi .spark{height:34px;margin:0 -2px}
.dsh .kpi .fo{font-size:11.5px;color:var(--mut-2)}

/* card */
.dsh .card{background:#fff;border:1px solid var(--bd);border-radius:15px;display:flex;flex-direction:column;min-width:0}
.dsh .ch{padding:16px 18px 0;display:flex;align-items:flex-start;gap:12px}
.dsh .ch .m{flex:1;min-width:0}
.dsh .ch h3{font-size:15.5px;font-weight:700;letter-spacing:-.015em;margin:0}
.dsh .ch p{font-size:12.5px;color:var(--mut);margin:2px 0 0}
.dsh .ch .acts{display:flex;gap:6px;align-items:center;flex-shrink:0}
.dsh .cb{padding:16px 18px 18px}

.dsh .legend{display:flex;gap:14px;flex-wrap:wrap}
.dsh .lg{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--mut)}
.dsh .lg i{width:9px;height:9px;border-radius:3px;flex-shrink:0}

.dsh .row2{display:grid;grid-template-columns:minmax(0,1.62fr) minmax(0,1fr);gap:16px}
.dsh .row3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}

/* donut */
.dsh .donut{display:flex;align-items:center;gap:20px}
.dsh .donut .lgs{flex:1;min-width:0;display:flex;flex-direction:column;gap:12px}
.dsh .dl2{display:flex;align-items:center;gap:10px}
.dsh .dl2 i{width:10px;height:10px;border-radius:3px;flex-shrink:0}
.dsh .dl2 .m{flex:1;min-width:0}
.dsh .dl2 .n{font-size:13px;font-weight:600}
.dsh .dl2 .s{font-size:11.5px;color:var(--mut-2)}
.dsh .dl2 .v{font-size:15px;font-weight:700;letter-spacing:-.02em}

/* agents */
.dsh .ag{display:flex;align-items:center;gap:11px;padding:10px 0;border-bottom:1px solid #f1f5f7}
.dsh .ag:last-child{border-bottom:none;padding-bottom:0}
.dsh .ag:first-child{padding-top:0}
.dsh .ag .av{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0;position:relative}
.dsh .ag .m{flex:1;min-width:0}
.dsh .ag .n{font-size:13.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dsh .ag .s{font-size:11.5px;color:var(--mut);margin-top:1px}
.dsh .ag .load{width:74px;flex-shrink:0}
.dsh .ag .load .b{height:5px;border-radius:99px;background:var(--soft-2);overflow:hidden}
.dsh .ag .load .b i{display:block;height:100%;border-radius:99px;background:var(--teal)}
.dsh .ag .load .b i.hot{background:var(--amber)}
.dsh .ag .load .t{font-size:10.5px;color:var(--mut-2);text-align:end;margin-top:3px;font-variant-numeric:tabular-nums}

/* heatmap */
.dsh .hm{display:grid;grid-template-columns:30px repeat(12,minmax(0,1fr));gap:3px;align-items:center}
.dsh .hm .hl{font-size:10px;color:var(--mut-2);font-weight:600;text-align:end;padding-inline-end:3px}
.dsh .hm .cell{aspect-ratio:1;border-radius:4px;background:var(--soft-2);min-height:15px}
.dsh .hm .hd{font-size:9.5px;color:var(--mut-2);text-align:center;font-weight:600}

/* table */
.dsh table{width:100%;border-collapse:collapse}
.dsh thead th{font-size:10.5px;font-weight:700;letter-spacing:.1em;color:var(--mut-2);text-transform:uppercase;text-align:start;padding:0 12px 9px;border-bottom:1px solid var(--bd)}
.dsh tbody td{padding:11px 12px;border-bottom:1px solid #f1f5f7;font-size:13.5px;vertical-align:middle}
.dsh tbody tr:last-child td{border-bottom:none}
.dsh tbody tr{transition:.1s}
.dsh tbody tr:hover{background:var(--soft)}
.dsh .cc{display:flex;align-items:center;gap:10px;min-width:0}
.dsh .cc .avw{position:relative;flex-shrink:0}
.dsh .cc .av{width:33px;height:33px;border-radius:50%;display:grid;place-items:center;font-size:11.5px;font-weight:700;color:#fff}
.dsh .cc .ch2{position:absolute;inset-inline-end:-2px;bottom:-2px;width:15px;height:15px;border-radius:50%;border:2px solid #fff;display:grid;place-items:center}
.dsh .cc .ch2.wa{background:var(--wa)}
.dsh .cc .ch2.lc{background:var(--lc)}
.dsh .cc .m{min-width:0}
.dsh .cc .n{font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dsh .cc .s{font-size:11.5px;color:var(--mut-2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dsh .st{font-size:10.5px;font-weight:700;letter-spacing:.05em;padding:3px 8px;border-radius:5px;text-transform:uppercase;display:inline-block}
.dsh .st.claimed{background:var(--teal-50);color:var(--teal)}
.dsh .st.pool{background:var(--amber-50);color:var(--amber)}
.dsh .st.ai{background:rgba(21,182,168,.14);color:var(--teal-d)}
.dsh .tm{font-size:12.5px;color:var(--mut);font-variant-numeric:tabular-nums;white-space:nowrap}
.dsh .who{display:inline-flex;align-items:center;gap:7px;font-size:13px}
.dsh .who .a{width:22px;height:22px;border-radius:50%;background:var(--teal-d);color:#fff;font-size:9.5px;font-weight:700;display:grid;place-items:center;flex-shrink:0}
.dsh .go{width:29px;height:29px;border-radius:8px;border:1px solid var(--bd);display:grid;place-items:center;color:var(--mut);transition:.12s}
.dsh .go:hover{background:var(--teal);border-color:var(--teal);color:#fff}

/* ai perf */
.dsh .aim{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px}
.dsh .aic{background:var(--soft);border:1px solid var(--bd);border-radius:11px;padding:12px 13px}
.dsh .aic .v{font-size:22px;font-weight:800;letter-spacing:-.04em;line-height:1.1}
.dsh .aic .l{font-size:11.5px;color:var(--mut);margin-top:2px}
.dsh .stack{height:9px;border-radius:99px;overflow:hidden;display:flex;background:var(--soft-2)}
.dsh .stack i{display:block;height:100%}

.dsh .empty{padding:34px 20px;text-align:center;color:var(--mut-2)}
.dsh .empty .t{font-size:14px;font-weight:600;color:var(--mut);margin-top:9px}
.dsh .empty .s{font-size:12.5px;margin-top:2px}

@media (max-width:1240px){
  .dsh .kpis{grid-template-columns:repeat(2,1fr)}
  .dsh .row2,.dsh .row3{grid-template-columns:1fr}
}
@media (max-width:900px){
  .dsh .kpis{grid-template-columns:repeat(2,1fr)}
  .dsh .trial .acts{width:100%}
  .dsh .trial .acts .btn{flex:1}
  .dsh .kpi .v{font-size:26px}
  .dsh .hm{grid-template-columns:26px repeat(12,minmax(0,1fr));gap:2px}
  .dsh .tblwrap{overflow-x:auto}
  .dsh table{min-width:640px}
}
@media (max-width:560px){
  .dsh .kpis{grid-template-columns:1fr}
  .dsh .aim{grid-template-columns:1fr}
  .dsh .donut{flex-direction:column;align-items:stretch}
}
</style>
@endpush

@section('content')
@php
    $panelPrefix = auth()->user()->routeNamePrefix();

    $avatarColours = ['#0f7e7a','#4f6bed','#c2410c','#7c3aed','#0891b2','#be185d'];
    $colourFor = function (?string $s) use ($avatarColours) {
        $sum = 0;
        foreach (str_split((string) $s) as $ch) { $sum += ord($ch); }
        return $avatarColours[$sum % count($avatarColours)];
    };
    $initials = function (?string $name) {
        $name = trim((string) $name);
        if ($name === '') return '·';
        $p = preg_split('/\s+/', $name);
        $a = mb_substr($p[0] ?? '', 0, 1);
        $b = count($p) > 1 ? mb_substr(end($p), 0, 1) : '';
        return mb_strtoupper($a . $b) ?: '·';
    };

    // ── sparkline path builder (pure PHP — no client-side charting) ──
    $spark = function (array $vals, string $colour) {
        $vals = array_map(fn ($v) => (float) $v, $vals);
        if (count($vals) < 2) $vals = [0, 0];
        $w = 200; $h = 34;
        $mx = max($vals); $mn = min($vals); $r = ($mx - $mn) ?: 1;
        $pts = [];
        foreach ($vals as $i => $v) {
            $pts[] = [round($i / (count($vals) - 1) * $w, 1), round($h - 2 - (($v - $mn) / $r) * ($h - 6), 1)];
        }
        $d = '';
        foreach ($pts as $i => $p) { $d .= ($i ? 'L' : 'M') . $p[0] . ' ' . $p[1] . ' '; }
        $id = 'sk' . substr(md5($colour . implode(',', $vals)), 0, 6);
        $area = trim($d) . " L{$w} {$h} L0 {$h} Z";
        return '<svg viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" style="width:100%;height:34px;display:block">'
            . '<defs><linearGradient id="' . $id . '" x1="0" y1="0" x2="0" y2="1">'
            . '<stop offset="0%" stop-color="' . $colour . '" stop-opacity=".22"/>'
            . '<stop offset="100%" stop-color="' . $colour . '" stop-opacity="0"/></linearGradient></defs>'
            . '<path d="' . $area . '" fill="url(#' . $id . ')"/>'
            . '<path d="' . trim($d) . '" fill="none" stroke="' . $colour . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/></svg>';
    };

    $arrow = fn (bool $up) => $up
        ? '<svg width="11" height="11" viewBox="0 0 24 24" fill="none"><path d="M12 19V5M5 12l7-7 7 7" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>'
        : '<svg width="11" height="11" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12l7 7 7-7" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    $waIcon = '<svg width="9" height="9" viewBox="0 0 24 24" fill="#fff"><path d="M12 2a10 10 0 00-8.6 15.1L2 22l5-1.3A10 10 0 1012 2zm0 18.2a8.2 8.2 0 01-4.2-1.15l-.3-.18-3.1.81.83-3.02-.2-.31A8.2 8.2 0 1112 20.2z"/><path d="M17.5 14.4c-.3-.15-1.75-.86-2-.96-.28-.1-.48-.15-.68.15s-.78.96-.95 1.16c-.18.2-.35.22-.65.07a8.2 8.2 0 01-2.4-1.48 9 9 0 01-1.67-2.07c-.17-.3 0-.46.13-.61.14-.14.3-.35.45-.53.15-.18.2-.3.3-.5.1-.2.05-.38-.02-.53-.08-.15-.68-1.6-.93-2.2-.24-.58-.49-.5-.67-.51h-.58c-.2 0-.53.07-.8.38-.28.3-1.05 1.02-1.05 2.5s1.07 2.9 1.22 3.1c.15.2 2.1 3.2 5.1 4.5.71.3 1.27.48 1.7.62.72.23 1.37.2 1.89.12.57-.09 1.75-.72 2-1.4.25-.7.25-1.28.17-1.4-.07-.13-.27-.2-.57-.35z"/></svg>';
    $lcIcon = '<svg width="9" height="9" viewBox="0 0 24 24" fill="none"><rect x="2.5" y="4" width="19" height="13" rx="2.5" fill="#fff"/><path d="M8 20l3-3h2l-5 3z" fill="#fff"/></svg>';

    $sparkConv  = array_map(fn ($d) => $d['wa'] + $d['lc'], $series);
    $sparkResp  = array_map(fn ($d) => $d['resp'] ?? 0, $series);
    $inboxRoute = route($panelPrefix . '.inbox.index');
@endphp

<div class="dsh">

    {{-- ── header ── --}}
    <div class="dhead">
        <div>
            <div class="page-title" style="font-size:20px;font-weight:800;letter-spacing:-.02em">{{ __('ui.dashboard') }}</div>
            <div style="font-size:13px;color:var(--mut)">{{ __('ui.dashboard_page.subtitle', ['days' => $days]) }}</div>
        </div>
        <span class="sp"></span>
        <form method="GET" style="margin:0">
            <select name="range" class="sel" onchange="this.form.submit()">
                @foreach($ranges as $r)
                <option value="{{ $r }}" {{ $days === $r ? 'selected' : '' }}>{{ __('ui.dashboard_page.last_days', ['days' => $r]) }}</option>
                @endforeach
            </select>
        </form>
    </div>

    {{-- ── trial banner ── --}}
    @if($trial)
    <div class="trial {{ $trial['level'] }}">
        <div class="ic">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 7v5.4l3.4 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="m">
            <div class="h">
                {{ __('ui.dashboard_page.trial_title', ['plan' => $trial['plan'] ?? '—']) }}
                <span class="pill">{{ __('ui.dashboard_page.trial_ends', ['date' => $trial['ends_at']->translatedFormat('j M')]) }}</span>
            </div>
            <div class="s">{{ __('ui.dashboard_page.trial_hint', ['used' => $trial['used'], 'total' => $trial['total']]) }}</div>
            <div class="bar"><i style="width:{{ $trial['percent'] }}%"></i></div>
        </div>
        <div class="days">
            <b>{{ $trial['days_left'] }}</b>
            <span>{{ __('ui.dashboard_page.days_left') }}</span>
        </div>
        @if(auth()->user()->isAdmin() && Route::has($panelPrefix . '.billing.index'))
        <div class="acts">
            <a class="btn p sm" href="{{ route($panelPrefix . '.billing.index') }}">{{ __('ui.dashboard_page.choose_plan') }}</a>
        </div>
        @endif
    </div>
    @endif

    {{-- ── KPIs ── --}}
    <div class="kpis">
        @php
            $convDeltaClass = $convDelta === null ? 'flat' : ($convDelta > 0 ? 'up' : ($convDelta < 0 ? 'dn' : 'flat'));
            $replyDeltaClass = $replyDelta === null ? 'flat' : ($replyDelta < 0 ? 'up' : ($replyDelta > 0 ? 'dn' : 'flat'));
        @endphp

        <div class="kpi">
            <div class="r1">
                <span class="ic" style="background:var(--teal-50)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M21 11.5a8.4 8.4 0 01-9 8.4 8.9 8.9 0 01-3.9-.9L3 20.5l1.5-4.6A8.4 8.4 0 013.6 11.5a8.4 8.4 0 018.4-8.4 8.4 8.4 0 019 8.4z" stroke="#0f7e7a" stroke-width="2" stroke-linejoin="round"/></svg></span>
                <span class="lb">{{ __('ui.dashboard_page.kpi_conversations') }}</span>
            </div>
            <div class="r2">
                <span class="v">{{ number_format($convTotal) }}</span>
                <span class="dl {{ $convDeltaClass }}">
                    @if($convDelta === null) — @else {!! $arrow($convDelta > 0) !!}{{ abs($convDelta) }}% @endif
                </span>
            </div>
            <div class="spark">{!! $spark($sparkConv, '#0f7e7a') !!}</div>
            <div class="fo">{{ __('ui.dashboard_page.per_day_avg', ['n' => $days ? round($convTotal / $days) : 0]) }}</div>
        </div>

        <div class="kpi">
            <div class="r1">
                <span class="ic" style="background:var(--amber-50)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="#d97706" stroke-width="2"/><path d="M12 7v5.4l3.4 2" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                <span class="lb">{{ __('ui.dashboard_page.kpi_first_reply') }}</span>
            </div>
            <div class="r2">
                <span class="v">{{ $reply['median'] !== null ? $reply['median'] : '—' }}@if($reply['median'] !== null)<small> {{ __('ui.dashboard_page.min') }}</small>@endif</span>
                <span class="dl {{ $replyDeltaClass }}">
                    @if($replyDelta === null) — @else {!! $arrow($replyDelta > 0) !!}{{ abs($replyDelta) }}% @endif
                </span>
            </div>
            <div class="spark">{!! $spark($sparkResp, '#d97706') !!}</div>
            <div class="fo">
                @if($reply['previous'] !== null)
                    {{ __('ui.dashboard_page.was_before', ['n' => $reply['previous']]) }}
                @else
                    {{ __('ui.dashboard_page.no_baseline') }}
                @endif
            </div>
        </div>

        <div class="kpi">
            <div class="r1">
                <span class="ic" style="background:rgba(21,182,168,.13)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z" fill="#15b6a8"/></svg></span>
                <span class="lb">{{ __('ui.dashboard_page.kpi_ai_resolved') }}</span>
            </div>
            <div class="r2">
                <span class="v">{{ $ai['ai_pct'] }}<small> %</small></span>
            </div>
            <div class="spark">{!! $spark($sparkConv, '#15b6a8') !!}</div>
            <div class="fo">{{ __('ui.dashboard_page.ai_of_total', ['n' => $ai['ai_only'], 'total' => $ai['total']]) }}</div>
        </div>

        <div class="kpi">
            <div class="r1">
                <span class="ic" style="background:var(--lc-50)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M16 20v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2" stroke="#4f6bed" stroke-width="2" stroke-linecap="round"/><circle cx="9" cy="7" r="3.4" stroke="#4f6bed" stroke-width="2"/></svg></span>
                <span class="lb">{{ __('ui.dashboard_page.kpi_pool') }}</span>
            </div>
            <div class="r2"><span class="v">{{ $pool['count'] }}</span></div>
            <div class="spark">{!! $spark($sparkConv, '#4f6bed') !!}</div>
            <div class="fo">
                @if($pool['oldest'])
                    {{ __('ui.dashboard_page.oldest_waiting', ['time' => \Carbon\Carbon::parse($pool['oldest'])->diffForHumans(null, true)]) }}
                @else
                    {{ __('ui.dashboard_page.pool_clear') }}
                @endif
            </div>
        </div>
    </div>

    {{-- ── volume + channel split ── --}}
    <div class="row2">
        <div class="card">
            <div class="ch">
                <div class="m">
                    <h3>{{ __('ui.dashboard_page.volume_title') }}</h3>
                    <p>{{ __('ui.dashboard_page.volume_hint') }}</p>
                </div>
                <div class="legend">
                    <span class="lg"><i style="background:#25a35a"></i>{{ __('ui.inbox_page.whatsapp') }}</span>
                    <span class="lg"><i style="background:#4f6bed"></i>{{ __('ui.inbox_page.live_chat') }}</span>
                </div>
            </div>
            <div class="cb">
                @php
                    $maxDay = max(1, max(array_map(fn ($d) => $d['wa'] + $d['lc'], $series ?: [['wa' => 0, 'lc' => 0]])));
                    $top    = (int) (ceil($maxDay / 5) * 5) ?: 5;
                    $W = 760; $H = 210; $PL = 32; $PB = 26; $PT = 8;
                    $iw = $W - $PL; $ih = $H - $PB - $PT;
                    $gap = count($series) ? $iw / count($series) : $iw;
                    $bw  = $gap * .56;
                @endphp
                <svg viewBox="0 0 {{ $W }} {{ $H }}" style="width:100%;height:auto;display:block">
                    @for($i = 0; $i <= 4; $i++)
                        @php $y = $PT + $ih - ($i / 4) * $ih; @endphp
                        <line x1="{{ $PL }}" y1="{{ $y }}" x2="{{ $W }}" y2="{{ $y }}" stroke="#e6ebf0" stroke-width="1"/>
                        <text x="{{ $PL - 8 }}" y="{{ $y + 4 }}" text-anchor="end" font-size="10" fill="#94a3b8">{{ round($top * $i / 4) }}</text>
                    @endfor
                    @foreach($series as $i => $d)
                        @php
                            $x  = $PL + $gap * $i + ($gap - $bw) / 2;
                            $hw = ($d['wa'] / $top) * $ih;
                            $hl = ($d['lc'] / $top) * $ih;
                        @endphp
                        @if($hl > 0)<rect x="{{ $x }}" y="{{ $PT + $ih - $hw - $hl }}" width="{{ $bw }}" height="{{ $hl }}" fill="#4f6bed" rx="3"/>@endif
                        @if($hw > 0)<rect x="{{ $x }}" y="{{ $PT + $ih - $hw }}" width="{{ $bw }}" height="{{ $hw }}" fill="#25a35a" rx="3"/>@endif
                        <rect x="{{ $x }}" y="{{ $PT }}" width="{{ $bw }}" height="{{ $ih }}" fill="transparent"><title>{{ $d['full'] }} · {{ __('ui.inbox_page.whatsapp') }} {{ $d['wa'] }} · {{ __('ui.inbox_page.live_chat') }} {{ $d['lc'] }}</title></rect>
                        @if($i % 2 === 0 || $i === count($series) - 1)
                        <text x="{{ $x + $bw / 2 }}" y="{{ $H - 8 }}" text-anchor="middle" font-size="10" fill="#94a3b8">{{ $d['label'] }}</text>
                        @endif
                    @endforeach
                </svg>
            </div>
        </div>

        <div class="card">
            <div class="ch"><div class="m"><h3>{{ __('ui.dashboard_page.channels_title') }}</h3><p>{{ __('ui.dashboard_page.channels_hint') }}</p></div></div>
            <div class="cb">
                @php
                    $tot  = $waTotal + $lcTotal;
                    $p1   = $tot > 0 ? $waTotal / $tot : 0;
                    $R    = 52; $SW = 17; $CIRC = 2 * M_PI * $R; $seg1 = $CIRC * $p1;
                @endphp
                <div class="donut">
                    <svg width="132" height="132" viewBox="0 0 132 132" style="flex-shrink:0">
                        <circle cx="66" cy="66" r="{{ $R }}" fill="none" stroke="{{ $tot ? '#4f6bed' : '#eef2f5' }}" stroke-width="{{ $SW }}"/>
                        @if($tot)
                        <circle cx="66" cy="66" r="{{ $R }}" fill="none" stroke="#25a35a" stroke-width="{{ $SW }}"
                                stroke-dasharray="{{ round($seg1, 2) }} {{ round($CIRC - $seg1, 2) }}"
                                stroke-dashoffset="{{ round($CIRC * .25, 2) }}" stroke-linecap="butt" transform="rotate(-90 66 66)"/>
                        @endif
                        <text x="66" y="62" text-anchor="middle" font-size="25" font-weight="800" fill="#0f172a" letter-spacing="-1">{{ $tot }}</text>
                        <text x="66" y="79" text-anchor="middle" font-size="10.5" font-weight="600" fill="#64748b">{{ __('ui.dashboard_page.total') }}</text>
                    </svg>
                    <div class="lgs">
                        <div class="dl2"><i style="background:#25a35a"></i><div class="m"><div class="n">{{ __('ui.inbox_page.whatsapp') }}</div><div class="s">{{ round($p1 * 100) }}% {{ __('ui.dashboard_page.of_volume') }}</div></div><span class="v">{{ $waTotal }}</span></div>
                        <div class="dl2"><i style="background:#4f6bed"></i><div class="m"><div class="n">{{ __('ui.inbox_page.live_chat') }}</div><div class="s">{{ round((1 - $p1) * 100) }}% {{ __('ui.dashboard_page.of_volume') }}</div></div><span class="v">{{ $lcTotal }}</span></div>
                        <div style="border-top:1px solid var(--bd);padding-top:11px;margin-top:2px">
                            <div class="dl2"><i style="background:#15b6a8"></i><div class="m"><div class="n">{{ __('ui.dashboard_page.repeat_customers') }}</div><div class="s">{{ __('ui.dashboard_page.repeat_hint') }}</div></div><span class="v">{{ $repeatPct }}%</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── response time + agents + heatmap ── --}}
    <div class="row3">
        <div class="card">
            <div class="ch"><div class="m"><h3>{{ __('ui.dashboard_page.response_title') }}</h3><p>{{ __('ui.dashboard_page.response_hint') }}</p></div></div>
            <div class="cb">
                @php
                    $vals = array_map(fn ($d) => (float) ($d['resp'] ?? 0), $series);
                    $rmax = max(1, ceil(max($vals ?: [1])));
                    $W2 = 340; $H2 = 168; $PL2 = 28; $PB2 = 22; $PT2 = 10;
                    $iw2 = $W2 - $PL2; $ih2 = $H2 - $PB2 - $PT2;
                    $pts = [];
                    foreach ($vals as $i => $v) {
                        $pts[] = [
                            round($PL2 + (count($vals) > 1 ? $i / (count($vals) - 1) : 0) * $iw2, 1),
                            round($PT2 + $ih2 - ($v / $rmax) * $ih2, 1),
                        ];
                    }
                    $dpath = '';
                    foreach ($pts as $i => $p) { $dpath .= ($i ? 'L' : 'M') . $p[0] . ' ' . $p[1] . ' '; }
                    $dpath = trim($dpath);
                    $last  = end($pts) ?: [$PL2, $PT2 + $ih2];
                    $avg   = $vals ? round(array_sum($vals) / max(1, count(array_filter($vals))), 1) : 0;
                @endphp
                <svg viewBox="0 0 {{ $W2 }} {{ $H2 }}" style="width:100%;height:auto;display:block">
                    <defs><linearGradient id="rgrad" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#0f7e7a" stop-opacity=".18"/><stop offset="100%" stop-color="#0f7e7a" stop-opacity="0"/></linearGradient></defs>
                    @for($i = 0; $i <= 3; $i++)
                        @php $y = $PT2 + $ih2 - ($i / 3) * $ih2; @endphp
                        <line x1="{{ $PL2 }}" y1="{{ $y }}" x2="{{ $W2 }}" y2="{{ $y }}" stroke="#e6ebf0"/>
                        <text x="{{ $PL2 - 7 }}" y="{{ $y + 4 }}" text-anchor="end" font-size="9.5" fill="#94a3b8">{{ round($rmax * $i / 3) }}m</text>
                    @endfor
                    @if(count($pts) > 1)
                    <path d="{{ $dpath }} L{{ $last[0] }} {{ $PT2 + $ih2 }} L{{ $PL2 }} {{ $PT2 + $ih2 }} Z" fill="url(#rgrad)"/>
                    <path d="{{ $dpath }}" fill="none" stroke="#0f7e7a" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="{{ $last[0] }}" cy="{{ $last[1] }}" r="4" fill="#fff" stroke="#0f7e7a" stroke-width="2.5"/>
                    @endif
                    @foreach($pts as $i => $p)
                    <circle cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="8" fill="transparent"><title>{{ $series[$i]['full'] }} · {{ $series[$i]['resp'] ?? 0 }} min</title></circle>
                    @endforeach
                </svg>
                <div style="display:flex;gap:18px;margin-top:12px;padding-top:12px;border-top:1px solid var(--bd)">
                    <div><div style="font-size:18px;font-weight:800;letter-spacing:-.03em">{{ $reply['median'] ?? '—' }} {{ __('ui.dashboard_page.min') }}</div><div style="font-size:11.5px;color:var(--mut)">{{ __('ui.dashboard_page.median') }}</div></div>
                    <div><div style="font-size:18px;font-weight:800;letter-spacing:-.03em">{{ $avg ?: '—' }} {{ __('ui.dashboard_page.min') }}</div><div style="font-size:11.5px;color:var(--mut)">{{ __('ui.dashboard_page.period_average') }}</div></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="ch">
                <div class="m"><h3>{{ __('ui.dashboard_page.agents_title') }}</h3><p>{{ __('ui.dashboard_page.agents_hint') }}</p></div>
                @if(auth()->user()->isAdmin())
                <a class="btn g sm" href="{{ route($panelPrefix . '.users.index') }}">{{ __('ui.manage') }}</a>
                @endif
            </div>
            <div class="cb">
                @forelse($agents as $a)
                    @php $pct = $a['cap'] ? min(100, (int) round($a['open'] / $a['cap'] * 100)) : 0; @endphp
                    <div class="ag">
                        <div class="av" style="background:{{ $colourFor($a['name']) }}">{{ $initials($a['name']) }}</div>
                        <div class="m">
                            <div class="n">{{ $a['name'] }}</div>
                            <div class="s">{{ __('ui.dashboard_page.closed_today', ['n' => $a['today']]) }}</div>
                        </div>
                        <div class="load">
                            <div class="b"><i class="{{ $pct >= 80 ? 'hot' : '' }}" style="width:{{ $pct }}%"></i></div>
                            <div class="t">{{ $a['open'] }}/{{ $a['cap'] }}</div>
                        </div>
                    </div>
                @empty
                    <div class="empty">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" style="margin:0 auto"><path d="M16 20v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="9" cy="7" r="3.4" stroke="currentColor" stroke-width="1.8"/></svg>
                        <div class="t">{{ __('ui.dashboard_page.no_agents') }}</div>
                        <div class="s">{{ __('ui.dashboard_page.no_agents_hint') }}</div>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="ch"><div class="m"><h3>{{ __('ui.dashboard_page.hours_title') }}</h3><p>{{ __('ui.dashboard_page.hours_hint') }}</p></div></div>
            <div class="cb">
                @php
                    $hours = range(8, 19);
                    $dayNames = [__('ui.days.mon'), __('ui.days.tue'), __('ui.days.wed'), __('ui.days.thu'), __('ui.days.fri'), __('ui.days.sat'), __('ui.days.sun')];
                    $hmax = max(1, $heat['max']);
                @endphp
                <div class="hm">
                    <div></div>
                    @foreach($hours as $h)<div class="hd">{{ $h }}</div>@endforeach
                    @foreach($dayNames as $di => $dn)
                        <div class="hl">{{ $dn }}</div>
                        @foreach($hours as $h)
                            @php
                                $n = $heat['grid'][$di][$h] ?? 0;
                                $v = $n / $hmax;
                                $bg = $n === 0 ? 'var(--soft-2)' : 'rgba(15,126,122,' . number_format(0.12 + $v * 0.85, 2) . ')';
                            @endphp
                            <div class="cell" style="background:{{ $bg }}" title="{{ $dn }} {{ $h }}:00 · {{ $n }}"></div>
                        @endforeach
                    @endforeach
                </div>
                <div style="display:flex;align-items:center;gap:7px;margin-top:12px;font-size:11px;color:var(--mut)">
                    {{ __('ui.dashboard_page.quiet') }}
                    <span style="display:flex;gap:2px">
                        @foreach([.12,.32,.52,.72,.95] as $o)<i style="width:14px;height:9px;border-radius:2px;background:rgba(15,126,122,{{ $o }});display:block"></i>@endforeach
                    </span>
                    {{ __('ui.dashboard_page.busy') }}
                    @if($heat['peak']['hour'] !== null)
                    <span style="margin-inline-start:auto;font-weight:600;color:var(--txt)">{{ __('ui.dashboard_page.peak', ['hour' => $heat['peak']['hour']]) }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ── AI performance ── --}}
    <div class="card">
        <div class="ch">
            <div class="m"><h3>{{ __('ui.dashboard_page.ai_title') }}</h3><p>{{ __('ui.dashboard_page.ai_hint') }}</p></div>
            <div class="acts">
                @if($aiSettings)
                <span class="st ai">{{ __('ui.ai_settings_page.modes.' . $aiSettings->mode . '.label') }}</span>
                @endif
                @if(auth()->user()->isAdmin())
                <a class="btn g sm" href="{{ route($panelPrefix . '.ai-settings.index') }}">{{ __('ui.sidebar.ai_agent') }}</a>
                @endif
            </div>
        </div>
        <div class="cb">
            @php
                $t = max(1, $ai['total']);
                $p = fn ($n) => round($n / $t * 100, 1);
            @endphp
            <div class="aim">
                <div class="aic"><div class="v">{{ $ai['ai_pct'] }}%</div><div class="l">{{ __('ui.dashboard_page.ai_resolved_label') }}</div></div>
                <div class="aic"><div class="v">{{ $ai['ai_median_seconds'] !== null ? $ai['ai_median_seconds'] . 's' : '—' }}</div><div class="l">{{ __('ui.dashboard_page.ai_speed_label') }}</div></div>
                <div class="aic"><div class="v">{{ $ai['both'] }}</div><div class="l">{{ __('ui.dashboard_page.ai_handover_label') }}</div></div>
            </div>
            <div class="stack">
                <i style="width:{{ $p($ai['ai_only']) }}%;background:var(--teal)"></i>
                <i style="width:{{ $p($ai['both']) }}%;background:var(--teal-l)"></i>
                <i style="width:{{ $p($ai['agent_only']) }}%;background:var(--amber)"></i>
            </div>
            <div class="legend" style="margin-top:11px">
                <span class="lg"><i style="background:var(--teal)"></i>{{ __('ui.dashboard_page.ai_only') }} · {{ $ai['ai_only'] }}</span>
                <span class="lg"><i style="background:var(--teal-l)"></i>{{ __('ui.dashboard_page.ai_then_agent') }} · {{ $ai['both'] }}</span>
                <span class="lg"><i style="background:var(--amber)"></i>{{ __('ui.dashboard_page.agent_only') }} · {{ $ai['agent_only'] }}</span>
            </div>
        </div>
    </div>

    {{-- ── needs attention ── --}}
    <div class="card">
        <div class="ch">
            <div class="m"><h3>{{ __('ui.dashboard_page.needs_title') }}</h3><p>{{ __('ui.dashboard_page.needs_hint') }}</p></div>
            <a class="btn g sm" href="{{ $inboxRoute }}">{{ __('ui.dashboard_page.open_inbox') }}</a>
        </div>
        <div class="cb" style="padding-top:12px">
            @if($needs->isEmpty())
                <div class="empty">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" style="margin:0 auto"><path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <div class="t">{{ __('ui.dashboard_page.all_clear') }}</div>
                    <div class="s">{{ __('ui.dashboard_page.all_clear_hint') }}</div>
                </div>
            @else
            <div class="tblwrap">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('ui.dashboard_page.col_contact') }}</th>
                            <th>{{ __('ui.dashboard_page.col_channel') }}</th>
                            <th>{{ __('ui.dashboard_page.col_status') }}</th>
                            <th>{{ __('ui.dashboard_page.col_agent') }}</th>
                            <th>{{ __('ui.dashboard_page.col_waiting') }}</th>
                            <th style="width:44px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($needs as $r)
                        <tr>
                            <td>
                                <div class="cc">
                                    <div class="avw">
                                        <div class="av" style="background:{{ $colourFor($r['name']) }}">{{ $initials($r['name']) }}</div>
                                        <span class="ch2 {{ $r['channel'] === 'whatsapp' ? 'wa' : 'lc' }}">{!! $r['channel'] === 'whatsapp' ? $waIcon : $lcIcon !!}</span>
                                    </div>
                                    <div class="m">
                                        <div class="n">{{ $r['name'] }}</div>
                                        <div class="s">{{ $r['sub'] ?: '—' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td><span style="font-size:12.5px;color:var(--mut);font-weight:600">{{ $r['channel'] === 'whatsapp' ? __('ui.inbox_page.whatsapp') : __('ui.inbox_page.live_chat') }}</span></td>
                            <td><span class="st {{ $r['state'] }}">{{ __('ui.dashboard_page.state_' . $r['state']) }}</span></td>
                            <td>
                                @if($r['agent'])
                                    <span class="who"><span class="a">{{ mb_substr($r['agent'], 0, 1) }}</span>{{ $r['agent'] }}</span>
                                @else
                                    <span style="color:var(--mut-2);font-size:12.5px">{{ __('ui.dashboard_page.unassigned') }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="tm" style="{{ $r['waiting'] ? 'color:var(--amber);font-weight:600' : '' }}">
                                    {{ $r['since'] ? \Carbon\Carbon::parse($r['since'])->diffForHumans(null, true) : '—' }}
                                </span>
                            </td>
                            <td>
                                <a class="go" href="{{ $inboxRoute }}">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12h13M12 5l7 7-7 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
