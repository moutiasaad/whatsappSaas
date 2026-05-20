<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — {{ config('app.name', 'WhatsApp SaaS') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --brand: #10b981;
            --brand-dark: #059669;
        }

        body {
            font-family: 'Outfit', system-ui, sans-serif;
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 480px;
            background: #0d1117;
            color: #f1f5f9;
        }

        /* Left panel */
        .left-panel {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 4rem;
            overflow: hidden;
        }

        .left-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 30% 50%, rgba(16,185,129,.15) 0%, transparent 60%),
                        radial-gradient(ellipse at 80% 20%, rgba(5,150,105,.08) 0%, transparent 50%);
            pointer-events: none;
        }

        .left-panel .badge {
            display: inline-flex;
            align-items: center;
            gap: .375rem;
            background: rgba(16,185,129,.1);
            border: 1px solid rgba(16,185,129,.2);
            color: var(--brand);
            font-size: .8125rem;
            font-weight: 500;
            padding: .375rem .75rem;
            border-radius: 999px;
            margin-bottom: 2rem;
            width: fit-content;
        }

        .left-panel h1 {
            font-size: 3rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 1.25rem;
        }

        .left-panel h1 span {
            color: var(--brand);
        }

        .left-panel p {
            font-size: 1.0625rem;
            color: #94a3b8;
            line-height: 1.6;
            max-width: 420px;
            margin-bottom: 3rem;
        }

        .features {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: .75rem;
            font-size: .9375rem;
            color: #cbd5e1;
        }

        .feature-icon {
            width: 2rem;
            height: 2rem;
            border-radius: .5rem;
            background: rgba(16,185,129,.12);
            border: 1px solid rgba(16,185,129,.15);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--brand);
            flex-shrink: 0;
        }

        /* Right panel */
        .right-panel {
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 3rem 2.5rem;
        }

        .login-box {
            width: 100%;
            max-width: 380px;
        }

        .login-logo {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: 2.5rem;
        }

        .logo-icon {
            width: 2.75rem;
            height: 2.75rem;
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            border-radius: .875rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            box-shadow: 0 4px 12px rgba(16,185,129,.35);
        }

        .login-logo span {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
        }

        h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: .375rem;
        }

        .sub {
            font-size: .875rem;
            color: #64748b;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.125rem;
        }

        label {
            display: block;
            font-size: .875rem;
            font-weight: 500;
            color: #334155;
            margin-bottom: .375rem;
        }

        input[type=email], input[type=password], input[type=text] {
            width: 100%;
            padding: .75rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: .625rem;
            font-size: .9375rem;
            font-family: inherit;
            color: #0f172a;
            background: #fff;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }

        input:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(16,185,129,.12);
        }

        .error-msg {
            font-size: .8125rem;
            color: #ef4444;
            margin-top: .375rem;
        }

        .row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }

        .remember {
            display: flex;
            align-items: center;
            gap: .5rem;
            font-size: .875rem;
            color: #475569;
            cursor: pointer;
        }

        .remember input { width: auto; accent-color: var(--brand); }

        .forgot {
            font-size: .875rem;
            color: var(--brand);
            text-decoration: none;
            font-weight: 500;
        }
        .forgot:hover { text-decoration: underline; }

        .btn-login {
            width: 100%;
            padding: .875rem;
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            color: #fff;
            border: none;
            border-radius: .75rem;
            font-size: 1rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all .2s;
            box-shadow: 0 4px 14px rgba(16,185,129,.3);
        }

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(16,185,129,.4);
        }

        .btn-login:active { transform: none; }

        .btn-login:disabled {
            opacity: .7;
            cursor: not-allowed;
            transform: none;
        }

        .alert-error {
            display: flex;
            align-items: center;
            gap: .625rem;
            padding: .875rem 1rem;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: .625rem;
            font-size: .875rem;
            color: #dc2626;
            margin-bottom: 1.25rem;
        }

        .divider {
            margin-top: 2rem;
            text-align: center;
            font-size: .8125rem;
            color: #94a3b8;
        }

        @media (max-width: 900px) {
            body { grid-template-columns: 1fr; }
            .left-panel { display: none; }
            .right-panel { background: #0d1117; }
            h2, .sub { color: #f1f5f9; }
            label { color: #cbd5e1; }
            .login-logo span { color: #f1f5f9; }
            input[type=email], input[type=password], input[type=text] {
                background: #1a2332; border-color: rgba(255,255,255,.1); color: #f1f5f9;
            }
            .remember { color: #94a3b8; }
        }
    </style>
</head>
<body>

    {{-- Left: Marketing Panel --}}
    <div class="left-panel">
        <div class="badge">
            <svg width="12" height="12" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
            {{ $portalBadge ?? 'WhatsApp Customer Support Platform' }}
        </div>
        <h1>Handle every conversation <span>faster, smarter</span></h1>
        <p>Unify your WhatsApp support across multiple numbers and teams. AI-powered triage, real-time collaboration, and full conversation history.</p>
        <div class="features">
            <div class="feature-item">
                <div class="feature-icon">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                AI auto-reply with Claude — reduces response time by 60%
            </div>
            <div class="feature-item">
                <div class="feature-icon">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                </div>
                Multi-team routing with real-time pool management
            </div>
            <div class="feature-item">
                <div class="feature-icon">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h.01M18 14h.01M14 18h.01M18 18h.01"/></svg>
                </div>
                Connect unlimited WhatsApp numbers with QR scan
            </div>
        </div>
    </div>

    {{-- Right: Login Form --}}
    <div class="right-panel">
        <div class="login-box">
            <div class="login-logo">
                <div class="logo-icon">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
                </div>
                <span>{{ config('app.name', 'WA Support') }}</span>
            </div>

            <h2>{{ $heading ?? 'Welcome back' }}</h2>
            <p class="sub">{{ $subheading ?? 'Sign in to your workspace' }}</p>

            @if($errors->any())
            <div class="alert-error">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ $errors->first() }}
            </div>
            @endif

            <form method="POST" action="{{ $loginAction ?? route('login') }}" onsubmit="this.querySelector('button').disabled=true">
                @csrf

                <div class="form-group">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}"
                           autocomplete="email" autofocus required
                           placeholder="you@company.com">
                    @error('email') <div class="error-msg">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           autocomplete="current-password" required
                           placeholder="••••••••">
                    @error('password') <div class="error-msg">{{ $message }}</div> @enderror
                </div>

                <div class="row">
                    <label class="remember">
                        <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                        Remember me
                    </label>
                    @if(Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="forgot">Forgot password?</a>
                    @endif
                </div>

                <button type="submit" class="btn-login">Sign in</button>
            </form>

            <div class="divider">
                Secure access · All data encrypted in transit
            </div>
        </div>
    </div>

</body>
</html>
