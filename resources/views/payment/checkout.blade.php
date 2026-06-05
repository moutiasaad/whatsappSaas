<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ data_get(config('locales.supported', []), app()->getLocale() . '.rtl') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('auth.register.checkout_title', ['app' => config('app.name', 'WA Support')]) }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --brand:#10b981; --brand-dark:#059669; --brand-light:#d1fae5; --brand-xlight:#ecfdf5; --border:#e2e8f0; --text:#0f172a; --muted:#64748b; }
        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Outfit',system-ui,sans-serif; background:#f8fafc; min-height:100vh; display:flex; flex-direction:column; }

        nav { background:rgba(255,255,255,.92); backdrop-filter:blur(12px); border-bottom:1px solid var(--border); padding:13px 24px; display:flex; align-items:center; gap:12px; }
        .nav-logo { display:flex; align-items:center; gap:9px; font-size:17px; font-weight:800; color:var(--text); text-decoration:none; }
        .nav-logo-icon { width:30px; height:30px; border-radius:8px; overflow:hidden; display:flex; align-items:center; justify-content:center; }

        main { flex:1; display:flex; align-items:center; justify-content:center; padding:40px 20px; }
        .checkout-box { width:100%; max-width:520px; background:#fff; border:1px solid var(--border); border-radius:20px; overflow:hidden; box-shadow:0 8px 40px rgba(0,0,0,.06); }

        .checkout-header { background:linear-gradient(135deg,#0d1117,#111827); padding:28px 32px; }
        .checkout-header h1 { font-size:22px; font-weight:800; color:#f1f5f9; margin-bottom:6px; }
        .checkout-header p  { font-size:14px; color:#64748b; }

        .checkout-body { padding:28px 32px; }

        .order-row { display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid var(--border); font-size:14px; }
        .order-row:last-of-type { border-bottom:none; }
        .order-label { color:var(--muted); }
        .order-value { font-weight:600; color:var(--text); }
        .order-total .order-label { font-size:16px; font-weight:700; color:var(--text); }
        .order-total .order-value { font-size:22px; font-weight:900; color:var(--brand); }

        .stripe-info { background:#f0f4ff; border:1px solid #c7d2fe; border-radius:12px; padding:14px 16px; margin:20px 0; display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#4f46e5; }
        .stripe-badge { display:inline-flex; align-items:center; gap:5px; background:#635bff; color:#fff; font-size:11px; font-weight:700; padding:3px 8px; border-radius:6px; letter-spacing:.3px; }

        .error-box { background:#fef2f2; border:1px solid #fecaca; border-radius:10px; padding:12px 14px; font-size:13px; color:#dc2626; margin-bottom:18px; display:flex; align-items:flex-start; gap:8px; }

        .btn-pay { width:100%; padding:15px; background:linear-gradient(135deg,var(--brand),var(--brand-dark)); color:#fff; border:none; border-radius:12px; font-size:16px; font-weight:700; font-family:'Outfit',sans-serif; cursor:pointer; transition:all .2s; box-shadow:0 4px 14px rgba(16,185,129,.3); }
        .btn-pay:hover { box-shadow:0 6px 20px rgba(16,185,129,.45); transform:translateY(-1px); }
        .btn-pay:disabled { opacity:.7; cursor:not-allowed; transform:none; }
        .btn-back { display:block; text-align:center; margin-top:14px; font-size:13px; color:var(--muted); text-decoration:none; }
        .btn-back:hover { color:var(--brand); }

        .security-note { text-align:center; font-size:12px; color:var(--muted); margin-top:16px; display:flex; align-items:center; justify-content:center; gap:6px; flex-wrap:wrap; }
    </style>
</head>
<body>
<nav>
    <a href="{{ route('landing') }}" class="nav-logo">
        <div class="nav-logo-icon">
            <img src="{{ asset('images/wavadesk-icon.svg') }}" alt="wavadesk" width="30" height="30">
        </div>
        wavadesk
    </a>
</nav>

<main>
    <div class="checkout-box">
        <div class="checkout-header">
            <h1>{{ __('auth.register.checkout_heading') }}</h1>
            <p>{{ $tenant->name }}</p>
        </div>

        <div class="checkout-body">
            @if($errors->has('payment'))
            <div class="error-box">
                <svg width="15" height="15" fill="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ $errors->first('payment') }}
            </div>
            @endif

            <div class="order-row">
                <span class="order-label">{{ __('auth.register.order_plan') }}</span>
                <span class="order-value">{{ $plan->name }}</span>
            </div>
            <div class="order-row">
                <span class="order-label">{{ __('auth.register.order_workspace') }}</span>
                <span class="order-value">{{ $tenant->name }}</span>
            </div>
            <div class="order-row">
                <span class="order-label">{{ __('auth.register.order_admin') }}</span>
                <span class="order-value">{{ $admin?->email ?? '—' }}</span>
            </div>
            @if($plan->max_users)
            <div class="order-row">
                <span class="order-label">{{ __('auth.register.order_limits') }}</span>
                <span class="order-value">
                    {{ __('ui.platform_plans_page.users_limit', ['count' => $plan->max_users]) }}
                    · {{ $plan->max_instances }} instances
                </span>
            </div>
            @endif
            <div class="order-row order-total" style="padding-top:18px;margin-top:8px;border-top:2px solid var(--border);border-bottom:none">
                <span class="order-label">{{ __('auth.register.order_total') }}</span>
                <span class="order-value">USD {{ number_format($amount, 2) }}</span>
            </div>

            <div class="stripe-info">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <div>
                    {{ __('auth.register.stripe_info') }}
                    &nbsp;<span class="stripe-badge">stripe</span>
                </div>
            </div>

            <form method="POST" action="{{ route('payment.initiate') }}" onsubmit="handlePay(this)">
                @csrf
                <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                <button type="submit" class="btn-pay" id="payBtn">
                    {{ __('auth.register.pay_btn', ['amount' => number_format($amount, 2)]) }}
                </button>
            </form>

            <a href="{{ route('register') }}" class="btn-back">← {{ __('auth.register.back_to_register') }}</a>

            <div class="security-note">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                {{ __('auth.register.secure_payment') }}
            </div>
        </div>
    </div>
</main>

<script>
function handlePay(form) {
    const btn = document.getElementById('payBtn');
    btn.disabled = true;
    btn.textContent = '{{ __("ui.processing") }}';
}
</script>
</body>
</html>
