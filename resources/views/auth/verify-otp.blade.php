<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ data_get(config('locales.supported', []), app()->getLocale() . '.rtl') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('auth.register.otp_page_title', ['app' => config('app.name', 'WA Support')]) }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand:        #10b981;
            --brand-dark:   #059669;
            --brand-light:  #d1fae5;
            --brand-xlight: #ecfdf5;
            --border:       #e2e8f0;
            --text:         #0f172a;
            --muted:        #64748b;
            --red:          #ef4444;
            --red-bg:       #fef2f2;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Outfit', system-ui, sans-serif; background: #f8fafc; min-height: 100vh; display: flex; flex-direction: column; }

        @keyframes fadeUp { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }
        @keyframes gradShift { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        @keyframes pulse-ring { 0%{transform:scale(.95);box-shadow:0 0 0 0 rgba(16,185,129,.5)} 70%{transform:scale(1);box-shadow:0 0 0 10px rgba(16,185,129,0)} 100%{transform:scale(.95);box-shadow:0 0 0 0 rgba(16,185,129,0)} }

        /* Nav */
        nav {
            background: rgba(255,255,255,.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 13px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky; top: 0; z-index: 50;
        }
        .nav-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; font-size: 17px; font-weight: 800; color: var(--text); }
        .nav-logo-icon { width: 32px; height: 32px; border-radius: 8px; overflow: hidden; }
        .nav-right { display: flex; align-items: center; gap: 12px; }
        .nav-link { font-size: 13px; color: var(--muted); text-decoration: none; font-weight: 500; transition: color .2s; }
        .nav-link:hover { color: var(--brand); }

        /* Lang switcher */
        .pub-lang { position: relative; }
        .pub-lang-btn { display: flex; align-items: center; gap: 5px; background: none; border: 1px solid var(--border); border-radius: 7px; padding: 5px 9px; cursor: pointer; font-size: 12px; font-weight: 600; color: var(--text); font-family: inherit; transition: background .2s; }
        .pub-lang-btn:hover { background: var(--brand-xlight); border-color: var(--brand); }
        .pub-lang-dropdown { position: absolute; top: calc(100% + 8px); right: 0; background: #fff; border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,.1); min-width: 145px; overflow: hidden; z-index: 200; display: none; }
        .pub-lang-dropdown.open { display: block; }
        .pub-lang-item { display: flex; align-items: center; gap: 8px; width: 100%; background: none; border: none; padding: 9px 13px; font-size: 13px; cursor: pointer; color: var(--text); font-family: inherit; transition: background .15s; }
        .pub-lang-item:hover { background: var(--brand-xlight); }
        .pub-lang-item.active { font-weight: 600; color: var(--brand); }
        html[dir=rtl] .pub-lang-dropdown { right: auto; left: 0; }

        /* Page */
        .page {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 480px;
            min-height: calc(100vh - 57px);
        }
        @media(max-width:860px) { .page { grid-template-columns: 1fr; } .side-panel { display: none; } }

        /* Left panel */
        .side-panel {
            background: linear-gradient(160deg, #0d1117 0%, #111827 50%, #064e3b 100%);
            background-size: 200% 200%;
            animation: gradShift 8s ease infinite;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 56px 48px; color: #fff;
            position: sticky; top: 57px; height: calc(100vh - 57px);
        }
        .email-icon-wrap {
            width: 96px; height: 96px;
            border-radius: 50%;
            background: rgba(16,185,129,.12);
            border: 2px solid rgba(16,185,129,.25);
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 28px;
            animation: pulse-ring 2.5s ease-in-out infinite;
        }
        .side-panel h2 { font-size: 26px; font-weight: 800; color: #fff; text-align: center; margin-bottom: 12px; }
        .side-panel h2 span { color: var(--brand); }
        .side-panel p { font-size: 14px; opacity: .75; line-height: 1.65; text-align: center; max-width: 340px; margin-bottom: 36px; }
        .side-steps { display: flex; flex-direction: column; gap: 14px; width: 100%; max-width: 340px; }
        .step { display: flex; align-items: center; gap: 14px; }
        .step-num {
            width: 28px; height: 28px; flex-shrink: 0;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700;
        }
        .step-num.done { background: var(--brand); color: #fff; }
        .step-num.active { background: rgba(16,185,129,.2); border: 2px solid var(--brand); color: var(--brand); }
        .step-num.todo { background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.15); color: rgba(255,255,255,.4); }
        .step-label { font-size: 13px; }
        .step-label.active { color: #fff; font-weight: 600; }
        .step-label.done { color: rgba(255,255,255,.6); }
        .step-label.todo { color: rgba(255,255,255,.35); }

        /* Right panel */
        .form-panel {
            background: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 40px;
            overflow-y: auto;
        }
        @media(max-width:560px) { .form-panel { padding: 32px 20px; } }

        .otp-box {
            width: 100%;
            max-width: 380px;
            animation: fadeUp .5s ease both;
        }

        .otp-icon {
            width: 56px; height: 56px;
            border-radius: 16px;
            background: var(--brand-xlight);
            border: 1px solid var(--brand-light);
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 20px;
        }

        h2 { font-size: 22px; font-weight: 800; color: var(--text); margin-bottom: 6px; }
        .sub {
            font-size: 14px; color: var(--muted); line-height: 1.5;
            margin-bottom: 32px;
        }
        .sub strong { color: var(--text); font-weight: 600; }

        /* Error alert */
        .alert-error {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 12px 14px;
            background: var(--red-bg); border: 1px solid #fecaca; border-radius: 10px;
            font-size: 13px; color: #dc2626; margin-bottom: 20px;
        }
        .alert-error svg { flex-shrink: 0; margin-top: 1px; }

        /* OTP digit inputs */
        .otp-label { font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 12px; }
        .otp-digits {
            display: flex; gap: 10px; justify-content: center;
            margin-bottom: 28px;
        }
        html[dir=rtl] .otp-digits { flex-direction: row-reverse; }
        .otp-digit {
            width: 52px; height: 60px;
            border: 2px solid var(--border);
            border-radius: 12px;
            text-align: center;
            font-size: 26px; font-weight: 700;
            color: var(--text);
            font-family: 'Outfit', monospace;
            outline: none;
            transition: border-color .15s, box-shadow .15s, background .15s;
            background: #fff;
            caret-color: transparent;
        }
        .otp-digit:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(16,185,129,.15);
        }
        .otp-digit.filled {
            border-color: var(--brand);
            background: var(--brand-xlight);
        }
        .otp-digit.error {
            border-color: var(--red);
            background: var(--red-bg);
        }
        @media(max-width:380px) { .otp-digit { width: 42px; height: 52px; font-size: 22px; } }

        /* Hidden aggregator */
        #otp-hidden { display: none; }

        /* Submit button */
        .btn-verify {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            color: #fff;
            border: none; border-radius: 12px;
            font-size: 15px; font-weight: 600;
            font-family: inherit; cursor: pointer;
            transition: all .2s;
            box-shadow: 0 4px 14px rgba(16,185,129,.3);
            margin-bottom: 16px;
        }
        .btn-verify:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(16,185,129,.4); }
        .btn-verify:active:not(:disabled) { transform: none; }
        .btn-verify:disabled { opacity: .6; cursor: not-allowed; }

        /* Resend */
        .resend-row {
            display: flex; align-items: center; justify-content: center;
            gap: 6px;
            font-size: 13px; color: var(--muted);
            margin-bottom: 24px;
        }
        #btn-resend {
            background: none; border: none; padding: 0;
            font-size: 13px; font-weight: 600; font-family: inherit;
            color: var(--brand); cursor: pointer; transition: opacity .2s;
        }
        #btn-resend:disabled { opacity: .5; cursor: not-allowed; color: var(--muted); }

        /* Back link */
        .back-link {
            display: flex; align-items: center; justify-content: center; gap: 6px;
            font-size: 13px; color: var(--muted); text-decoration: none;
            transition: color .2s;
        }
        .back-link:hover { color: var(--brand); }

        /* Expires note */
        .expires-note {
            display: flex; align-items: center; justify-content: center; gap: 6px;
            font-size: 12px; color: #94a3b8; margin-bottom: 20px;
        }
    </style>
</head>
<body>

    {{-- Nav --}}
    <nav>
        <a href="{{ route('landing') }}" class="nav-logo">
            <div class="nav-logo-icon">
                <img src="{{ asset('images/wavadesk-icon.svg') }}" alt="{{ config('app.name') }}" width="32" height="32">
            </div>
            {{ config('app.name', 'wavadesk') }}
        </a>
        <div class="nav-right">
            <div class="pub-lang">
                <button class="pub-lang-btn" onclick="this.nextElementSibling.classList.toggle('open')" type="button">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 010 20M12 2a15.3 15.3 0 000 20"/></svg>
                    {{ strtoupper(app()->getLocale()) }}
                    <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
                </button>
                <div class="pub-lang-dropdown">
                    @foreach(config('locales.supported', []) as $code => $meta)
                    <form method="POST" action="{{ route('locale.update') }}" style="margin:0">
                        @csrf
                        <input type="hidden" name="locale" value="{{ $code }}">
                        <input type="hidden" name="redirect" value="{{ url()->full() }}">
                        <button type="submit" class="pub-lang-item {{ app()->getLocale() === $code ? 'active' : '' }}">
                            <span>{{ $meta['flag'] ?? '' }}</span>
                            <span>{{ __('ui.languages.' . $code) }}</span>
                        </button>
                    </form>
                    @endforeach
                </div>
            </div>
        </div>
    </nav>

    <div class="page">

        {{-- Left panel --}}
        <div class="side-panel">
            <div class="email-icon-wrap">
                <svg width="44" height="44" fill="none" stroke="#10b981" stroke-width="1.5" viewBox="0 0 24 24">
                    <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <h2>Almost there! <span>Verify</span> your email</h2>
            <p>We've sent a secure 6-digit code to your inbox. Enter it to activate your workspace.</p>
            <div class="side-steps">
                <div class="step">
                    <div class="step-num done">
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
                    </div>
                    <span class="step-label done">Account details</span>
                </div>
                <div class="step">
                    <div class="step-num active">2</div>
                    <span class="step-label active">Email verification</span>
                </div>
                <div class="step">
                    <div class="step-num todo">3</div>
                    <span class="step-label todo">Workspace ready</span>
                </div>
            </div>
        </div>

        {{-- Right panel --}}
        <div class="form-panel">
            <div class="otp-box">
                <div class="otp-icon">
                    <svg width="26" height="26" fill="none" stroke="#10b981" stroke-width="1.75" viewBox="0 0 24 24">
                        <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>

                <h2>{{ __('auth.register.otp_heading') }}</h2>
                <p class="sub">
                    {{ __('auth.register.otp_subheading') }}
                    <strong>{{ $maskedEmail }}</strong>
                </p>

                @if($errors->has('general'))
                <div class="alert-error">
                    <svg width="15" height="15" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ $errors->first('general') }}
                </div>
                @endif

                <form method="POST" action="{{ route('register.otp.verify') }}" id="otp-form" novalidate>
                    @csrf

                    <div class="otp-label">{{ __('auth.register.otp_label') }}</div>

                    <div class="otp-digits" id="otp-digits">
                        @for($i = 0; $i < 6; $i++)
                        <input
                            type="text"
                            inputmode="numeric"
                            maxlength="1"
                            pattern="[0-9]"
                            autocomplete="one-time-code"
                            class="otp-digit {{ $errors->has('otp') ? 'error' : '' }}"
                            data-index="{{ $i }}"
                            aria-label="Digit {{ $i + 1 }}"
                        >
                        @endfor
                    </div>

                    @error('otp')
                    <div style="text-align:center;font-size:13px;color:var(--red);margin-top:-18px;margin-bottom:18px">{{ $message }}</div>
                    @enderror

                    <input type="hidden" name="otp" id="otp-hidden">

                    <div class="expires-note">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        {{ __('auth.register.otp_expires') }}
                    </div>

                    <button type="submit" class="btn-verify" id="btn-verify" disabled>
                        {{ __('auth.register.otp_verify_btn') }}
                    </button>
                </form>

                <div class="resend-row">
                    <span id="resend-text">{{ __('auth.register.otp_resend') }}?</span>
                    <button type="button" id="btn-resend" disabled>
                        <span id="resend-label">{{ __('auth.register.otp_resend_wait', ['s' => 60]) }}</span>
                    </button>
                </div>

                <a href="{{ route('register') }}" class="back-link">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                    {{ __('auth.register.otp_back') }}
                </a>
            </div>
        </div>

    </div>

    <script>
    (() => {
        const digits   = Array.from(document.querySelectorAll('.otp-digit'));
        const hidden   = document.getElementById('otp-hidden');
        const form     = document.getElementById('otp-form');
        const btnVerify = document.getElementById('btn-verify');
        const btnResend = document.getElementById('btn-resend');
        const resendLabel = document.getElementById('resend-label');
        const resendWaitTpl = @json(__('auth.register.otp_resend_wait', ['s' => '__S__']));
        const resendText = @json(__('auth.register.otp_resend'));

        // ── digit inputs ──────────────────────────────────
        function syncHidden() {
            const val = digits.map(d => d.value).join('');
            hidden.value = val;
            btnVerify.disabled = val.length < 6;
        }

        function focusAt(idx) {
            if (idx >= 0 && idx < digits.length) digits[idx].focus();
        }

        digits.forEach((el, i) => {
            el.addEventListener('keydown', e => {
                if (e.key === 'Backspace') {
                    if (el.value) { el.value = ''; syncHidden(); el.classList.remove('filled'); }
                    else { focusAt(i - 1); }
                    e.preventDefault();
                } else if (e.key === 'ArrowLeft') { focusAt(i - 1); e.preventDefault(); }
                else if (e.key === 'ArrowRight') { focusAt(i + 1); e.preventDefault(); }
            });

            el.addEventListener('input', e => {
                const raw = el.value.replace(/\D/g, '');
                el.value = raw.slice(-1);
                el.classList.toggle('filled', !!el.value);
                el.classList.remove('error');
                syncHidden();
                if (el.value && i < digits.length - 1) focusAt(i + 1);
            });

            el.addEventListener('paste', e => {
                e.preventDefault();
                const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                pasted.split('').forEach((ch, j) => {
                    if (digits[j]) {
                        digits[j].value = ch;
                        digits[j].classList.toggle('filled', true);
                        digits[j].classList.remove('error');
                    }
                });
                syncHidden();
                focusAt(Math.min(pasted.length, digits.length - 1));
            });
        });

        // Pre-fill if old() value exists
        const existingOtp = @json(old('otp', ''));
        if (existingOtp) {
            existingOtp.split('').slice(0, 6).forEach((ch, i) => {
                if (digits[i]) { digits[i].value = ch; digits[i].classList.add('filled'); }
            });
            syncHidden();
        }

        // Focus first empty on load
        const firstEmpty = digits.find(d => !d.value) ?? digits[0];
        firstEmpty?.focus();

        // ── submit ──────────────────────────────────────────
        form.addEventListener('submit', () => {
            btnVerify.disabled = true;
            btnVerify.textContent = '…';
        });

        // ── resend countdown ────────────────────────────────
        let countdown = 60;

        function tick() {
            if (countdown <= 0) {
                btnResend.disabled = false;
                resendLabel.textContent = resendText;
                return;
            }
            btnResend.disabled = true;
            resendLabel.textContent = resendWaitTpl.replace('__S__', countdown);
            countdown--;
            setTimeout(tick, 1000);
        }
        tick();

        btnResend.addEventListener('click', async () => {
            btnResend.disabled = true;
            resendLabel.textContent = '…';
            try {
                const res = await fetch('{{ route('register.otp.resend') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                            ?? document.querySelector('input[name="_token"]')?.value ?? '',
                    },
                });
                const data = await res.json();
                if (data.ok) {
                    countdown = 60;
                    tick();
                    digits.forEach(d => { d.value = ''; d.classList.remove('filled', 'error'); });
                    hidden.value = '';
                    btnVerify.disabled = true;
                    focusAt(0);
                } else {
                    resendLabel.textContent = data.error ?? 'Error';
                    setTimeout(() => { countdown = 0; tick(); }, 2000);
                }
            } catch {
                resendLabel.textContent = 'Error';
                setTimeout(() => { countdown = 0; tick(); }, 2000);
            }
        });

        // Close lang dropdown on outside click
        document.addEventListener('click', e => {
            document.querySelectorAll('.pub-lang-dropdown.open').forEach(d => {
                if (!d.parentElement.contains(e.target)) d.classList.remove('open');
            });
        });
    })();
    </script>
</body>
</html>
