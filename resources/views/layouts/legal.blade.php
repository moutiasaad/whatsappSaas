<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ data_get(config('locales.supported', []), app()->getLocale() . '.rtl') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') — {{ config('app.name', 'wavadesk') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand:        #0f7e7a;
            --brand-dark:   #0a5e5b;
            --brand-xdark:  #047857;
            --brand-light:  #d6efed;
            --brand-xlight: #ecf7f6;
            --bg:           #f8fafc;
            --card:         #ffffff;
            --border:       #e2e8f0;
            --dark:         #0d1117;
            --dark2:        #161b22;
            --text:         #0f172a;
            --muted:        #64748b;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Outfit', system-ui, sans-serif; background: #fff; color: var(--text); line-height: 1.6; overflow-x: hidden; }
        html[dir="rtl"] body { font-family: 'Cairo', sans-serif; line-height: 1.7; }

        @keyframes navSlide    { from{transform:translateY(-100%);opacity:0} to{transform:translateY(0);opacity:1} }
        @keyframes scaleIn     { from{opacity:0;transform:scale(.88)} to{opacity:1;transform:scale(1)} }
        @keyframes fadeInUp    { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }

        .container { max-width:1160px; margin:0 auto; padding:0 24px; }

        /* nav */
        nav { position:sticky; top:0; z-index:100; background:rgba(255,255,255,.92); backdrop-filter:blur(14px); border-bottom:1px solid var(--border); animation:navSlide .5s ease both; }
        .nav-inner { display:flex; align-items:center; justify-content:space-between; padding:14px 0; }
        .nav-logo { display:flex; align-items:center; gap:10px; font-size:19px; font-weight:800; color:var(--text); text-decoration:none; }
        .nav-logo-icon { width:36px; height:36px; border-radius:9px; overflow:hidden; display:flex; align-items:center; justify-content:center; transition:transform .25s; }
        .nav-logo:hover .nav-logo-icon { transform:rotate(-8deg) scale(1.1); }
        .nav-cta { display:flex; gap:10px; align-items:center; }
        .btn-ghost { font-size:14px; font-weight:500; color:var(--brand); text-decoration:none; padding:8px 18px; border-radius:8px; transition:background .2s; }
        .btn-ghost:hover { background:var(--brand-xlight); }
        .btn-primary { background:linear-gradient(135deg,var(--brand),var(--brand-dark)); color:#fff; font-size:14px; font-weight:600; padding:9px 22px; border-radius:9px; text-decoration:none; border:none; cursor:pointer; font-family:inherit; transition:all .2s; box-shadow:0 4px 14px rgba(15,126,122,.35); }
        .btn-primary:hover { box-shadow:0 6px 20px rgba(15,126,122,.45); transform:translateY(-1px); }

        /* lang switcher */
        .pub-lang { position:relative; }
        .pub-lang-btn { display:flex; align-items:center; gap:5px; background:none; border:1px solid var(--border); border-radius:8px; padding:6px 10px; cursor:pointer; font-size:12px; font-weight:600; color:var(--text); font-family:inherit; transition:background .2s; }
        .pub-lang-btn:hover { background:var(--brand-xlight); border-color:var(--brand); }
        .pub-lang-dropdown { position:absolute; top:calc(100% + 8px); right:0; background:#fff; border:1px solid var(--border); border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,.12); min-width:155px; overflow:hidden; z-index:200; display:none; }
        .pub-lang-dropdown.open { display:block; animation:scaleIn .15s ease; }
        .pub-lang-item { display:flex; align-items:center; gap:8px; width:100%; background:none; border:none; padding:10px 14px; font-size:13px; cursor:pointer; color:var(--text); font-family:inherit; transition:background .15s; }
        .pub-lang-item:hover { background:var(--brand-xlight); }
        .pub-lang-item.active { font-weight:600; color:var(--brand); }
        html[dir=rtl] .pub-lang-dropdown { right:auto; left:0; }

        /* legal hero */
        .legal-hero { background:var(--dark); padding:64px 0 56px; position:relative; overflow:hidden; }
        .legal-hero::before { content:''; position:absolute; inset:0; background:radial-gradient(ellipse at 30% 50%, rgba(15,126,122,.12) 0%, transparent 60%); pointer-events:none; }
        .legal-breadcrumb { display:flex; align-items:center; gap:8px; font-size:13px; color:#64748b; margin-bottom:20px; animation:fadeInUp .5s ease both; }
        .legal-breadcrumb a { color:#64748b; text-decoration:none; transition:color .2s; }
        .legal-breadcrumb a:hover { color:var(--brand); }
        .legal-breadcrumb span { color:#334155; }
        .legal-hero h1 { font-size:clamp(28px,4vw,44px); font-weight:900; color:#f1f5f9; margin-bottom:12px; animation:fadeInUp .6s ease both .05s; }
        .legal-hero p { font-size:15px; color:#64748b; animation:fadeInUp .6s ease both .1s; }

        /* legal content */
        .legal-content { padding:64px 0 80px; }
        .legal-body { max-width:780px; }
        .legal-body h2 { font-size:20px; font-weight:800; color:var(--text); margin:40px 0 12px; padding-top:8px; border-top:1px solid var(--border); }
        .legal-body h2:first-child { margin-top:0; border-top:none; padding-top:0; }
        .legal-body h3 { font-size:15px; font-weight:700; color:var(--text); margin:20px 0 8px; }
        .legal-body p { font-size:15px; color:var(--muted); line-height:1.75; margin-bottom:14px; }
        .legal-body ul { margin:8px 0 14px 20px; display:flex; flex-direction:column; gap:6px; }
        .legal-body ul li { font-size:15px; color:var(--muted); line-height:1.65; }
        .legal-body a { color:var(--brand); text-decoration:none; }
        .legal-body a:hover { text-decoration:underline; }
        .legal-sidebar { position:sticky; top:88px; }
        .legal-nav-card { background:var(--bg); border:1px solid var(--border); border-radius:14px; padding:20px; }
        .legal-nav-card h4 { font-size:12px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.5px; margin-bottom:14px; }
        .legal-nav-card a { display:block; font-size:13px; color:var(--muted); text-decoration:none; padding:5px 0; border-left:2px solid transparent; padding-left:10px; transition:all .2s; }
        .legal-nav-card a:hover { color:var(--brand); border-color:var(--brand); }
        .legal-grid { display:grid; grid-template-columns:1fr 240px; gap:48px; align-items:start; }
        @media(max-width:860px) { .legal-grid { grid-template-columns:1fr; } .legal-sidebar { display:none; } }

        /* legal page switcher pills */
        .legal-pills { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:36px; }
        .legal-pill { font-size:13px; font-weight:600; padding:6px 16px; border-radius:100px; text-decoration:none; border:1.5px solid var(--border); color:var(--muted); transition:all .2s; }
        .legal-pill:hover { border-color:var(--brand); color:var(--brand); background:var(--brand-xlight); }
        .legal-pill.active { background:var(--brand); color:#fff; border-color:var(--brand); }

        /* footer */
        .footer { background:var(--dark); border-top:1px solid #21262d; padding:48px 0 32px; }
        .footer-inner { display:grid; grid-template-columns:1.8fr 1fr 1fr 1fr; gap:32px; margin-bottom:40px; }
        @media(max-width:720px) { .footer-inner { grid-template-columns:1fr 1fr; } }
        @media(max-width:480px) { .footer-inner { grid-template-columns:1fr; } }
        .footer-brand .logo { display:flex; align-items:center; gap:10px; font-size:18px; font-weight:800; color:#f1f5f9; text-decoration:none; margin-bottom:12px; }
        .footer-brand p { font-size:13px; color:#64748b; line-height:1.6; max-width:220px; }
        .footer-col h4 { font-size:13px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.5px; margin-bottom:14px; }
        .footer-col a { display:block; font-size:14px; color:#64748b; text-decoration:none; margin-bottom:8px; transition:color .2s; }
        .footer-col a:hover { color:var(--brand); }
        .footer-bottom { border-top:1px solid #21262d; padding-top:24px; text-align:center; font-size:13px; color:#475569; }

        @media(max-width:860px) { .nav-links, .nav-mobile-hide { display:none; } }
    </style>
</head>
<body>

{{-- NAV --}}
<nav>
    <div class="container">
        <div class="nav-inner">
            <a href="{{ route('landing') }}" class="nav-logo">
                <div class="nav-logo-icon">
                    <img src="{{ asset('images/logo.svg') }}?v={{ @filemtime(public_path('images/logo.svg')) ?: 1 }}" alt="{{ config('app.name') }}" width="36" height="36" onerror="this.style.display='none'">
                </div>
                {{ config('app.name', 'wavadesk') }}
            </a>
            <div class="nav-cta">
                <div class="pub-lang">
                    <button class="pub-lang-btn" id="langBtn" type="button">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>
                        {{ strtoupper(app()->getLocale()) }}
                    </button>
                    <div class="pub-lang-dropdown" id="langDropdown">
                        @foreach(config('locales.supported', []) as $code => $meta)
                        <form method="POST" action="{{ route('locale.update') }}" style="margin:0">
                            @csrf
                            <input type="hidden" name="locale" value="{{ $code }}">
                            <input type="hidden" name="redirect" value="{{ url()->full() }}">
                            <button type="submit" class="pub-lang-item {{ app()->getLocale() === $code ? 'active' : '' }}">
                                {{ $meta['native'] ?? $code }}
                            </button>
                        </form>
                        @endforeach
                    </div>
                </div>
                <a href="{{ route('login') }}" class="btn-primary">{{ __('landing.nav_login') }}</a>
            </div>
        </div>
    </div>
</nav>

{{-- HERO --}}
<div class="legal-hero">
    <div class="container">
        <div class="legal-breadcrumb">
            <a href="{{ route('landing') }}">{{ config('app.name', 'wavadesk') }}</a>
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
            <span>@yield('title')</span>
        </div>
        <h1>@yield('title')</h1>
        <p>@yield('subtitle')</p>
    </div>
</div>

{{-- CONTENT --}}
<section class="legal-content">
    <div class="container">
        <div class="legal-grid">
            <div class="legal-body">
                {{-- page switcher pills --}}
                <div class="legal-pills">
                    <a href="{{ route('legal.terms') }}" class="legal-pill {{ request()->routeIs('legal.terms') ? 'active' : '' }}">{{ __('landing.footer_terms') }}</a>
                    <a href="{{ route('legal.privacy') }}" class="legal-pill {{ request()->routeIs('legal.privacy') ? 'active' : '' }}">{{ __('landing.footer_privacy') }}</a>
                    <a href="{{ route('legal.cookies') }}" class="legal-pill {{ request()->routeIs('legal.cookies') ? 'active' : '' }}">{{ __('landing.footer_cookies') }}</a>
                </div>
                @yield('content')
            </div>
            <aside class="legal-sidebar">
                <div class="legal-nav-card">
                    <h4>{{ __('landing.footer_legal') }}</h4>
                    @yield('toc')
                </div>
            </aside>
        </div>
    </div>
</section>

{{-- FOOTER --}}
<footer class="footer">
    <div class="container">
        <div class="footer-inner">
            <div class="footer-brand">
                <a href="{{ route('landing') }}" class="logo" style="display:flex;align-items:center;gap:8px;text-decoration:none;font-size:16px;font-weight:800;color:#f8fafc">
                    <img src="{{ asset('images/logo.svg') }}?v={{ @filemtime(public_path('images/logo.svg')) ?: 1 }}" alt="{{ config('app.name') }}" width="30" height="30" style="border-radius:7px"
                         onerror="this.style.display='none'">
                    {{ config('app.name', 'wavadesk') }}
                </a>
                <p>{{ __('landing.footer_tagline') }}</p>
            </div>
            <div class="footer-col">
                <h4>{{ __('landing.footer_product') }}</h4>
                <a href="{{ route('landing') }}#features">{{ __('landing.footer_features') }}</a>
                <a href="{{ route('landing') }}#pricing">{{ __('landing.footer_pricing') }}</a>
                <a href="{{ route('landing') }}#how">{{ __('landing.footer_how') }}</a>
            </div>
            <div class="footer-col">
                <h4>{{ __('landing.footer_company') }}</h4>
                <a href="{{ route('login') }}">{{ __('landing.nav_login') }}</a>
            </div>
            <div class="footer-col">
                <h4>{{ __('landing.footer_legal') }}</h4>
                <a href="{{ route('legal.privacy') }}">{{ __('landing.footer_privacy') }}</a>
                <a href="{{ route('legal.terms') }}">{{ __('landing.footer_terms') }}</a>
                <a href="{{ route('legal.cookies') }}">{{ __('landing.footer_cookies') }}</a>
            </div>
        </div>
        <div class="footer-bottom">
            {{ __('landing.footer_copyright', ['year' => date('Y'), 'app' => config('app.name', 'wavadesk')]) }}
        </div>
    </div>
</footer>

<script>
const langBtn = document.getElementById('langBtn');
const langDD  = document.getElementById('langDropdown');
if (langBtn && langDD) {
    langBtn.addEventListener('click', e => { e.stopPropagation(); langDD.classList.toggle('open'); });
    document.addEventListener('click', () => langDD.classList.remove('open'));
    langDD.addEventListener('click', e => e.stopPropagation());
}
</script>
</body>
</html>
