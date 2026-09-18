@extends('layouts.auth')

@section('title', __('auth.register.plan_title'))

@section('pane_class', 'plans-wide')

@push('head')
<style>
.impersonate-return{display:flex;align-items:center;gap:10px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:11px;padding:11px 14px;font-size:13px;line-height:1.4;margin-bottom:14px}
.impersonate-return svg{flex-shrink:0}
.impersonate-return span{flex:1}
.impersonate-return .return-btn{flex-shrink:0;background:#92400e;color:#fff;font-weight:600;font-size:12.5px;padding:6px 12px;border-radius:8px;text-decoration:none;transition:.15s}
.impersonate-return .return-btn:hover{background:#78350f;color:#fff}
</style>
@endpush

@section('proof')
    <span class="eyebrow">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        {{ __('auth.register.plan_side_pill') }}
    </span>

    <h1>{!! __('auth.register.plan_side_title') !!}</h1>
    <p class="lede">{{ __('auth.register.plan_side_desc') }}</p>

    <div class="flow">
        <div class="fs done"><span class="no">&#10003;</span>{{ __('auth.register.step_account') }}</div>
        <div class="fs active"><span class="no">2</span>{{ __('auth.register.step_plan') }}</div>
        <div class="fs"><span class="no">3</span>{{ __('auth.register.step_ready') }}</div>
    </div>

    <div class="proofline">
        @foreach(['stat_1','stat_2','stat_3'] as $k)
        <div class="stat"><svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M20 6 9 17l-5-5" stroke="#15b6a8" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('auth.shell.' . $k, ['days' => config('app.trial_days', 7)]) }}</div>
        @endforeach
    </div>
@endsection

@section('pane')
@php
    // Everything the CTA needs to change label without a round trip: which
    // plans start a trial, and what each one costs when they do not.
    $meta = [];
    foreach ($plans as $plan) {
        $isFree    = !$plan->price_monthly || (float) $plan->price_monthly === 0.0;
        $trialDays = $plan->trialDays();
        $meta[$plan->id] = [
            'trial' => $isFree || $trialDays > 0,
            'days'  => $trialDays,
            'price' => $isFree ? __('landing.plan_free_label') : '$' . number_format((float) $plan->price_monthly, 0),
            'name'  => $plan->name,
        ];
    }
@endphp

<div class="wizsteps" aria-label="{{ __('auth.register.steps_label') }}">
    <span class="st done"><i>&#10003;</i>{{ __('auth.register.step_account') }}</span>
    <span class="bar"></span>
    <span class="st on"><i>2</i>{{ __('auth.register.step_plan') }}</span>
</div>

@if(session('impersonating'))
{{-- Super-admin landed here because the impersonated tenant never picked a
     plan. The layouts.admin impersonation banner isn't rendered on the
     auth layout, so without this escape the picker is a dead-end — clicking
     "Sign out" would kill the super-admin's own session too. --}}
<div class="impersonate-return" role="status">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm0 5v6m0 3.5h.01" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
    <span>{{ __('auth.register.impersonating_notice', ['name' => $tenant->name]) }}</span>
    {{-- Uses the impersonated user's own prefix (not a hard-coded `admin.`)
         so role_path doesn't 302 through an extra hop. The leave route is
         registered under every panel prefix by $registerPanelRoutes, all
         with the same CheckSubscription bypass. --}}
    <a href="{{ route(auth()->user()->routeNamePrefix() . '.users.impersonate.leave') }}" class="return-btn">
        {{ __('ui.return_to_account') }}
    </a>
</div>
@endif

<div class="whoami">
    <div class="av">{{ mb_strtoupper(mb_substr($tenant->name, 0, 1)) }}</div>
    <div class="m">
        <div class="n">{{ $tenant->name }}</div>
        {{-- On the marketing host the signed-in user lives on the core app,
             so the controller passes $currentUserEmail explicitly. The
             Auth fallback keeps the single-host flow unchanged. --}}
        <div class="e">{{ $currentUserEmail ?? auth()->user()?->email }}</div>
    </div>
    @if(!session('impersonating'))
    <form method="POST" action="{{ route('logout') }}" style="margin:0" class="signout-wrap">
        @csrf
        <button type="submit" class="signout">{{ __('auth.register.plan_signout') }}</button>
    </form>
    @endif
</div>

<h2 class="formtitle">{{ __('auth.register.plan_heading') }}</h2>
<p class="formsub">{{ __('auth.register.plan_sub') }}</p>

@if($errors->any())
<div class="alert">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9.5" stroke="currentColor" stroke-width="1.8"/><path d="M12 7.5v5.5M12 16.4h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    {{ $errors->first() }}
</div>
@endif

<form method="POST" action="{{ route('register.plan.store') }}" id="planForm" data-spin>
    @csrf

    <div class="pickplans">
        @foreach($plans as $i => $plan)
        @php
            $isFree    = !$plan->price_monthly || (float) $plan->price_monthly === 0.0;
            $trialDays = $plan->trialDays();
            $checked   = (old('plan_id', $selectedPlan?->id) == $plan->id);
            $picked    = array_slice((array) ($plan->landingAttributes() ?? []), 0, 4);
        @endphp
        <input type="radio" class="planopt" name="plan_id" id="plan_{{ $plan->id }}" value="{{ $plan->id }}" {{ $checked ? 'checked' : '' }}>
        <label class="pickplan" for="plan_{{ $plan->id }}">
            <span class="top">
                <span class="mark">&#10003;</span>
                <span class="mid">
                    <span class="nm">
                        {{ $plan->name }}
                        @if($trialDays > 0)
                            <span class="free">{{ __('auth.register.plan_trial_tag', ['days' => $trialDays]) }}</span>
                        @elseif($i === 1 && $plans->count() >= 2)
                            <span class="pop">{{ __('landing.popular_short') }}</span>
                        @endif
                    </span>
                    <span class="note">
                        @if($trialDays > 0)
                            {{ __('auth.register.plan_trial_note', ['days' => $trialDays, 'price' => '$' . number_format((float) $plan->price_monthly, 0)]) }}
                        @elseif($isFree)
                            {{ __('auth.register.plan_free_note') }}
                        @else
                            {{ __('auth.register.plan_paid_note') }}
                        @endif
                    </span>
                </span>
                <span class="price">
                    <b>{{ $isFree ? __('landing.plan_free_label') : '$' . number_format((float) $plan->price_monthly, 0) }}</b>
                    @unless($isFree)<span>{{ __('landing.plan_per_month') }}</span>@endunless
                </span>
            </span>

            <ul>
                <li>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M20 6 9 17l-5-5" stroke="#15b6a8" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    {{ __('auth.register.plan_seats', ['n' => $plan->max_users ? number_format($plan->max_users) : '∞']) }}
                </li>
                <li>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M20 6 9 17l-5-5" stroke="#15b6a8" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    {{ __('auth.register.plan_ai', ['n' => $plan->ai_message_quota === null ? '∞' : number_format((int) $plan->ai_message_quota)]) }}
                </li>
                @foreach($picked as $attr)
                    @if(str_starts_with($attr, 'module:'))
                    <li>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M20 6 9 17l-5-5" stroke="#15b6a8" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        {{ __('ui.plan_modules.' . substr($attr, 7)) }}
                    </li>
                    @endif
                @endforeach
            </ul>
        </label>
        @endforeach
    </div>

    @php
        $initial = $meta[old('plan_id', $selectedPlan?->id)] ?? reset($meta);
    @endphp
    <button class="cta" type="submit" id="planCta" style="margin-top:22px">
        <span class="sp"></span>
        <span class="lbl" id="planCtaLabel">
            {{ $initial['trial'] ? __('auth.register.plan_cta_trial') : __('auth.register.plan_cta_pay') }}
        </span>
    </button>
    <div class="ctahint" id="planCtaHint">
        @if($initial['trial'])
            {{ __('auth.register.plan_cta_trial_hint', ['days' => $initial['days'], 'price' => $initial['price']]) }}
        @else
            {{ __('auth.register.plan_cta_pay_hint', ['price' => $initial['price'], 'plan' => $initial['name']]) }}
        @endif
    </div>

    <div class="reassure">
        <span><svg width="13" height="13" viewBox="0 0 24 24" fill="none"><rect x="2.5" y="5" width="19" height="14" rx="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M2.5 10h19" stroke="currentColor" stroke-width="1.8"/></svg>{{ __('landing.trust_no_card') }}</span>
        <span><svg width="13" height="13" viewBox="0 0 24 24" fill="none"><rect x="4" y="10" width="16" height="11" rx="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M8 10V7a4 4 0 118 0v3" stroke="currentColor" stroke-width="1.8"/></svg>{{ __('landing.trust_cancel') }}</span>
    </div>
</form>
@endsection

@push('scripts')
<script>
(() => {
    const META       = @json($meta);
    const LBL_TRIAL  = @json(__('auth.register.plan_cta_trial'));
    const LBL_PAY    = @json(__('auth.register.plan_cta_pay'));
    const HINT_TRIAL = @json(__('auth.register.plan_cta_trial_hint', ['days' => '__D__', 'price' => '__P__']));
    const HINT_PAY   = @json(__('auth.register.plan_cta_pay_hint', ['price' => '__P__', 'plan' => '__N__']));

    const label = document.getElementById('planCtaLabel');
    const hint  = document.getElementById('planCtaHint');

    function sync() {
        const picked = document.querySelector('input[name="plan_id"]:checked');
        const m = picked ? META[picked.value] : null;
        if (!m) { return; }

        label.textContent = m.trial ? LBL_TRIAL : LBL_PAY;
        hint.textContent  = m.trial
            ? HINT_TRIAL.replace('__D__', m.days).replace('__P__', m.price)
            : HINT_PAY.replace('__P__', m.price).replace('__N__', m.name);
    }

    document.querySelectorAll('input[name="plan_id"]').forEach(i => i.addEventListener('change', sync));
    sync();
})();
</script>
@endpush
