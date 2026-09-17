@php
    $url    = url('/features');
    $locale = app()->getLocale();
    $isRtl  = (bool) data_get(config('locales.supported', []), $locale . '.rtl');
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('features.index_title') }}</title>
<meta name="description" content="{{ __('features.index_description') }}">
<link rel="canonical" href="{{ $url }}">
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
<meta property="og:type" content="website">
<meta property="og:title" content="{{ __('features.index_title') }}">
<meta property="og:description" content="{{ __('features.index_description') }}">
<meta property="og:url" content="{{ $url }}">
<meta property="og:site_name" content="{{ config('app.name', 'wavadesk') }}">
<meta property="og:image" content="{{ asset('img/features/inbox-full.png') }}">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
{{-- Cairo carries the Arabic; features.css switches to it on html[dir="rtl"],
     and without it here that rule fell through to a system font. Google
     serves these with unicode-range, so Latin visitors never fetch the
     Arabic files. Keep in step with features/show.blade.php. --}}
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/features.css') }}">
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'CollectionPage',
    'name'     => __('features.index_h1'),
    'description' => __('features.index_description'),
    'url'      => $url,
    'hasPart'  => collect($pages)->map(fn ($p, $slug) => [
        '@type'       => 'WebPage',
        'name'        => __('features.' . $slug . '.nav_title'),
        'description' => __('features.' . $slug . '.nav_sub'),
        'url'         => url('/features/' . $slug),
    ])->values()->all(),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
</head>
<body>

@include('partials.marketing-nav', ['hideLocale' => false])

<div class="wrap bc">
    <ol>
        <li><a href="{{ url('/') }}">{{ __('features.breadcrumb_home') }}</a></li>
        <li class="sep" aria-hidden="true">/</li>
        <li aria-current="page">{{ __('features.breadcrumb_features') }}</li>
    </ol>
</div>

<div class="hero">
    <div class="glow" aria-hidden="true"></div>
    <div class="wrap in">
        <span class="eyebrow">{{ __('features.breadcrumb_features') }}</span>
        <h1>{{ __('features.index_h1') }}</h1>
        <p class="lede">{{ __('features.index_lede') }}</p>
        <div class="cta">
            @if($homeRoute)
                <a class="btn p" href="{{ $homeRoute }}">{{ __('landing.go_to_dashboard') }}</a>
            @else
                <a class="btn p" href="{{ route('register') }}">{{ __('landing.nav_cta') }}</a>
            @endif
            <a class="btn d" href="{{ url('/#pricing') }}">{{ __('features.see_pricing') }}</a>
        </div>
    </div>
</div>

<div class="wrap">
    <div class="fidx">
        @foreach($pages as $slug => $p)
        <a class="rc" href="{{ url('/features/' . $slug) }}">
            <div class="k">{{ __('features.' . $slug . '.kicker') }}</div>
            <div class="h">{{ __('features.' . $slug . '.nav_title') }}</div>
            <p>{{ __('features.' . $slug . '.nav_sub') }}</p>
        </a>
        @endforeach
    </div>
</div>

<section class="final">
    <div class="wrap">
        <h2>{{ __('features.cta_title') }}</h2>
        <p>{{ __('features.cta_sub') }}</p>
        <div class="cta">
            @if($homeRoute)
                <a class="btn p" href="{{ $homeRoute }}">{{ __('landing.go_to_dashboard') }}</a>
            @else
                <a class="btn p" href="{{ route('register') }}">{{ __('landing.nav_cta') }}</a>
            @endif
            <a class="btn g" href="{{ url('/#pricing') }}">{{ __('features.see_pricing') }}</a>
        </div>
    </div>
</section>

@include('partials.marketing-footer')
@include('partials.wavadesk-chat-widget')
</body>
</html>
