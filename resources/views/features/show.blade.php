@php
    $locale   = app()->getLocale();
    $isRtl    = (bool) data_get(config('locales.supported', []), $locale . '.rtl');
    $url      = url('/features/' . $slug);
    $title    = __('features.' . $slug . '.title');
    $desc     = __('features.' . $slug . '.description');
    $h1       = __('features.' . $slug . '.h1');
    $lede     = __('features.' . $slug . '.lede');
    // Every page currently ships an empty kicker. `.eyebrow` still paints a
    // background, border and padding, so rendering it regardless left a blank
    // pill floating above the H1. __() also echoes the key back when a
    // translation is missing, which would print 'features.<slug>.kicker'.
    $kicker   = __('features.' . $slug . '.kicker');
    $kicker   = $kicker === 'features.' . $slug . '.kicker' ? '' : trim($kicker);
    $ogImage  = asset('img/features/' . ($page['og_image'] ?? 'inbox-full.png'));

    // Structured data. The FAQ entries come from the same array the visible
    // accordions render below, so the markup can never claim a Q&A the page
    // does not actually show — the thing Google penalises.
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@graph'   => [
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => __('features.breadcrumb_home'),     'item' => url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => __('features.breadcrumb_features'), 'item' => url('/features')],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => strip_tags($h1),                    'item' => $url],
                ],
            ],
            [
                '@type'            => 'Article',
                'headline'         => strip_tags($h1),
                'description'      => $desc,
                'inLanguage'       => $locale,
                'image'            => $ogImage,
                'author'           => ['@type' => 'Organization', 'name' => config('app.name', 'wavadesk'), 'url' => url('/')],
                'publisher'        => [
                    '@type' => 'Organization',
                    'name'  => config('app.name', 'wavadesk'),
                    'url'   => url('/'),
                    'logo'  => ['@type' => 'ImageObject', 'url' => asset('favicon-512.png')],
                ],
                'datePublished'    => '2026-09-10',
                'dateModified'     => now()->toDateString(),
                'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
            ],
            [
                '@type' => 'FAQPage',
                'mainEntity' => collect($page['faq'])->map(fn ($f, $i) => [
                    '@type'          => 'Question',
                    'name'           => __('features.' . $slug . '.faq.' . $i . '.q'),
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text'  => implode(' ', array_map(
                            fn ($j) => __('features.' . $slug . '.faq.' . $i . '.a.' . $j),
                            array_keys($f['a']),
                        )),
                    ],
                ])->values()->all(),
            ],
        ],
    ];
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

{{-- ── Core SEO ───────────────────────────────────────────────────────── --}}
<title>{{ $title }}</title>
<meta name="description" content="{{ $desc }}">
<link rel="canonical" href="{{ $url }}">
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">

{{-- No per-locale alternates: language is chosen by session, so every
     translation of this page lives at this one URL. Emitting fr/ar hreflangs
     that all point here would declare three pages where there is one, which
     is worse than declaring none. Proper alternates need locale-prefixed URLs
     (/fr/features/...), which is a routing change, not a copy change. --}}
<link rel="alternate" hreflang="x-default" href="{{ $url }}">
<meta property="og:locale" content="{{ str_replace('-', '_', $locale) }}">

<meta property="og:type" content="article">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $desc }}">
<meta property="og:url" content="{{ $url }}">
<meta property="og:site_name" content="{{ config('app.name', 'wavadesk') }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:locale" content="{{ $locale }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $desc }}">
<meta name="twitter:image" content="{{ $ogImage }}">

<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/features.css') }}">

<script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
</head>
<body>

{{-- The locale switcher belongs here: these pages are translated, so a
     reader landing on one from search needs a way to change language. --}}
@include('partials.marketing-nav', ['hideLocale' => false])

{{-- ── Breadcrumb (matches the BreadcrumbList above) ───────────────────── --}}
<div class="wrap bc">
    <ol>
        <li><a href="{{ url('/') }}">{{ __('features.breadcrumb_home') }}</a></li>
        <li class="sep" aria-hidden="true">/</li>
        <li><a href="{{ url('/features') }}">{{ __('features.breadcrumb_features') }}</a></li>
        <li class="sep" aria-hidden="true">/</li>
        <li aria-current="page">{{ __('features.' . $slug . '.nav_title') }}</li>
    </ol>
</div>

{{-- ── Hero ───────────────────────────────────────────────────────────── --}}
<div class="hero">
    <div class="glow" aria-hidden="true"></div>
    <div class="wrap in">
        @if($kicker !== '')
            <span class="eyebrow">{{ $kicker }}</span>
        @endif
        <h1>{!! $h1 !!}</h1>
        <p class="lede">{{ $lede }}</p>
        <div class="cta">
            @if($homeRoute)
                <a class="btn p" href="{{ $homeRoute }}">{{ __('landing.go_to_dashboard') }}</a>
            @else
                <a class="btn p" href="{{ route('register') }}">{{ __('landing.nav_cta') }}</a>
            @endif
            <a class="btn d" href="{{ url('/#pricing') }}">{{ __('features.see_pricing') }}</a>
        </div>
        <div class="micro">
            <span><i class="ri-check-line"></i>{{ __('features.micro_trial', ['days' => $fromPlan?->trialDays() ?: config('app.trial_days', 7)]) }}</span>
            <span><i class="ri-check-line"></i>{{ __('features.micro_nocard') }}</span>
            <span><i class="ri-check-line"></i>{{ __('features.micro_from', ['price' => $fromPlan ? '$' . rtrim(rtrim(number_format((float) $fromPlan->price_monthly, 2), '0'), '.') : '—']) }}</span>
        </div>
    </div>
</div>

{{-- ── Article + sticky table of contents ─────────────────────────────── --}}
<div class="wrap doc">
    <aside class="toc">
        <div class="lbl">{{ __('features.on_this_page') }}</div>
        <ol>
            @foreach($page['toc'] as $i => $t)
            <li><a href="#{{ $t['id'] }}">{{ __('features.' . $slug . '.toc.' . $i) }}</a></li>
            @endforeach
        </ol>
    </aside>

    <article class="art">
        {{-- Translated article bodies live in features/content/<locale>/<slug>.
             A locale that has not been translated yet falls through to the
             English body rather than rendering an empty article. --}}
        @includeFirst([
            'features.content.' . app()->getLocale() . '.' . $slug,
            'features.content.' . $slug,
        ])

        {{-- ── FAQ, rendered from the same array as the JSON-LD ────────── --}}
        <section id="faq">
            <h2>{{ __('features.faq_title') }}</h2>
            @foreach($page['faq'] as $i => $f)
            <details>
                <summary>{{ __('features.' . $slug . '.faq.' . $i . '.q') }}</summary>
                <div class="a">
                    @foreach($f['a'] as $j => $unused)
                    <p>{{ __('features.' . $slug . '.faq.' . $i . '.a.' . $j) }}</p>
                    @endforeach
                </div>
            </details>
            @endforeach
        </section>
    </article>
</div>

{{-- ── Related pages: the internal links that make the cluster crawlable.
     Class names match the shared stylesheet (.related / .rg) — inventing new
     ones here is what left this block unstyled. ── --}}
<div class="wrap related">
    <h2>{{ __('features.related_title') }}</h2>
    <p class="sub">{{ __('features.related_sub', ['topic' => __('features.' . $slug . '.nav_title')]) }}</p>
    <div class="rg">
        @foreach($page['related'] as $r)
            @isset($pages[$r])
            <a class="rc" href="{{ url('/features/' . $r) }}">
                @php($rk = __('features.' . $r . '.kicker'))
                @php($rk = $rk === 'features.' . $r . '.kicker' ? '' : trim($rk))
                @if($rk !== '')<div class="k">{{ $rk }}</div>@endif
                <div class="h">{{ __('features.' . $r . '.nav_title') }}</div>
                <p>{{ __('features.' . $r . '.nav_sub') }}</p>
            </a>
            @endisset
        @endforeach
    </div>
</div>

{{-- ── Final CTA ──────────────────────────────────────────────────────── --}}
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

<script>
/* Highlight the table-of-contents entry for whatever section is in view. */
(function () {
    const links = [...document.querySelectorAll('.toc a')];
    if (!links.length) return;
    const byId = new Map(links.map(a => [a.getAttribute('href').slice(1), a]));
    const seen = new Set();

    const io = new IntersectionObserver(entries => {
        entries.forEach(e => e.isIntersecting ? seen.add(e.target.id) : seen.delete(e.target.id));
        links.forEach(a => a.classList.remove('on'));
        // The topmost visible section wins, so the marker never jumps ahead.
        for (const a of links) {
            if (seen.has(a.getAttribute('href').slice(1))) { a.classList.add('on'); break; }
        }
    }, { rootMargin: '-88px 0px -70% 0px' });

    document.querySelectorAll('.art section[id]').forEach(s => io.observe(s));
})();
</script>
@include('partials.wavadesk-chat-widget')
</body>
</html>
