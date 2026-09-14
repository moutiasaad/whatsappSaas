{{-- Four-column sitemap footer. Every feature page is linked from every other
     page, which is what makes the cluster crawlable from a single entry point. --}}
@php $features = config('seo_pages', []); @endphp
<footer><div class="wrap">
    <div class="fcols">
        <div>
            <span class="wm">{{ config('app.name', 'wavadesk') }}</span>
            <p class="ftag">{{ __('landing.footer_tagline') }}</p>
        </div>
        <div>
            <h4>{{ __('landing.footer_product') }}</h4>
            <ul>
                @foreach(['whatsapp-shared-inbox','whatsapp-multi-agent','live-chat-widget','teams-routing'] as $s)
                    @isset($features[$s])<li><a href="{{ url('/features/' . $s) }}">{{ __('features.' . $s . '.nav_title') }}</a></li>@endisset
                @endforeach
            </ul>
        </div>
        <div>
            <h4>{{ __('landing.footer_automation') }}</h4>
            <ul>
                @foreach(['ai-agent','knowledge-base','otp-service','reservations'] as $s)
                    @isset($features[$s])<li><a href="{{ url('/features/' . $s) }}">{{ __('features.' . $s . '.nav_title') }}</a></li>@endisset
                @endforeach
            </ul>
        </div>
        <div>
            <h4>{{ __('landing.footer_platform') }}</h4>
            <ul>
                @foreach(['reports-analytics','api-integrations'] as $s)
                    @isset($features[$s])<li><a href="{{ url('/features/' . $s) }}">{{ __('features.' . $s . '.nav_title') }}</a></li>@endisset
                @endforeach
                <li><a href="{{ url('/#pricing') }}">{{ __('landing.footer_pricing') }}</a></li>
                <li><a href="{{ route('login') }}">{{ __('landing.nav_signin') }}</a></li>
            </ul>
        </div>
    </div>
    <div class="fbase">
        <span>&copy; {{ date('Y') }} {{ config('app.name', 'wavadesk') }}</span>
        <a href="{{ url('/') }}">{{ __('landing.footer_home') }}</a>
        <a href="{{ route('legal.terms') }}">{{ __('landing.footer_terms') }}</a>
        <a href="{{ route('legal.privacy') }}">{{ __('landing.footer_privacy') }}</a>
        <a href="{{ route('legal.cookies') }}">{{ __('landing.footer_cookies') }}</a>
    </div>
</div></footer>
