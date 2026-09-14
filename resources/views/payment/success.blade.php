<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ data_get(config('locales.supported', []), app()->getLocale() . '.rtl') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('auth.register.success_title', ['app' => config('app.name', 'WA Support')]) }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @if($redirectToDash ?? false)
    <meta http-equiv="refresh" content="5;url={{ auth()->check() ? route(auth()->user()->homeRouteName()) : route('login') }}">
    @endif
    <style>
        :root { --brand:#10b981; --brand-dark:#059669; --brand-xlight:#ecfdf5; --border:#e2e8f0; --text:#0f172a; --muted:#64748b; }
        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Outfit',system-ui,sans-serif; background:#f8fafc; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
        html[dir="rtl"] body { font-family:'Cairo',sans-serif; line-height:1.65; }
        @keyframes scaleIn { from{opacity:0;transform:scale(.7)} to{opacity:1;transform:scale(1)} }
        @keyframes fadeInUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
        @keyframes checkDraw { from{stroke-dashoffset:100} to{stroke-dashoffset:0} }
        .card { background:#fff; border:1px solid var(--border); border-radius:24px; padding:48px 40px; max-width:460px; width:100%; text-align:center; box-shadow:0 8px 40px rgba(0,0,0,.06); animation:scaleIn .5s ease both; }
        .icon-wrap { width:80px; height:80px; border-radius:50%; background:var(--brand-xlight); border:2px solid var(--brand); display:flex; align-items:center; justify-content:center; margin:0 auto 24px; }
        .icon-wrap svg { animation:checkDraw .5s ease .3s both; stroke-dasharray:100; stroke-dashoffset:100; }
        h1 { font-size:26px; font-weight:800; color:var(--text); margin-bottom:10px; animation:fadeInUp .5s ease .2s both; }
        p  { font-size:15px; color:var(--muted); line-height:1.65; animation:fadeInUp .5s ease .3s both; }
        .details { background:var(--brand-xlight); border:1px solid #a7f3d0; border-radius:14px; padding:18px 20px; margin:24px 0; animation:fadeInUp .5s ease .4s both; }
        .detail-row { display:flex; justify-content:space-between; font-size:14px; padding:6px 0; }
        .detail-label { color:var(--muted); }
        .detail-value { font-weight:600; color:var(--text); }
        .btn { display:block; width:100%; padding:14px; background:linear-gradient(135deg,var(--brand),var(--brand-dark)); color:#fff; border:none; border-radius:12px; font-size:15px; font-weight:700; font-family:inherit; cursor:pointer; text-decoration:none; text-align:center; margin-top:24px; animation:fadeInUp .5s ease .5s both; box-shadow:0 4px 14px rgba(16,185,129,.3); transition:all .2s; }
        .btn:hover { box-shadow:0 6px 20px rgba(16,185,129,.45); transform:translateY(-1px); }
        .redirect-note { font-size:12px; color:var(--muted); margin-top:12px; animation:fadeInUp .5s ease .6s both; }
    </style>
</head>
<body>
<div class="card">
    <div class="icon-wrap">
        <svg width="36" height="36" fill="none" stroke="#10b981" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <path d="M20 6L9 17l-5-5"/>
        </svg>
    </div>

    @if($payment?->isCompleted())
        {{-- An AI top-up did not change the plan, so it must not claim it did. --}}
        @if($payment->isAiPack())
            <h1>{{ __('ui.payment_page.pack_success_heading') }}</h1>
            <p>{{ __('ui.payment_page.pack_success_desc', ['n' => number_format($payment->packMessages())]) }}</p>
        @elseif($payment->isSeatPack())
            <h1>{{ __('ui.payment_page.seat_success_heading') }}</h1>
            <p>{{ trans_choice('ui.payment_page.seat_success_desc', $payment->packSeats(), ['count' => $payment->packSeats()]) }}</p>
        @else
            <h1>{{ __('auth.register.payment_success_heading') }}</h1>
            <p>{{ __('auth.register.payment_success_desc', ['plan' => $plan?->name ?? '']) }}</p>
        @endif

        @if($tenant || $plan || $payment->isAiPack() || $payment->isSeatPack())
        <div class="details">
            @if($payment->isAiPack())
            <div class="detail-row">
                <span class="detail-label">{{ __('ui.payment_page.pack_line') }}</span>
                <span class="detail-value">{{ __('ui.payment_page.pack_name', ['n' => number_format($payment->packMessages())]) }}</span>
            </div>
            @elseif($payment->isSeatPack())
            <div class="detail-row">
                <span class="detail-label">{{ __('ui.payment_page.seat_line') }}</span>
                <span class="detail-value">{{ trans_choice('ui.payment_page.seat_name', $payment->packSeats(), ['count' => $payment->packSeats()]) }}</span>
            </div>
            @elseif($plan)
            <div class="detail-row">
                <span class="detail-label">{{ __('auth.register.order_plan') }}</span>
                <span class="detail-value">{{ $plan->name }}</span>
            </div>
            @endif
            @if($tenant)
            <div class="detail-row">
                <span class="detail-label">{{ __('auth.register.order_workspace') }}</span>
                <span class="detail-value">{{ $tenant->name }}</span>
            </div>
            @endif
            @if($payment?->amount)
            <div class="detail-row">
                <span class="detail-label">{{ __('auth.register.order_total') }}</span>
                <span class="detail-value">USD {{ number_format((float)$payment->amount, 2) }}</span>
            </div>
            @endif
        </div>
        @endif

        @if($redirectToDash ?? false)
            <a href="{{ auth()->check() ? route(auth()->user()->homeRouteName()) : route('login') }}" class="btn">
                {{ __('auth.register.go_to_dashboard') }}
            </a>
            <p class="redirect-note">{{ __('auth.register.redirect_note') }}</p>
        @else
            <a href="{{ route('login') }}" class="btn">{{ __('auth.login.sign_in') }}</a>
        @endif
    @else
        <h1>{{ __('auth.register.payment_pending_heading') }}</h1>
        <p>{{ __('auth.register.payment_pending_desc') }}</p>
        <a href="{{ route('login') }}" class="btn">{{ __('auth.login.sign_in') }}</a>
    @endif
</div>
</body>
</html>
