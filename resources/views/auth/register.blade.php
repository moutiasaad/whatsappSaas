<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ data_get(config('locales.supported', []), app()->getLocale() . '.rtl') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('auth.register.page_title', ['app' => config('app.name', 'WA Support')]) }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
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
        body { font-family: 'Outfit', system-ui, sans-serif; background: #f8fafc; min-height: 100vh; }

        @keyframes fadeUp    { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
        @keyframes gradShift { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }

        /* ── nav ── */
        nav { background: rgba(255,255,255,.92); backdrop-filter: blur(12px); border-bottom: 1px solid var(--border); padding: 13px 28px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 50; }
        .nav-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; font-size: 17px; font-weight: 800; color: var(--text); }
        .nav-logo-icon { width: 32px; height: 32px; border-radius: 8px; overflow: hidden; display: flex; align-items: center; justify-content: center; }
        .nav-right { display: flex; align-items: center; gap: 12px; }
        .nav-link { font-size: 13px; color: var(--muted); text-decoration: none; font-weight: 500; transition: color .2s; }
        .nav-link:hover { color: var(--brand); }

        /* lang switcher */
        .pub-lang { position: relative; }
        .pub-lang-btn { display: flex; align-items: center; gap: 5px; background: none; border: 1px solid var(--border); border-radius: 7px; padding: 5px 9px; cursor: pointer; font-size: 12px; font-weight: 600; color: var(--text); font-family: inherit; transition: background .2s; }
        .pub-lang-btn:hover { background: var(--brand-xlight); border-color: var(--brand); }
        .pub-lang-dropdown { position: absolute; top: calc(100% + 8px); right: 0; background: #fff; border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,.1); min-width: 145px; overflow: hidden; z-index: 200; display: none; }
        .pub-lang-dropdown.open { display: block; }
        .pub-lang-item { display: flex; align-items: center; gap: 8px; width: 100%; background: none; border: none; padding: 9px 13px; font-size: 13px; cursor: pointer; color: var(--text); font-family: inherit; transition: background .15s; }
        .pub-lang-item:hover { background: var(--brand-xlight); }
        .pub-lang-item.active { font-weight: 600; color: var(--brand); }
        html[dir=rtl] .pub-lang-dropdown { right: auto; left: 0; }

        /* ── layout ── */
        .page { display: grid; grid-template-columns: 1fr 460px; min-height: calc(100vh - 57px); }
        @media(max-width:860px) { .page { grid-template-columns: 1fr; } .side-panel { display: none; } }

        /* ── left panel ── */
        .side-panel {
            background: linear-gradient(160deg, #0d1117 0%, #111827 50%, #064e3b 100%);
            background-size: 200% 200%;
            animation: gradShift 8s ease infinite;
            display: flex; flex-direction: column; justify-content: center;
            padding: 56px 48px; color: #fff;
            position: sticky; top: 57px; height: calc(100vh - 57px);
        }
        .side-badge { display: inline-flex; align-items: center; gap: 8px; background: rgba(16,185,129,.12); border: 1px solid rgba(16,185,129,.2); color: var(--brand); font-size: 12px; font-weight: 600; padding: 5px 12px; border-radius: 100px; margin-bottom: 24px; }
        .side-panel h2 { font-size: 30px; font-weight: 800; line-height: 1.2; margin-bottom: 14px; }
        .side-panel h2 span { color: var(--brand); }
        .side-panel p  { font-size: 15px; opacity: .8; line-height: 1.65; margin-bottom: 40px; }
        .side-feats { display: flex; flex-direction: column; gap: 14px; }
        .side-feat { display: flex; align-items: flex-start; gap: 14px; background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.08); border-radius: 14px; padding: 16px 18px; }
        .side-feat-icon { width: 36px; height: 36px; border-radius: 9px; background: rgba(16,185,129,.15); border: 1px solid rgba(16,185,129,.2); display: flex; align-items: center; justify-content: center; color: var(--brand); flex-shrink: 0; }
        .side-feat-title { font-size: 14px; font-weight: 700; margin-bottom: 3px; }
        .side-feat-desc  { font-size: 12px; opacity: .7; }

        /* ── form panel ── */
        .form-panel { background: #fff; padding: 40px; overflow-y: auto; display: flex; flex-direction: column; }
        @media(max-width:560px) { .form-panel { padding: 24px 18px; } }

        .form-header { margin-bottom: 28px; animation: fadeUp .5s ease both; }
        .form-title  { font-size: 24px; font-weight: 800; color: var(--text); margin-bottom: 6px; }
        .form-sub    { font-size: 14px; color: var(--muted); }

        /* plan selector */
        .plan-selector { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 28px; }
        @media(max-width:480px) { .plan-selector { grid-template-columns: 1fr; } }
        .plan-opt    { display: none; }
        .plan-lbl {
            display: flex; flex-direction: column; align-items: center; gap: 5px;
            padding: 14px 10px; border: 2px solid var(--border); border-radius: 14px;
            cursor: pointer; transition: border-color .2s, background .2s; text-align: center; position: relative;
        }
        .plan-lbl:hover { border-color: var(--brand); background: var(--brand-xlight); }
        .plan-opt:checked + .plan-lbl { border-color: var(--brand); background: var(--brand-xlight); }
        .plan-lbl .check {
            position: absolute; top: 8px; right: 8px; width: 18px; height: 18px;
            border-radius: 50%; background: var(--brand); color: #fff; font-size: 10px;
            display: none; align-items: center; justify-content: center;
        }
        .plan-opt:checked + .plan-lbl .check { display: flex; }
        .plan-lbl .p-name  { font-size: 13px; font-weight: 700; color: var(--text); }
        .plan-lbl .p-price { font-size: 12px; color: var(--muted); }
        .plan-lbl .pop-tag {
            position: absolute; top: -9px; left: 50%; transform: translateX(-50%);
            background: var(--brand); color: #fff; font-size: 10px; font-weight: 700;
            padding: 2px 8px; border-radius: 100px; white-space: nowrap;
        }

        /* form fields */
        .form-section { margin-bottom: 28px; }
        .form-section-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); margin-bottom: 14px; padding-bottom: 8px; border-bottom: 1px solid var(--border); }
        .form-group { margin-bottom: 16px; }
        .form-row   { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        @media(max-width:480px) { .form-row { grid-template-columns: 1fr; } }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px; }
        .form-label .req { color: var(--brand); margin-left: 2px; }
        .form-input {
            width: 100%; padding: 11px 14px; border: 1.5px solid var(--border);
            border-radius: 10px; font-size: 14px; color: var(--text); font-family: 'Outfit', sans-serif;
            background: #fff; outline: none; transition: border-color .2s, box-shadow .2s;
        }
        .form-input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(16,185,129,.1); }
        .form-input.is-error { border-color: var(--red); }
        .field-error { font-size: 12px; color: var(--red); margin-top: 5px; }

        /* password strength */
        .pw-strength { margin-top: 8px; }
        .pw-bar { height: 3px; border-radius: 2px; background: #e2e8f0; overflow: hidden; }
        .pw-fill { height: 100%; border-radius: 2px; width: 0; transition: width .3s, background .3s; }
        .pw-label { font-size: 11px; color: var(--muted); margin-top: 4px; }

        /* error alert */
        .alert-error { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; background: var(--red-bg); border: 1px solid #fecaca; border-radius: 10px; font-size: 13px; color: var(--red); margin-bottom: 20px; }

        /* submit */
        .btn-submit { width: 100%; padding: 14px; background: linear-gradient(135deg, var(--brand), var(--brand-dark)); color: #fff; border: none; border-radius: 11px; font-size: 16px; font-weight: 700; font-family: 'Outfit', sans-serif; cursor: pointer; transition: all .2s; box-shadow: 0 4px 14px rgba(16,185,129,.3); }
        .btn-submit:hover { box-shadow: 0 6px 20px rgba(16,185,129,.45); transform: translateY(-1px); }
        .btn-submit:active { transform: none; }
        .btn-submit:disabled { opacity: .7; cursor: not-allowed; transform: none; }

        .form-footer { text-align: center; font-size: 13px; color: var(--muted); margin-top: 20px; }
        .form-footer a { color: var(--brand); text-decoration: none; font-weight: 600; }
        .form-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>

{{-- nav --}}
<nav>
    <a href="{{ route('landing') }}" class="nav-logo">
        <div class="nav-logo-icon">
            <img src="{{ asset('images/wavadesk-icon.svg') }}" alt="wavadesk" width="32" height="32">
        </div>
        wavadesk
    </a>
    <div class="nav-right">
        {{-- lang --}}
        <div class="pub-lang">
            <button class="pub-lang-btn" id="langBtn" type="button">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>
                {{ strtoupper(app()->getLocale()) }}
            </button>
            <div class="pub-lang-dropdown" id="langDropdown">
                @foreach(config('locales.supported', []) as $code => $meta)
                <form method="POST" action="{{ route('locale.update') }}" style="margin:0">
                    @csrf
                    <input type="hidden" name="locale" value="{{ $code }}">
                    <input type="hidden" name="redirect" value="{{ url()->full() }}">
                    <button type="submit" class="pub-lang-item {{ app()->getLocale() === $code ? 'active' : '' }}">{{ $meta['native'] ?? $code }}</button>
                </form>
                @endforeach
            </div>
        </div>
        <a href="{{ route('login') }}" class="nav-link">{{ __('auth.register.already_have_account') }}</a>
    </div>
</nav>

<div class="page">
    {{-- ── left: marketing ── --}}
    <div class="side-panel">
        <div class="side-badge">
            <svg width="11" height="11" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
            {{ __('auth.register.badge') }}
        </div>
        <h2>{{ __('auth.register.side_title') }} <span>{{ __('auth.register.side_title_accent') }}</span></h2>
        <p>{{ __('auth.register.side_desc') }}</p>
        <div class="side-feats">
            <div class="side-feat">
                <div class="side-feat-icon">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <div>
                    <div class="side-feat-title">{{ __('auth.register.feat_1_title') }}</div>
                    <div class="side-feat-desc">{{ __('auth.register.feat_1_desc') }}</div>
                </div>
            </div>
            <div class="side-feat">
                <div class="side-feat-icon">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                </div>
                <div>
                    <div class="side-feat-title">{{ __('auth.register.feat_2_title') }}</div>
                    <div class="side-feat-desc">{{ __('auth.register.feat_2_desc') }}</div>
                </div>
            </div>
            <div class="side-feat">
                <div class="side-feat-icon">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h.01M18 14h.01M14 18h.01M18 18h.01"/></svg>
                </div>
                <div>
                    <div class="side-feat-title">{{ __('auth.register.feat_3_title') }}</div>
                    <div class="side-feat-desc">{{ __('auth.register.feat_3_desc') }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── right: form ── --}}
    <div class="form-panel">
        <div class="form-header">
            <div class="form-title">{{ __('auth.register.form_title') }}</div>
            <div class="form-sub">{{ __('auth.register.form_sub') }}</div>
        </div>

        @if($errors->has('general'))
        <div class="alert-error">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ $errors->first('general') }}
        </div>
        @endif

        <form method="POST" action="{{ route('register.store') }}" id="regForm" onsubmit="handleSubmit(this)">
            @csrf

            {{-- plan selector --}}
            @if($plans->count())
            <div class="form-section">
                <div class="form-section-title">{{ __('auth.register.choose_plan') }}</div>
                <div class="plan-selector">
                    @foreach($plans as $i => $plan)
                    @php $isFree = !$plan->price_monthly || (float)$plan->price_monthly === 0.0; @endphp
                    <input type="radio" name="plan_id" id="plan_{{ $plan->id }}" value="{{ $plan->id }}"
                           class="plan-opt"
                           {{ (old('plan_id', $selectedPlan?->id) == $plan->id) ? 'checked' : '' }}>
                    <label for="plan_{{ $plan->id }}" class="plan-lbl">
                        @if($i === 1 && $plans->count() >= 2)<div class="pop-tag">{{ __('landing.popular_badge') }}</div>@endif
                        <div class="check">✓</div>
                        <div class="p-name">{{ $plan->name }}</div>
                        <div class="p-price">
                            {{ $isFree ? __('landing.plan_free_label') : '$' . number_format((float)$plan->price_monthly, 0) . __('landing.plan_per_month') }}
                        </div>
                    </label>
                    @endforeach
                </div>
                @error('plan_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            @endif

            {{-- workspace --}}
            <div class="form-section">
                <div class="form-section-title">{{ __('auth.register.section_workspace') }}</div>
                <div class="form-group">
                    <label class="form-label" for="company_name">{{ __('auth.register.company_name') }} <span class="req">*</span></label>
                    <input type="text" id="company_name" name="company_name" class="form-input {{ $errors->has('company_name') ? 'is-error' : '' }}"
                           value="{{ old('company_name') }}" placeholder="{{ __('auth.register.company_name_placeholder') }}" required>
                    @error('company_name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- admin account --}}
            <div class="form-section">
                <div class="form-section-title">{{ __('auth.register.section_admin') }}</div>
                <div class="form-group">
                    <label class="form-label" for="admin_name">{{ __('auth.register.admin_name') }} <span class="req">*</span></label>
                    <input type="text" id="admin_name" name="admin_name" class="form-input {{ $errors->has('admin_name') ? 'is-error' : '' }}"
                           value="{{ old('admin_name') }}" placeholder="{{ __('auth.register.admin_name_placeholder') }}" required>
                    @error('admin_name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="email">{{ __('auth.register.email') }} <span class="req">*</span></label>
                    <input type="email" id="email" name="email" class="form-input {{ $errors->has('email') ? 'is-error' : '' }}"
                           value="{{ old('email') }}" placeholder="{{ __('auth.login.placeholder_email') }}" required autocomplete="email">
                    @error('email')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="password">{{ __('auth.register.password') }} <span class="req">*</span></label>
                        <input type="password" id="password" name="password" class="form-input {{ $errors->has('password') ? 'is-error' : '' }}"
                               placeholder="••••••••" required autocomplete="new-password" oninput="checkStrength(this.value)">
                        <div class="pw-strength">
                            <div class="pw-bar"><div class="pw-fill" id="pwFill"></div></div>
                            <div class="pw-label" id="pwLabel"></div>
                        </div>
                        @error('password')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="password_confirmation">{{ __('auth.register.confirm_password') }} <span class="req">*</span></label>
                        <input type="password" id="password_confirmation" name="password_confirmation" class="form-input"
                               placeholder="••••••••" required autocomplete="new-password">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                {{ __('auth.register.submit_btn') }}
            </button>
        </form>

        <p class="form-footer">
            {{ __('auth.register.already_have_account') }} <a href="{{ route('login') }}">{{ __('auth.login.sign_in') }}</a>
        </p>
    </div>
</div>

<script>
// lang dropdown
const langBtn = document.getElementById('langBtn');
const langDD  = document.getElementById('langDropdown');
if (langBtn) {
    langBtn.addEventListener('click', e => { e.stopPropagation(); langDD.classList.toggle('open'); });
    document.addEventListener('click', () => langDD.classList.remove('open'));
}

// password strength
function checkStrength(val) {
    const fill  = document.getElementById('pwFill');
    const label = document.getElementById('pwLabel');
    if (!fill) return;
    let score = 0;
    if (val.length >= 8)  score++;
    if (val.length >= 12) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const levels = [
        {w:'0%',   bg:'#e2e8f0', lbl:''},
        {w:'20%',  bg:'#ef4444', lbl:'{{ __("auth.register.pw_weak") }}'},
        {w:'40%',  bg:'#f97316', lbl:'{{ __("auth.register.pw_fair") }}'},
        {w:'65%',  bg:'#eab308', lbl:'{{ __("auth.register.pw_good") }}'},
        {w:'82%',  bg:'#22c55e', lbl:'{{ __("auth.register.pw_strong") }}'},
        {w:'100%', bg:'#10b981', lbl:'{{ __("auth.register.pw_very_strong") }}'},
    ];
    const l = levels[Math.min(score, 5)];
    fill.style.width = l.w; fill.style.background = l.bg;
    label.textContent = l.lbl; label.style.color = l.bg;
}

// submit handler
function handleSubmit(form) {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = '{{ __("ui.processing") }}';
}
</script>
</body>
</html>
