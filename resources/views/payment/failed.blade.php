<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ data_get(config('locales.supported', []), app()->getLocale() . '.rtl') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('auth.register.failed_title', ['app' => config('app.name', 'WA Support')]) }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --brand:#10b981; --brand-dark:#059669; --brand-xlight:#ecfdf5; --border:#e2e8f0; --text:#0f172a; --muted:#64748b; --red:#ef4444; --red-bg:#fef2f2; }
        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Outfit',system-ui,sans-serif; background:#f8fafc; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
        html[dir="rtl"] body { font-family:'Cairo',sans-serif; line-height:1.65; }
        @keyframes scaleIn { from{opacity:0;transform:scale(.7)} to{opacity:1;transform:scale(1)} }
        @keyframes fadeInUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
        .card { background:#fff; border:1px solid var(--border); border-radius:24px; padding:48px 40px; max-width:460px; width:100%; text-align:center; box-shadow:0 8px 40px rgba(0,0,0,.06); animation:scaleIn .5s ease both; }
        .icon-wrap { width:80px; height:80px; border-radius:50%; background:var(--red-bg); border:2px solid #fecaca; display:flex; align-items:center; justify-content:center; margin:0 auto 24px; }
        h1 { font-size:26px; font-weight:800; color:var(--text); margin-bottom:10px; animation:fadeInUp .5s ease .2s both; }
        p  { font-size:15px; color:var(--muted); line-height:1.65; animation:fadeInUp .5s ease .3s both; }
        .actions { display:flex; flex-direction:column; gap:12px; margin-top:28px; animation:fadeInUp .5s ease .4s both; }
        .btn-primary { display:block; padding:14px; background:linear-gradient(135deg,var(--brand),var(--brand-dark)); color:#fff; border:none; border-radius:12px; font-size:15px; font-weight:700; font-family:inherit; cursor:pointer; text-decoration:none; box-shadow:0 4px 14px rgba(16,185,129,.3); transition:all .2s; }
        .btn-primary:hover { box-shadow:0 6px 20px rgba(16,185,129,.45); transform:translateY(-1px); }
        .btn-ghost { display:block; padding:12px; border:1.5px solid var(--border); border-radius:12px; font-size:14px; font-weight:600; color:var(--muted); text-decoration:none; transition:all .2s; }
        .btn-ghost:hover { border-color:var(--brand); color:var(--brand); background:var(--brand-xlight); }
    </style>
</head>
<body>
<div class="card">
    <div class="icon-wrap">
        <svg width="36" height="36" fill="none" stroke="#ef4444" stroke-width="3" stroke-linecap="round" viewBox="0 0 24 24">
            <path d="M18 6L6 18M6 6l12 12"/>
        </svg>
    </div>

    <h1>{{ __('auth.register.payment_failed_heading') }}</h1>
    <p>{{ __('auth.register.payment_failed_desc') }}</p>

    <div class="actions">
        @if($payment?->tenant_id)
        <a href="{{ route('payment.checkout', $payment->tenant_id) }}" class="btn-primary">
            {{ __('auth.register.retry_payment') }}
        </a>
        @endif
        <a href="{{ route('register') }}" class="btn-ghost">{{ __('auth.register.back_to_register') }}</a>
    </div>
</div>
</body>
</html>
