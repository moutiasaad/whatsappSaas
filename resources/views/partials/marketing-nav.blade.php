{{-- Sticky marketing header, shared by the landing page and every /features
     page so the mega-menu and CTAs stay one component. --}}
@php
    $u        = auth()->user();
    $navHome  = $homeRoute ?? null;
    $features = config('seo_pages', []);
    $megaIcons = [
        'whatsapp-shared-inbox' => 'ri-chat-3-line',
        'ai-agent'              => 'ri-sparkling-2-line',
        'whatsapp-multi-agent'  => 'ri-team-line',
        'knowledge-base'        => 'ri-book-2-line',
        'live-chat-widget'      => 'ri-chat-smile-2-line',
        'teams-routing'         => 'ri-node-tree',
        'otp-service'           => 'ri-shield-keyhole-line',
        'reservations'          => 'ri-calendar-check-line',
        'reports-analytics'     => 'ri-bar-chart-2-line',
        'api-integrations'      => 'ri-code-s-slash-line',
    ];
@endphp
<div class="nav"><div class="wrap in">
    <a class="brand" href="{{ url('/') }}" aria-label="{{ config('app.name', 'wavadesk') }}">
        <svg width="34" height="34" viewBox="0 0 512 512" fill="none" aria-hidden="true"><rect x="7" y="7" width="498" height="498" rx="118" fill="#0f7e7a"/><g transform="translate(256,256) scale(.8) translate(-284,-267)"><path d="M 96 326 C 162 326, 162 184, 240 184 C 320 184, 320 350, 388 350 C 432 350, 432 226, 472 226" stroke="#fff" stroke-width="46" stroke-linecap="round" fill="none"/><circle cx="96" cy="326" r="34" fill="#fff"/><circle cx="472" cy="226" r="34" fill="#d6efed"/></g></svg>
        <span class="wm">{{ config('app.name', 'wavadesk') }}</span>
    </a>

    <nav aria-label="{{ __('landing.nav_primary') }}">
        {{-- Opens on hover AND focus-within, so it is reachable by keyboard. --}}
        <div class="has-mega">
            <button class="navbtn" aria-haspopup="true" aria-expanded="false">
                {{ __('landing.nav_product') }}
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <div class="mega"><div class="megainner">
                @foreach($features as $slug => $f)
                <a class="mi" href="{{ url('/features/' . $slug) }}">
                    <span class="mic"><i class="{{ $megaIcons[$slug] ?? 'ri-checkbox-blank-circle-line' }}" style="color:#0f7e7a;font-size:18px"></i></span>
                    <span>
                        <span class="mt">{{ __('features.' . $slug . '.nav_title') }}</span>
                        <span class="ms">{{ __('features.' . $slug . '.nav_sub') }}</span>
                    </span>
                </a>
                @endforeach
                <div class="megafoot">
                    <span class="mf">{{ __('landing.plan_unlimited_headline') }}</span>
                    <a class="mfl" href="{{ url('/#pricing') }}">
                        {{ __('landing.nav_see_pricing') }}
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h13M12 5l7 7-7 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                </div>
            </div></div>
        </div>
        <a href="{{ url('/#how') }}">{{ __('landing.nav_how') }}</a>
        <a href="{{ url('/#pricing') }}">{{ __('landing.nav_pricing') }}</a>
        <a href="{{ url('/#faq') }}">{{ __('landing.nav_faq') }}</a>
    </nav>

    <div class="act">
        {{-- Callers that are genuinely English-only can still opt out; feature
             pages no longer do, since their article bodies are translated. --}}
        @unless($hideLocale ?? false)
            @include('partials.locale-switcher')
        @endunless
        @if($navHome)
            <a class="btn p sm" href="{{ $navHome }}">{{ __('landing.go_to_dashboard') }}</a>
        @else
            <a class="si" href="{{ route('login') }}">{{ __('landing.nav_signin') }}</a>
            <a class="btn p sm" href="{{ route('register') }}">{{ __('landing.nav_cta') }}</a>
        @endif
        @include('partials.marketing-mobile-nav-button')
    </div>
</div></div>

@include('partials.marketing-mobile-nav')
