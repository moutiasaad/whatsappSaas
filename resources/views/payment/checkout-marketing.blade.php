@php
    $isRtl    = (bool) data_get(config('locales.supported', []), app()->getLocale() . '.rtl');
    $backUrl  = $backUrl ?? url('/register/plan');
    $homeUrl  = url('/');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('ui.payment_page.title') }} — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4/fonts/remixicon.css">
    <style>
        :root {
            --brand: #10b981;
            --brand-dark: #059669;
            --text: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --bg: #f8fafc;
            --card: #ffffff;
        }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .head {
            padding: 24px 32px;
            border-bottom: 1px solid var(--border);
            background: var(--card);
        }
        .head-inner {
            max-width: 960px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            font-size: 18px;
            letter-spacing: -.02em;
        }
        .head-inner img { height: 28px; }
        main {
            flex: 1;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
        }
        .wrap {
            width: 100%;
            max-width: 480px;
        }
        h1 {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -.02em;
            margin: 0 0 8px;
        }
        .sub {
            color: var(--text-muted);
            font-size: 14px;
            margin: 0 0 28px;
        }
        .plan {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .plan-name {
            font-weight: 700;
            font-size: 16px;
            margin: 0 0 4px;
        }
        .plan-price {
            color: var(--text-muted);
            font-size: 14px;
        }
        .plan-total {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
            font-weight: 700;
        }
        .plan-total .amount {
            font-size: 24px;
            color: var(--brand-dark);
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
        }
        .card-label {
            display: block;
            text-transform: uppercase;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .08em;
            color: var(--text-muted);
            margin: 0 0 12px;
            text-align: center;
        }
        form { margin: 0; }
        form + form { margin-top: 12px; }
        .btn {
            width: 100%;
            padding: 14px 20px;
            border-radius: 10px;
            border: 1px solid transparent;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: transform .1s ease, box-shadow .1s ease;
        }
        .btn:hover { transform: translateY(-1px); }
        .btn:disabled { opacity: .6; cursor: not-allowed; }
        .btn-stripe {
            background: #635bff;
            color: #fff;
        }
        .btn-paypal {
            background: #ffc439;
            color: #003087;
        }
        .btn i { font-size: 18px; }
        .divider {
            text-align: center;
            color: var(--text-muted);
            font-size: 12px;
            margin: 12px 0;
            position: relative;
        }
        .divider::before, .divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 40%;
            height: 1px;
            background: var(--border);
        }
        .divider::before { left: 0; }
        .divider::after { right: 0; }
        .note {
            margin-top: 16px;
            padding: 12px;
            background: rgba(16, 185, 129, .08);
            border: 1px solid rgba(16, 185, 129, .25);
            border-radius: 8px;
            font-size: 12px;
            color: #065f46;
            display: flex;
            gap: 8px;
            align-items: flex-start;
        }
        .note i { font-size: 14px; flex-shrink: 0; margin-top: 1px; }
        .errbox {
            margin-bottom: 16px;
            padding: 12px 14px;
            background: rgba(239, 68, 68, .08);
            border: 1px solid rgba(239, 68, 68, .3);
            border-radius: 8px;
            font-size: 13px;
            color: #991b1b;
            display: flex;
            gap: 8px;
            align-items: flex-start;
        }
        .errbox i { font-size: 15px; flex-shrink: 0; margin-top: 1px; }
        .back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 24px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
        }
        .back:hover { color: var(--text); }
    </style>
</head>
<body>
    <header class="head">
        <div class="head-inner">
            <a href="{{ $homeUrl }}" style="color:inherit;text-decoration:none;display:flex;align-items:center;gap:10px;">
                <i class="ri-briefcase-4-line" style="color:var(--brand);font-size:20px;"></i>
                {{ config('app.name') }}
            </a>
        </div>
    </header>

    <main>
        <div class="wrap">
            <h1>{{ __('ui.payment_page.title') }}</h1>
            <p class="sub">{{ __('ui.payment_page.subtitle', ['tenant' => $tenant->name]) }}</p>

            @if($errors->any())
                <div class="errbox">
                    <i class="ri-error-warning-line"></i>
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif

            {{-- Plan summary --}}
            <div class="plan">
                <div class="plan-name">{{ $plan->name }}</div>
                <div class="plan-price">{{ __('ui.payment_page.plan_monthly') }}</div>
                <div class="plan-total">
                    <span>{{ __('ui.payment_page.total') }}</span>
                    <span class="amount">${{ number_format($amount, 2) }}</span>
                </div>
            </div>

            {{-- Payment method buttons — each POSTs to a marketing route that
                 proxies to /api/v1/billing/checkout on the core app and 302s
                 the browser to the hosted gateway (Stripe or PayPal). --}}
            <div class="card">
                <span class="card-label">{{ __('ui.payment_page.choose_method') }}</span>

                <form method="POST" action="{{ route('payment.initiate') }}" data-spin>
                    @csrf
                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                    <button type="submit" class="btn btn-stripe">
                        <i class="ri-bank-card-line"></i>
                        {{ __('ui.payment_page.pay_with_stripe', ['amount' => '$' . number_format($amount, 2)]) }}
                    </button>
                </form>

                <div class="divider">{{ __('ui.payment_page.or') }}</div>

                <form method="POST" action="{{ route('payment.paypal.initiate') }}" data-spin>
                    @csrf
                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                    <button type="submit" class="btn btn-paypal">
                        <i class="ri-paypal-fill"></i>
                        {{ __('ui.payment_page.pay_with_paypal', ['amount' => '$' . number_format($amount, 2)]) }}
                    </button>
                </form>

                <div class="note">
                    <i class="ri-shield-check-line"></i>
                    <div>{{ __('ui.payment_page.marketing_flow_note') }}</div>
                </div>
            </div>

            <a href="{{ $backUrl }}" class="back">
                <i class="ri-arrow-left-line"></i> {{ __('ui.payment_page.back_cancel') }}
            </a>
        </div>
    </main>

    <script>
        // One-shot disable-on-submit so a nervous double-click doesn't double-
        // POST. Standard shape used elsewhere in the app; the server response
        // is a 302 to the gateway, so the button stays disabled through the
        // navigation.
        document.querySelectorAll('form[data-spin]').forEach((form) => {
            form.addEventListener('submit', () => {
                form.querySelectorAll('button').forEach((b) => (b.disabled = true));
            });
        });
    </script>
</body>
</html>
