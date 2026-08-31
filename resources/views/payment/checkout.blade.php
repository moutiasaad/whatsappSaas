<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ data_get(config('locales.supported', []), app()->getLocale() . '.rtl') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('auth.register.checkout_title', ['app' => config('app.name', 'WA Support')]) }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --brand:#10b981; --brand-dark:#059669; --brand-light:#d1fae5; --brand-xlight:#ecfdf5; --border:#e2e8f0; --text:#0f172a; --muted:#64748b; }
        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Outfit',system-ui,sans-serif; background:#f8fafc; min-height:100vh; display:flex; flex-direction:column; }
        html[dir="rtl"] body { font-family:'Cairo',sans-serif; line-height:1.65; }

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
        .btn-paypal { width:100%; padding:15px; background:linear-gradient(135deg,#ffc439,#f5b800); color:#003087; border:none; border-radius:12px; font-size:16px; font-weight:800; font-family:'Outfit',sans-serif; cursor:pointer; transition:all .2s; box-shadow:0 4px 14px rgba(255,196,57,.35); display:flex; align-items:center; justify-content:center; gap:8px; }
        .btn-paypal:hover { box-shadow:0 6px 20px rgba(255,196,57,.5); transform:translateY(-1px); }
        .btn-paypal:disabled { opacity:.7; cursor:not-allowed; transform:none; }
        .pay-divider { display:flex; align-items:center; gap:12px; margin:14px 0; color:var(--muted); font-size:12px; text-transform:uppercase; letter-spacing:.6px; }
        .pay-divider::before, .pay-divider::after { content:""; flex:1; height:1px; background:var(--border); }
        .btn-back { display:block; text-align:center; margin-top:14px; font-size:13px; color:var(--muted); text-decoration:none; }
        .btn-back:hover { color:var(--brand); }

        .security-note { text-align:center; font-size:12px; color:var(--muted); margin-top:16px; display:flex; align-items:center; justify-content:center; gap:6px; flex-wrap:wrap; }
    </style>
</head>
<body>
<nav>
    <a href="{{ route('login') }}" class="nav-logo">
        <div class="nav-logo-icon">
            <img src="{{ asset('images/logo.svg') }}" alt="{{ config('app.name') }}" width="30" height="30" onerror="this.style.display='none'">
        </div>
        {{ config('app.name', 'TshlBot') }}
    </a>
</nav>

<main>
    <div class="checkout-box">
        <div class="checkout-header">
            <h1>@auth{{ __('auth.register.upgrade_heading') }}@else{{ __('auth.register.checkout_heading') }}@endauth</h1>
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

            <form method="POST" action="{{ route('payment.initiate') }}" onsubmit="handlePay(this,'payBtn')">
                @csrf
                <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                <button type="submit" class="btn-pay" id="payBtn">
                    {{ __('auth.register.pay_btn', ['amount' => number_format($amount, 2)]) }}
                </button>
            </form>

            @if(config('services.paypal.client_id'))
                {{-- PayPal Smart Buttons — official 3-button stack (PayPal / Pay Later / Card). --}}
                <div class="pay-divider">{{ __('auth.register.or') }}</div>
                <div id="paypal-button-container" style="min-height:180px;margin-top:6px"></div>
                <div id="paypal-error" style="display:none;margin-top:10px;color:#dc2626;font-size:13px;text-align:center"></div>
            @elseif(config('services.paypal.payee_email'))
                {{-- Standard Payments fallback — single button redirect to hosted checkout. --}}
                <div class="pay-divider">{{ __('auth.register.or') }}</div>
                <form method="POST" action="{{ route('payment.paypal.initiate') }}" onsubmit="handlePay(this,'paypalBtn')">
                    @csrf
                    <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                    <button type="submit" class="btn-paypal" id="paypalBtn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.067 8.478c.492.315.844.755 1.048 1.316.203.562.213 1.209.03 1.943-.19.762-.517 1.44-.98 2.036-.464.596-1.036 1.089-1.717 1.478a7.213 7.213 0 0 1-2.24.826c-.815.171-1.68.257-2.593.257h-.514c-.293 0-.55.104-.771.313a1.14 1.14 0 0 0-.379.775l-.03.166-.514 3.257-.03.257c-.03.14-.099.264-.207.373a.501.501 0 0 1-.36.163H7.777a.312.312 0 0 1-.257-.115.28.28 0 0 1-.05-.259l2.293-14.514c.04-.223.148-.406.325-.549A.914.914 0 0 1 10.674 6h4.933c.874 0 1.667.104 2.379.313.712.208 1.328.502 1.848.882.52.379.936.842 1.247 1.386.31.544.518 1.13.622 1.756.081.5.09 1.04.028 1.619z"/></svg>
                        {{ __('auth.register.pay_with_paypal') }}
                    </button>
                </form>
            @endif

            @auth
                <a href="{{ route(auth()->user()->routeNamePrefix() . '.billing.index') }}" class="btn-back">
                    ← {{ __('auth.register.back_to_billing') }}
                </a>
            @else
                <a href="{{ route('login') }}" class="btn-back">← {{ __('auth.login.sign_in') }}</a>
            @endauth

            <div class="security-note">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                {{ __('auth.register.secure_payment') }}
            </div>
        </div>
    </div>
</main>

<script>
function handlePay(form, btnId) {
    document.querySelectorAll('#payBtn, #paypalBtn').forEach(function (b) { b.disabled = true; });
    const btn = document.getElementById(btnId);
    if (btn) btn.textContent = '{{ __("ui.processing") }}';
}
</script>

@if(config('services.paypal.client_id'))
<script src="https://www.paypal.com/sdk/js?client-id={{ urlencode(config('services.paypal.client_id')) }}&currency={{ urlencode(config('services.paypal.currency', 'USD')) }}&intent=capture&enable-funding=paylater,card&components=buttons"
        data-partner-attribution-id="wavadesk_saas"
        onerror="document.getElementById('paypal-error').style.display='block';document.getElementById('paypal-error').textContent='PayPal SDK failed to load.'"></script>
<script>
(function () {
    if (typeof paypal === 'undefined') return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                   || document.querySelector('input[name="_token"]')?.value;
    const errorBox = document.getElementById('paypal-error');
    function showError(msg) { errorBox.style.display = 'block'; errorBox.textContent = msg; }

    paypal.Buttons({
        style: { layout: 'vertical', shape: 'rect', label: 'paypal', height: 45 },

        createOrder: async function () {
            try {
                const res = await fetch('{{ route("payment.paypal.create-order") }}', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ tenant_id: {{ (int) $tenant->id }} }),
                });
                if (!res.ok) throw new Error('create-order HTTP ' + res.status);
                const data = await res.json();
                if (!data.id) throw new Error('create-order returned no id');
                return data.id;
            } catch (e) {
                showError('Could not start PayPal checkout: ' + e.message);
                throw e;
            }
        },

        onApprove: async function (data) {
            try {
                const res = await fetch('/payment/paypal/capture-order/' + encodeURIComponent(data.orderID), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const result = await res.json();
                if (result.success && result.redirect) {
                    window.location.href = result.redirect;
                } else {
                    showError('Payment could not be finalized. Please try again.');
                }
            } catch (e) {
                showError('Capture failed: ' + e.message);
            }
        },

        onCancel: function () {
            // Buyer closed the popup; leave the page as-is.
        },

        onError: function (err) {
            console.error('[PayPal SDK]', err);
            showError('PayPal returned an error. Please try again or use a card.');
        },
    }).render('#paypal-button-container').catch(function (e) {
        showError('Failed to render PayPal buttons.');
    });
})();
</script>
@endif
</body>
</html>
