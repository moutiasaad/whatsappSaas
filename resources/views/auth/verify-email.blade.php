@extends('layouts.auth')

@section('title', __('auth.verify_email.title'))

@section('navlink')
    <form method="POST" action="{{ route('logout') }}" style="display:inline">
        @csrf
        <button type="submit" class="navlink" style="background:none;border:none;cursor:pointer;padding:0;font:inherit;color:inherit">
            {{ __('auth.verify_email.sign_out') }}
        </button>
    </form>
@endsection

@section('pane')
    <h2 class="formtitle">{{ __('auth.verify_email.title') }}</h2>
    <p class="formsub">{{ __('auth.verify_email.body', ['email' => auth()->user()->email]) }}</p>

    @if (session('success'))
        <div class="alert" style="background:#d1fae5;color:#065f46;padding:12px;border-radius:8px;margin-top:16px">
            {{ session('success') }}
        </div>
    @endif

    <div style="margin-top:24px;display:flex;flex-direction:column;gap:12px">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-primary" style="width:100%">
                {{ __('auth.verify_email.resend') }}
            </button>
        </form>

        <a href="{{ route(auth()->user()->routeNamePrefix() . '.profile.show') }}" class="btn btn-secondary" style="width:100%;text-align:center">
            {{ __('auth.verify_email.change_email') }}
        </a>
    </div>

    <p class="formsub" style="margin-top:16px;font-size:13px;text-align:center">
        {{ __('auth.verify_email.hint') }}
    </p>
@endsection
