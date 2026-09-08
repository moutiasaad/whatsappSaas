@extends('layouts.auth')

@section('title', $pageTitle ?? __('auth.login.sign_in'))

@section('navlink')
    @if(Route::has('register'))
        <a class="navlink" href="{{ route('register') }}">{{ __('auth.login.no_account') }} <b>{{ __('auth.login.sign_up_here') }}</b></a>
    @endif
@endsection

@section('pane')
    @isset($portalBadge)
    <div class="trialbadge">
        <div class="ic">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><rect x="4" y="10" width="16" height="11" rx="2.5" stroke="#fff" stroke-width="1.9"/><path d="M8 10V7a4 4 0 118 0v3" stroke="#fff" stroke-width="1.9"/></svg>
        </div>
        <div class="tx">
            <b>{{ $portalBadge }}</b>
            <span>{{ $subheading ?? __('auth.login.sign_in_workspace') }}</span>
        </div>
    </div>
    @endisset

    <h2 class="formtitle">{{ $heading ?? __('auth.login.welcome_back') }}</h2>
    <p class="formsub">{{ $subheading ?? __('auth.login.sign_in_workspace') }}</p>

    @if($errors->any())
    <div class="alert">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9.5" stroke="currentColor" stroke-width="1.8"/><path d="M12 7.5v5.5M12 16.4h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        {{ $errors->first() }}
    </div>
    @endif

    <form method="POST" action="{{ $loginAction ?? route('login') }}" data-spin novalidate style="margin-top:24px">
        @csrf

        <div class="field">
            <label for="email">{{ __('auth.login.email_address') }}</label>
            <div class="ctrl" data-wrap>
                <input id="email" name="email" type="email" value="{{ old('email') }}"
                       class="{{ $errors->has('email') ? 'is-error' : '' }}"
                       placeholder="{{ __('auth.login.placeholder_email') }}" autocomplete="email" autofocus required>
                <svg class="tick" width="17" height="17" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" fill="#d6efed"/><path d="M17 9l-6 6-3-3" stroke="#0f7e7a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            @error('email')<div class="hint err">{{ $message }}</div>@enderror
        </div>

        <div class="field">
            <div class="rowline">
                <label for="password" style="margin:0">{{ __('auth.login.password') }}</label>
                @if(Route::has('password.request'))
                    <a href="{{ route('password.request') }}" style="font-size:13px">{{ __('auth.login.forgot_password') }}</a>
                @endif
            </div>
            <div class="ctrl has-icon" data-wrap>
                <input id="password" name="password" type="password"
                       class="{{ $errors->has('password') ? 'is-error' : '' }}"
                       placeholder="{{ __('auth.login.placeholder_password') }}" autocomplete="current-password" required>
                <button type="button" class="eye" data-eye aria-label="{{ __('auth.login.toggle_password') }}"></button>
            </div>
            @error('password')<div class="hint err">{{ $message }}</div>@enderror
        </div>

        <label class="check" style="margin-top:4px">
            <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
            {{ __('auth.login.remember_me') }}
        </label>

        <button class="cta" type="submit"><span class="sp"></span><span class="lbl">{{ __('auth.login.sign_in') }}</span></button>

        <div class="reassure">
            <span>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><rect x="4" y="10" width="16" height="11" rx="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M8 10V7a4 4 0 118 0v3" stroke="currentColor" stroke-width="1.8"/></svg>
                {{ __('auth.login.secure_access') }}
            </span>
        </div>
    </form>

    @if(Route::has('register'))
    <div class="swap">{{ __('auth.login.no_account') }} <a href="{{ route('register') }}"><b>{{ __('auth.shell.start_free_trial') }}</b></a></div>
    @endif
@endsection
