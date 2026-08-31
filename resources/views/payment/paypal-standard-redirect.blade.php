<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('auth.register.redirecting_to_paypal', ['default' => 'Redirecting to PayPal…']) }}</title>
    <meta name="robots" content="noindex,nofollow">
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .box { text-align: center; padding: 2rem; }
        .spin { width: 42px; height: 42px; border: 4px solid #cbd5e1; border-top-color: #003087; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 1rem; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .hint { color: #64748b; font-size: 14px; margin-top: .5rem; }
        button { margin-top: 1rem; padding: .5rem 1rem; border: 1px solid #94a3b8; background: #fff; border-radius: 6px; cursor: pointer; }
    </style>
</head>
<body>
    <form id="pp-form" method="POST" action="{{ $action }}" accept-charset="utf-8">
        @foreach($params as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <div class="box">
            <div class="spin"></div>
            <div>{{ __('auth.register.redirecting_to_paypal', ['default' => 'Redirecting to PayPal…']) }}</div>
            <div class="hint">{{ __('auth.register.redirecting_hint', ['default' => 'If you are not redirected automatically, click the button below.']) }}</div>
            <noscript>
                <button type="submit">{{ __('auth.register.continue_to_paypal', ['default' => 'Continue to PayPal']) }}</button>
            </noscript>
        </div>
    </form>
    <script>document.getElementById('pp-form').submit();</script>
</body>
</html>
