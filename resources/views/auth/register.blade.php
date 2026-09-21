@extends('layouts.auth')

@section('title', __('auth.register.form_title'))

@section('pane_class', 'wide')

@section('navlink')
    <a class="navlink" href="{{ route('login') }}">{{ __('auth.register.already_have_account') }} <b>{{ __('auth.login.sign_in') }}</b></a>
@endsection

@section('pane')
<div class="wizsteps" aria-label="{{ __('auth.register.steps_label') }}">
    <span class="st on"><i>1</i>{{ __('auth.register.step_account') }}</span>
    <span class="bar"></span>
    <span class="st"><i>2</i>{{ __('auth.register.step_plan') }}</span>
</div>

<div class="trialbadge">
    <div class="ic"><svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z" fill="#fff"/></svg></div>
    <div class="tx">
        <b>{{ __('auth.register.trial_badge_title', ['days' => $trialDays]) }}</b>
        <span>{{ __('auth.register.trial_badge_sub') }}</span>
    </div>
</div>

<h2 class="formtitle">{{ __('auth.register.form_title') }}</h2>
<p class="formsub">{{ __('auth.register.form_sub') }}</p>

@if($errors->has('general'))
<div class="alert">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9.5" stroke="currentColor" stroke-width="1.8"/><path d="M12 7.5v5.5M12 16.4h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    {{ $errors->first('general') }}
</div>
@endif

<form method="POST" action="{{ route('register.store') }}" id="regForm" data-spin novalidate style="margin-top:24px">
    @csrf

    {{-- The plan is chosen on step 2, once the workspace exists. A card picked
         on the pricing page rides along so it arrives pre-selected there. --}}
    @if($intendedPlan)
    <input type="hidden" name="plan_id" value="{{ $intendedPlan->id }}">
    @endif

    <div class="field">
        <label for="email">{{ __('auth.register.email') }} <span class="req">*</span></label>
        <div class="ctrl" data-wrap>
            <input id="email" name="email" type="email" value="{{ old('email', request('email')) }}"
                   class="{{ $errors->has('email') ? 'is-error' : '' }}"
                   placeholder="{{ __('auth.login.placeholder_email') }}" autocomplete="email" required>
            <svg class="tick" width="17" height="17" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" fill="#d6efed"/><path d="M17 9l-6 6-3-3" stroke="#0f7e7a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        @error('email')
            <div class="hint err">{{ $message }}</div>
        @else
            <div class="hint" id="email-hint">{{ __('auth.register.email_hint') }}</div>
        @enderror
    </div>

    <div class="field">
        <label for="company_name">{{ __('auth.register.company_name') }} <span class="req">*</span></label>
        <div class="ctrl" data-wrap>
            <input id="company_name" name="company_name" type="text" value="{{ old('company_name') }}"
                   class="{{ $errors->has('company_name') ? 'is-error' : '' }}"
                   placeholder="{{ __('auth.register.company_name_placeholder') }}" autocomplete="organization" required>
            <svg class="tick" width="17" height="17" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" fill="#d6efed"/><path d="M17 9l-6 6-3-3" stroke="#0f7e7a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        @error('company_name')<div class="hint err">{{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label for="password">{{ __('auth.register.password') }} <span class="req">*</span></label>
        <div class="ctrl has-icon" data-wrap>
            <input id="password" name="password" type="password"
                   class="{{ $errors->has('password') ? 'is-error' : '' }}"
                   placeholder="{{ __('auth.register.password_placeholder') }}" autocomplete="new-password" required>
            <button type="button" class="eye" data-eye aria-label="{{ __('auth.login.toggle_password') }}"></button>
        </div>
        <div class="meter" id="pwMeter"><i></i><i></i><i></i><i></i></div>
        <div class="meterlbl" id="pwLabel"></div>
        @error('password')<div class="hint err">{{ $message }}</div>@enderror
    </div>

    <button class="cta" type="submit"><span class="sp"></span><span class="lbl">{{ __('auth.register.submit_btn') }}</span></button>
    <div class="ctahint">{{ __('auth.register.submit_hint', ['days' => $trialDays]) }}</div>

    <div class="reassure">
        <span><svg width="13" height="13" viewBox="0 0 24 24" fill="none"><rect x="2.5" y="5" width="19" height="14" rx="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M2.5 10h19" stroke="currentColor" stroke-width="1.8"/></svg>{{ __('landing.trust_no_card') }}</span>
        <span><svg width="13" height="13" viewBox="0 0 24 24" fill="none"><rect x="4" y="10" width="16" height="11" rx="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M8 10V7a4 4 0 118 0v3" stroke="currentColor" stroke-width="1.8"/></svg>{{ __('landing.trust_cancel') }}</span>
    </div>

    <div class="legal">
        {!! __('auth.register.legal', [
            'terms'   => '<a href="' . route('legal.terms') . '">' . e(__('landing.footer_terms')) . '</a>',
            'privacy' => '<a href="' . route('legal.privacy') . '">' . e(__('landing.footer_privacy')) . '</a>',
        ]) !!}
    </div>
</form>

<div class="swap">{{ __('auth.register.already_have_account') }} <a href="{{ route('login') }}"><b>{{ __('auth.login.sign_in') }}</b></a></div>
@endsection

@push('scripts')
@php
    $pwLabels = [
        1 => ['t' => __('auth.register.pw_weak'),   'c' => '#ef4444'],
        2 => ['t' => __('auth.register.pw_fair'),   'c' => '#f59e0b'],
        3 => ['t' => __('auth.register.pw_good'),   'c' => '#15b6a8'],
        4 => ['t' => __('auth.register.pw_strong'), 'c' => '#0f7e7a'],
    ];
@endphp
<script>
(() => {
    const pw      = document.getElementById('password');
    const meter   = document.getElementById('pwMeter');
    const label   = document.getElementById('pwLabel');
    const email   = document.getElementById('email');
    const company = document.getElementById('company_name');
    const hint    = document.getElementById('email-hint');

    const LABELS       = @json($pwLabels);
    const HINT_DEFAULT = @json(__('auth.register.email_hint'));
    const HINT_NAMED   = @json(__('auth.register.email_hint_named', ['name' => '__N__']));
    const ERR = {
        email_required:    @json(__('auth.register.err_email_required')),
        email_invalid:     @json(__('auth.register.err_email_invalid')),
        company_required:  @json(__('auth.register.err_company_required')),
        password_required: @json(__('auth.register.err_password_required')),
        password_short:    @json(__('auth.register.err_password_short')),
    };

    // ── Client-side pre-submit validation ─────────────────────────────
    // The form has `novalidate` so browsers don't show their own tooltip
    // (Chrome's box floats away from RTL/mobile layouts). We enforce the
    // same rules the server does (RegisterController::store) inline
    // instead: mark the input .is-error, render a .hint.err message
    // under it, and block the submit until every field passes. Errors
    // clear the moment the user starts typing again.
    const form = document.getElementById('regForm');
    if (form) {
        const setError = (input, msg) => {
            input.classList.add('is-error');
            const field = input.closest('.field');
            if (!field) return;
            let hint = field.querySelector('.hint.err');
            if (!hint) {
                hint = document.createElement('div');
                hint.className = 'hint err';
                field.appendChild(hint);
            }
            hint.textContent = msg;
        };
        const clearError = (input) => {
            input.classList.remove('is-error');
            const field = input.closest('.field');
            const hint = field?.querySelector('.hint.err');
            // Keep the default helper hint (id="email-hint") in place; only
            // remove hints we injected on submit.
            if (hint && !hint.id) hint.remove();
        };
        // Clear per-field errors as soon as the user starts typing/blurring.
        ['email','company_name','password'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', () => clearError(el));
        });

        form.addEventListener('submit', (ev) => {
            let firstBad = null;
            const emailV   = (email?.value || '').trim();
            const companyV = (company?.value || '').trim();
            const pwV      = (pw?.value || '');

            if (!emailV) {
                setError(email, ERR.email_required);
                firstBad = firstBad || email;
            } else if (!/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i.test(emailV)) {
                setError(email, ERR.email_invalid);
                firstBad = firstBad || email;
            }
            if (!companyV) {
                setError(company, ERR.company_required);
                firstBad = firstBad || company;
            }
            if (!pwV) {
                setError(pw, ERR.password_required);
                firstBad = firstBad || pw;
            } else if (pwV.length < 8) {
                setError(pw, ERR.password_short);
                firstBad = firstBad || pw;
            }

            if (firstBad) {
                ev.preventDefault();
                ev.stopPropagation();
                // data-spin adds a submitting spinner via layouts/auth.blade.
                // Remove it so the button isn't stuck in a "submitting" state.
                form.classList.remove('is-submitting');
                firstBad.focus({ preventScroll: false });
                firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }

    function score(v) {
        let s = 0;
        if (v.length >= 8) s++;
        if (v.length >= 12) s++;
        if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
        if (/[0-9\W]/.test(v)) s++;
        return Math.min(s, 4);
    }

    if (pw) pw.addEventListener('input', () => {
        const s = pw.value ? score(pw.value) : 0;
        meter.className = 'meter' + (s ? ' s' + s : '');
        const l = LABELS[s];
        label.textContent = l ? l.t : '';
        label.style.color = l ? l.c : 'transparent';
    });

    // suggest a workspace name from the email domain while the field is untouched
    let companyTouched = company && company.value.trim().length > 0;
    if (company) company.addEventListener('input', () => { companyTouched = true; });

    if (email) {
        const sync = () => {
            const ok = /^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i.test(email.value);
            const domain = email.value.split('@')[1];
            if (!ok || !domain) { if (hint) hint.textContent = HINT_DEFAULT; return; }
            const raw  = domain.split('.')[0].replace(/[-_]/g, ' ');
            const name = raw.charAt(0).toUpperCase() + raw.slice(1);
            if (hint) hint.textContent = HINT_NAMED.replace('__N__', name);
            if (!companyTouched && company) {
                company.value = name;
                company.closest('[data-wrap]').classList.add('valid');
            }
        };
        email.addEventListener('input', sync);
        if (email.value) sync();
    }
})();
</script>
@endpush
