<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ data_get(config('locales.supported', []), app()->getLocale() . '.rtl') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('landing.page_title') }}</title>
    <meta name="description" content="{{ __('landing.page_desc') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* ── tokens ── */
        :root {
            --brand:        #10b981;
            --brand-dark:   #059669;
            --brand-xdark:  #047857;
            --brand-light:  #d1fae5;
            --brand-xlight: #ecfdf5;
            --bg:           #f8fafc;
            --card:         #ffffff;
            --border:       #e2e8f0;
            --dark:         #0d1117;
            --dark2:        #161b22;
            --text:         #0f172a;
            --muted:        #64748b;
            --green-glow:   rgba(16,185,129,.25);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Outfit', system-ui, sans-serif; background: #fff; color: var(--text); line-height: 1.6; overflow-x: hidden; }

        /* animations */
        @keyframes fadeInDown  { from{opacity:0;transform:translateY(-24px)} to{opacity:1;transform:translateY(0)} }
        @keyframes fadeInUp    { from{opacity:0;transform:translateY(36px)} to{opacity:1;transform:translateY(0)} }
        @keyframes fadeIn      { from{opacity:0} to{opacity:1} }
        @keyframes scaleIn     { from{opacity:0;transform:scale(.88)} to{opacity:1;transform:scale(1)} }
        @keyframes floatY      { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-12px)} }
        @keyframes blobMove    { 0%,100%{border-radius:60% 40% 30% 70%/60% 30% 70% 40%} 50%{border-radius:30% 60% 70% 40%/50% 60% 30% 60%} }
        @keyframes gradShift   { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        @keyframes marqueeScroll { from{transform:translateX(0)} to{transform:translateX(-50%)} }
        @keyframes navSlide    { from{transform:translateY(-100%);opacity:0} to{transform:translateY(0);opacity:1} }
        @keyframes pulseRing   { 0%{box-shadow:0 0 0 0 rgba(16,185,129,.4)} 70%{box-shadow:0 0 0 16px rgba(16,185,129,0)} 100%{box-shadow:0 0 0 0 rgba(16,185,129,0)} }

        .reveal { opacity:0; transform:translateY(36px); transition:opacity .65s cubic-bezier(.16,1,.3,1), transform .65s cubic-bezier(.16,1,.3,1); }
        .reveal.reveal-left  { transform:translateX(-48px); }
        .reveal.reveal-right { transform:translateX(48px); }
        .reveal.visible { opacity:1; transform:translate(0); }
        .stagger > * { opacity:0; transform:translateY(32px); transition:opacity .55s cubic-bezier(.16,1,.3,1), transform .55s cubic-bezier(.16,1,.3,1); }
        .stagger.visible > *:nth-child(1){transition-delay:.05s}
        .stagger.visible > *:nth-child(2){transition-delay:.12s}
        .stagger.visible > *:nth-child(3){transition-delay:.19s}
        .stagger.visible > *:nth-child(4){transition-delay:.26s}
        .stagger.visible > *:nth-child(5){transition-delay:.33s}
        .stagger.visible > *:nth-child(6){transition-delay:.40s}
        .stagger.visible > * { opacity:1; transform:translateY(0); }

        .container { max-width:1160px; margin:0 auto; padding:0 24px; }

        /* ── nav ── */
        nav { position:sticky; top:0; z-index:100; background:rgba(255,255,255,.92); backdrop-filter:blur(14px); border-bottom:1px solid var(--border); animation:navSlide .5s ease both; }
        .nav-inner { display:flex; align-items:center; justify-content:space-between; padding:14px 0; }
        .nav-logo { display:flex; align-items:center; gap:10px; font-size:19px; font-weight:800; color:var(--text); text-decoration:none; }
        .nav-logo-icon { width:36px; height:36px; border-radius:9px; background:linear-gradient(135deg,var(--brand),var(--brand-dark)); display:flex; align-items:center; justify-content:center; color:#fff; transition:transform .25s; }
        .nav-logo:hover .nav-logo-icon { transform:rotate(-8deg) scale(1.1); }
        .nav-links { display:flex; align-items:center; gap:28px; }
        .nav-links a { font-size:14px; font-weight:500; color:var(--muted); text-decoration:none; transition:color .2s; position:relative; }
        .nav-links a::after { content:''; position:absolute; bottom:-2px; left:0; width:0; height:2px; background:var(--brand); transition:width .25s; }
        .nav-links a:hover { color:var(--brand); }
        .nav-links a:hover::after { width:100%; }
        .nav-cta { display:flex; gap:10px; align-items:center; }
        .nav-mobile-hide { display:flex; }

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

        .btn-ghost { font-size:14px; font-weight:500; color:var(--brand); text-decoration:none; padding:8px 18px; border-radius:8px; transition:background .2s; border:none; cursor:pointer; font-family:inherit; }
        .btn-ghost:hover { background:var(--brand-xlight); }
        .btn-primary { background:linear-gradient(135deg,var(--brand),var(--brand-dark)); color:#fff; font-size:14px; font-weight:600; padding:9px 22px; border-radius:9px; text-decoration:none; border:none; cursor:pointer; font-family:inherit; transition:all .2s; box-shadow:0 4px 14px rgba(16,185,129,.3); }
        .btn-primary:hover { box-shadow:0 6px 20px rgba(16,185,129,.45); transform:translateY(-1px); }
        .btn-primary:active { transform:none; }
        .btn-primary-lg { font-size:16px; font-weight:700; padding:14px 32px; border-radius:12px; }
        .btn-outline { font-size:14px; font-weight:600; padding:9px 22px; border-radius:9px; border:1.5px solid var(--border); color:var(--text); text-decoration:none; transition:all .2s; background:#fff; cursor:pointer; font-family:inherit; }
        .btn-outline:hover { border-color:var(--brand); color:var(--brand); background:var(--brand-xlight); }
        .btn-outline-lg { font-size:16px; font-weight:600; padding:14px 32px; border-radius:12px; }

        /* ── hero ── */
        .hero { background:var(--dark); position:relative; overflow:hidden; padding:100px 0 80px; }
        .hero::before { content:''; position:absolute; inset:0; background:radial-gradient(ellipse at 20% 50%, rgba(16,185,129,.12) 0%, transparent 55%), radial-gradient(ellipse at 80% 20%, rgba(5,150,105,.07) 0%, transparent 50%); pointer-events:none; }
        .hero-blob { position:absolute; width:500px; height:500px; border-radius:40%; animation:blobMove 12s ease-in-out infinite, gradShift 8s ease infinite; filter:blur(80px); pointer-events:none; }
        .hero-blob-1 { top:-150px; right:-100px; background:linear-gradient(135deg, rgba(16,185,129,.18), rgba(5,150,105,.08)); }
        .hero-blob-2 { bottom:-200px; left:-150px; background:linear-gradient(135deg, rgba(16,185,129,.1), rgba(4,120,87,.06)); animation-delay:-5s; }

        .hero-inner { position:relative; z-index:1; display:grid; grid-template-columns:1fr 1fr; gap:64px; align-items:center; }
        @media(max-width:900px) { .hero-inner { grid-template-columns:1fr; } .hero-right { display:none; } }

        .hero-badge { display:inline-flex; align-items:center; gap:8px; background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.2); color:var(--brand); font-size:13px; font-weight:500; padding:6px 14px; border-radius:999px; margin-bottom:24px; animation:fadeIn .6s ease both; }
        .hero-badge svg { animation:pulseRing 2s infinite; border-radius:50%; }
        .hero h1 { font-size:clamp(36px,5vw,60px); font-weight:900; line-height:1.1; color:#f1f5f9; margin-bottom:20px; animation:fadeInUp .7s ease both .1s; }
        .hero h1 span { color:var(--brand); }
        .hero-sub { font-size:17px; color:#94a3b8; line-height:1.65; max-width:480px; margin-bottom:36px; animation:fadeInUp .7s ease both .2s; }
        .hero-actions { display:flex; gap:14px; flex-wrap:wrap; margin-bottom:40px; animation:fadeInUp .7s ease both .3s; }
        .hero-trust { display:flex; gap:24px; flex-wrap:wrap; animation:fadeInUp .7s ease both .4s; }
        .trust-item { display:flex; align-items:center; gap:8px; font-size:13px; color:#64748b; font-weight:500; }
        .trust-dot { width:6px; height:6px; border-radius:50%; background:var(--brand); flex-shrink:0; }

        /* hero right — chat mockup */
        .hero-card { background:#161b22; border:1px solid #30363d; border-radius:16px; padding:20px; animation:scaleIn .8s ease both .3s; }
        .chat-header { display:flex; align-items:center; gap:10px; padding-bottom:14px; border-bottom:1px solid #30363d; margin-bottom:14px; }
        .chat-avatar { width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg,var(--brand),var(--brand-dark)); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .chat-name { font-size:13px; font-weight:600; color:#f1f5f9; }
        .chat-status { font-size:11px; color:var(--brand); }
        .chat-messages { display:flex; flex-direction:column; gap:10px; }
        .msg { max-width:85%; padding:10px 14px; border-radius:12px; font-size:13px; line-height:1.5; }
        .msg-in { background:#21262d; color:#c9d1d9; border-radius:4px 12px 12px 12px; align-self:flex-start; }
        .msg-out { background:linear-gradient(135deg,var(--brand),var(--brand-dark)); color:#fff; border-radius:12px 4px 12px 12px; align-self:flex-end; }
        .msg-ai { background:rgba(16,185,129,.08); border:1px solid rgba(16,185,129,.15); color:#86efac; border-radius:12px; align-self:flex-start; font-size:12px; }
        .msg-ai-badge { font-size:10px; font-weight:600; color:var(--brand); margin-bottom:4px; text-transform:uppercase; letter-spacing:.5px; }
        .chat-input-bar { margin-top:14px; padding-top:14px; border-top:1px solid #30363d; display:flex; gap:8px; }
        .chat-input-bar input { flex:1; background:#21262d; border:1px solid #30363d; border-radius:8px; padding:8px 12px; color:#c9d1d9; font-size:13px; font-family:inherit; outline:none; }
        .chat-send { width:32px; height:32px; border-radius:8px; background:var(--brand); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; }

        /* ── marquee ── */
        .marquee-wrap { background:var(--brand-xlight); border-top:1px solid var(--brand-light); border-bottom:1px solid var(--brand-light); padding:14px 0; overflow:hidden; }
        .marquee-track { display:flex; width:max-content; animation:marqueeScroll 28s linear infinite; }
        .marquee-item { display:flex; align-items:center; gap:8px; padding:0 32px; font-size:13px; font-weight:600; color:var(--brand-xdark); white-space:nowrap; }
        .marquee-dot { width:5px; height:5px; border-radius:50%; background:var(--brand); flex-shrink:0; }

        /* ── section commons ── */
        .section { padding:80px 0; }
        .section-dark { background:var(--dark); }
        .section-gray { background:var(--bg); }
        .badge-pill { display:inline-flex; align-items:center; gap:6px; background:var(--brand-xlight); color:var(--brand); font-size:12px; font-weight:700; letter-spacing:.4px; padding:4px 12px; border-radius:100px; }
        .section-label { text-align:center; margin-bottom:12px; }
        .section-title { font-size:clamp(26px,3.8vw,40px); font-weight:800; line-height:1.2; text-align:center; }
        .section-sub { text-align:center; color:var(--muted); font-size:16px; margin-top:12px; max-width:540px; margin-inline:auto; }

        /* ── features ── */
        .feat-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:24px; margin-top:56px; }
        @media(max-width:900px) { .feat-grid { grid-template-columns:1fr 1fr; } }
        @media(max-width:560px) { .feat-grid { grid-template-columns:1fr; } }
        .feat-card { background:var(--card); border:1px solid var(--border); border-radius:16px; padding:28px; transition:border-color .25s, box-shadow .25s, transform .25s; }
        .feat-card:hover { border-color:var(--brand-light); box-shadow:0 8px 32px rgba(16,185,129,.1); transform:translateY(-3px); }
        .feat-icon { width:46px; height:46px; border-radius:12px; background:var(--brand-xlight); border:1px solid var(--brand-light); display:flex; align-items:center; justify-content:center; margin-bottom:18px; }
        .feat-name { font-size:16px; font-weight:700; color:var(--text); margin-bottom:8px; }
        .feat-desc { font-size:14px; color:var(--muted); line-height:1.6; }

        /* ── pricing ── */
        .billing-toggle { display:flex; align-items:center; gap:12px; justify-content:center; margin:28px 0 48px; }
        .toggle-label { font-size:14px; font-weight:600; color:var(--muted); cursor:pointer; }
        .toggle-label.active { color:var(--text); }
        .toggle-track { width:44px; height:24px; border-radius:12px; background:#e2e8f0; border:none; cursor:pointer; position:relative; transition:background .3s; padding:0; }
        .toggle-track.on { background:var(--brand); }
        .toggle-thumb { position:absolute; top:2px; left:2px; width:20px; height:20px; border-radius:50%; background:#fff; transition:left .25s; box-shadow:0 1px 4px rgba(0,0,0,.15); }
        .toggle-track.on .toggle-thumb { left:22px; }
        .save-badge { display:inline-flex; align-items:center; background:var(--brand-xlight); color:var(--brand-xdark); font-size:11px; font-weight:700; padding:3px 8px; border-radius:100px; }

        .plans-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:24px; }
        .plan-card { background:var(--card); border:1.5px solid var(--border); border-radius:20px; padding:32px; display:flex; flex-direction:column; position:relative; transition:border-color .25s, box-shadow .25s, transform .25s; }
        .plan-card:hover { border-color:var(--brand-light); box-shadow:0 12px 40px rgba(16,185,129,.12); transform:translateY(-4px); }
        .plan-card.popular { border-color:var(--brand); box-shadow:0 8px 32px rgba(16,185,129,.18); }
        .popular-tag { position:absolute; top:-13px; left:50%; transform:translateX(-50%); background:linear-gradient(135deg,var(--brand),var(--brand-dark)); color:#fff; font-size:11px; font-weight:700; padding:4px 14px; border-radius:100px; white-space:nowrap; }
        .plan-name { font-size:18px; font-weight:800; color:var(--text); margin-bottom:6px; }
        .plan-price { display:flex; align-items:baseline; gap:4px; margin:16px 0; }
        .plan-amount { font-size:40px; font-weight:900; color:var(--text); }
        .plan-curr { font-size:18px; font-weight:600; color:var(--muted); }
        .plan-period { font-size:14px; color:var(--muted); margin-left:2px; }
        .plan-free-price { font-size:32px; font-weight:900; color:var(--text); }
        .plan-desc { font-size:13px; color:var(--muted); margin-bottom:24px; }
        .plan-feats { display:flex; flex-direction:column; gap:10px; margin-bottom:28px; flex:1; }
        .plan-feat { display:flex; align-items:flex-start; gap:10px; font-size:14px; color:var(--muted); }
        .plan-feat-check { width:18px; height:18px; border-radius:50%; background:var(--brand-xlight); display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:1px; }
        .plan-feat-check svg { color:var(--brand); }
        .plan-feat strong { color:var(--text); font-weight:600; }
        .plan-btn { width:100%; padding:12px; border-radius:10px; font-size:15px; font-weight:700; font-family:inherit; cursor:pointer; text-align:center; text-decoration:none; display:block; transition:all .2s; }
        .plan-btn-primary { background:linear-gradient(135deg,var(--brand),var(--brand-dark)); color:#fff; border:none; box-shadow:0 4px 14px rgba(16,185,129,.3); }
        .plan-btn-primary:hover { box-shadow:0 6px 20px rgba(16,185,129,.45); transform:translateY(-1px); }
        .plan-btn-outline { background:transparent; color:var(--brand); border:1.5px solid var(--brand-light); }
        .plan-btn-outline:hover { background:var(--brand-xlight); border-color:var(--brand); }

        /* ── how it works ── */
        .how-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:32px; margin-top:56px; }
        @media(max-width:860px) { .how-grid { grid-template-columns:1fr 1fr; } }
        @media(max-width:480px) { .how-grid { grid-template-columns:1fr; } }
        .how-step { text-align:center; }
        .how-number { width:52px; height:52px; border-radius:50%; background:linear-gradient(135deg,var(--brand),var(--brand-dark)); color:#fff; font-size:20px; font-weight:900; display:flex; align-items:center; justify-content:center; margin:0 auto 18px; box-shadow:0 4px 14px rgba(16,185,129,.3); }
        .how-title { font-size:16px; font-weight:700; color:var(--text); margin-bottom:8px; }
        .how-desc  { font-size:14px; color:var(--muted); line-height:1.6; }

        /* ── faq ── */
        .faq-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:56px; }
        @media(max-width:720px) { .faq-grid { grid-template-columns:1fr; } }
        .faq-item { background:var(--card); border:1px solid var(--border); border-radius:14px; overflow:hidden; }
        .faq-q { width:100%; background:none; border:none; padding:20px 22px; text-align:left; font-size:15px; font-weight:600; color:var(--text); cursor:pointer; display:flex; align-items:center; justify-content:space-between; gap:12px; font-family:inherit; }
        html[dir=rtl] .faq-q { text-align:right; }
        .faq-q:hover { background:var(--brand-xlight); }
        .faq-icon { width:22px; height:22px; border-radius:50%; background:var(--brand-xlight); display:flex; align-items:center; justify-content:center; color:var(--brand); flex-shrink:0; transition:transform .25s, background .2s; }
        .faq-item.open .faq-icon { transform:rotate(45deg); background:var(--brand); color:#fff; }
        .faq-a { max-height:0; overflow:hidden; transition:max-height .35s ease; }
        .faq-item.open .faq-a { max-height:300px; }
        .faq-a-inner { padding:0 22px 20px; font-size:14px; color:var(--muted); line-height:1.7; }

        /* ── cta ── */
        .section-cta { background:var(--dark); position:relative; overflow:hidden; }
        .cta-blob { position:absolute; width:400px; height:400px; border-radius:50%; background:radial-gradient(rgba(16,185,129,.15), transparent 70%); pointer-events:none; }
        .cta-blob-1 { top:-150px; left:-100px; }
        .cta-blob-2 { bottom:-150px; right:-100px; }
        .cta-inner { position:relative; z-index:1; text-align:center; padding:80px 0; }
        .cta-inner h2 { font-size:clamp(28px,4vw,44px); font-weight:900; color:#f1f5f9; margin-bottom:16px; }
        .cta-inner p { font-size:17px; color:#94a3b8; margin-bottom:36px; max-width:520px; margin-inline:auto; }
        .cta-actions { display:flex; gap:14px; justify-content:center; flex-wrap:wrap; }
        .btn-primary-dark { background:linear-gradient(135deg,var(--brand),var(--brand-dark)); color:#fff; font-size:16px; font-weight:700; padding:14px 32px; border-radius:12px; text-decoration:none; border:none; cursor:pointer; font-family:inherit; transition:all .2s; box-shadow:0 4px 18px rgba(16,185,129,.35); }
        .btn-primary-dark:hover { box-shadow:0 6px 24px rgba(16,185,129,.5); transform:translateY(-2px); }
        .btn-ghost-dark { font-size:15px; font-weight:500; color:#94a3b8; text-decoration:none; padding:14px 28px; border-radius:12px; border:1px solid rgba(255,255,255,.1); transition:all .2s; }
        .btn-ghost-dark:hover { color:#f1f5f9; background:rgba(255,255,255,.06); border-color:rgba(255,255,255,.2); }

        /* ── footer ── */
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

        @media(max-width:860px) {
            .nav-links, .nav-mobile-hide { display:none; }
        }
    </style>
</head>
<body>

{{-- ══ NAVBAR ══ --}}
<nav>
    <div class="container">
        <div class="nav-inner">
            <a href="{{ route('landing') }}" class="nav-logo">
                <div class="nav-logo-icon">
                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
                </div>
                {{ config('app.name', 'WA Support') }}
            </a>

            <div class="nav-links">
                <a href="#features">{{ __('landing.nav_features') }}</a>
                <a href="#pricing">{{ __('landing.nav_pricing') }}</a>
                <a href="#how">{{ __('landing.nav_how') }}</a>
                <a href="#faq">{{ __('landing.nav_faq') }}</a>
            </div>

            <div class="nav-cta">
                {{-- Language switcher --}}
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
                <div class="nav-mobile-hide">
                    <a href="{{ route('login') }}" class="btn-ghost">{{ __('landing.nav_login') }}</a>
                </div>
                <a href="{{ route('register') }}" class="btn-primary">{{ __('landing.nav_start') }}</a>
            </div>
        </div>
    </div>
</nav>

{{-- ══ HERO ══ --}}
<section class="hero" id="hero">
    <div class="hero-blob hero-blob-1"></div>
    <div class="hero-blob hero-blob-2"></div>
    <div class="container">
        <div class="hero-inner">
            <div class="hero-left">
                <div class="hero-badge">
                    <svg width="12" height="12" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
                    {{ __('landing.hero_badge') }}
                </div>
                <h1>
                    {{ __('landing.hero_title_1') }}<br>
                    <span>{{ __('landing.hero_title_2') }}</span><br>
                    {{ __('landing.hero_title_3') }}
                </h1>
                <p class="hero-sub">{{ __('landing.hero_sub') }}</p>
                <div class="hero-actions">
                    <a href="{{ route('register') }}" class="btn-primary btn-primary-lg">{{ __('landing.hero_btn_start') }}</a>
                    <a href="#features" class="btn-outline btn-outline-lg" style="color:#94a3b8;border-color:rgba(255,255,255,.15)">{{ __('landing.hero_btn_demo') }}</a>
                </div>
                <div class="hero-trust">
                    <div class="trust-item"><div class="trust-dot"></div>{{ __('landing.trust_no_card') }}</div>
                    <div class="trust-item"><div class="trust-dot"></div>{{ __('landing.trust_trial') }}</div>
                    <div class="trust-item"><div class="trust-dot"></div>{{ __('landing.trust_secure') }}</div>
                    <div class="trust-item"><div class="trust-dot"></div>{{ __('landing.trust_support') }}</div>
                </div>
            </div>

            <div class="hero-right">
                <div class="hero-card">
                    <div class="chat-header">
                        <div class="chat-avatar">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
                        </div>
                        <div>
                            <div class="chat-name">Support WhatsApp</div>
                            <div class="chat-status">● En ligne</div>
                        </div>
                    </div>
                    <div class="chat-messages">
                        <div class="msg msg-in">Bonjour, je voudrais connaître les délais de livraison ?</div>
                        <div class="msg msg-ai">
                            <div class="msg-ai-badge">✦ IA Claude</div>
                            Bonjour ! Nos délais de livraison standard sont de 3 à 5 jours ouvrables. Pour les commandes urgentes, nous proposons une livraison express en 24h. 📦
                        </div>
                        <div class="msg msg-in">Merci ! Et pour les retours ?</div>
                        <div class="msg msg-out">Vous avez 30 jours pour effectuer un retour. Je vous envoie le formulaire.</div>
                    </div>
                    <div class="chat-input-bar">
                        <input type="text" placeholder="Répondre..." disabled>
                        <button class="chat-send" disabled>
                            <svg width="14" height="14" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ══ MARQUEE ══ --}}
<div class="marquee-wrap">
    <div class="marquee-track">
        @php
        $items = ['marquee_ai','marquee_routing','marquee_multiagent','marquee_instances','marquee_realtime','marquee_knowledge','marquee_analytics','marquee_secure'];
        @endphp
        @foreach(array_merge($items, $items) as $key)
        <div class="marquee-item">
            <div class="marquee-dot"></div>
            {{ __('landing.' . $key) }}
        </div>
        @endforeach
    </div>
</div>

{{-- ══ FEATURES ══ --}}
<section class="section" id="features">
    <div class="container">
        <div class="section-label reveal"><span class="badge-pill">{{ __('landing.features_badge') }}</span></div>
        <h2 class="section-title reveal">{{ __('landing.features_title') }}</h2>
        <p class="section-sub reveal">{{ __('landing.features_sub') }}</p>

        <div class="feat-grid stagger">
            <div class="feat-card">
                <div class="feat-icon">
                    <svg width="22" height="22" fill="none" stroke="#10b981" stroke-width="2" viewBox="0 0 24 24"><path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                </div>
                <div class="feat-name">{{ __('landing.feat_ai_name') }}</div>
                <div class="feat-desc">{{ __('landing.feat_ai_desc') }}</div>
            </div>
            <div class="feat-card">
                <div class="feat-icon">
                    <svg width="22" height="22" fill="none" stroke="#10b981" stroke-width="2" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div class="feat-name">{{ __('landing.feat_routing_name') }}</div>
                <div class="feat-desc">{{ __('landing.feat_routing_desc') }}</div>
            </div>
            <div class="feat-card">
                <div class="feat-icon">
                    <svg width="22" height="22" fill="none" stroke="#10b981" stroke-width="2" viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
                </div>
                <div class="feat-name">{{ __('landing.feat_instances_name') }}</div>
                <div class="feat-desc">{{ __('landing.feat_instances_desc') }}</div>
            </div>
            <div class="feat-card">
                <div class="feat-icon">
                    <svg width="22" height="22" fill="none" stroke="#10b981" stroke-width="2" viewBox="0 0 24 24"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <div class="feat-name">{{ __('landing.feat_knowledge_name') }}</div>
                <div class="feat-desc">{{ __('landing.feat_knowledge_desc') }}</div>
            </div>
            <div class="feat-card">
                <div class="feat-icon">
                    <svg width="22" height="22" fill="none" stroke="#10b981" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                </div>
                <div class="feat-name">{{ __('landing.feat_audit_name') }}</div>
                <div class="feat-desc">{{ __('landing.feat_audit_desc') }}</div>
            </div>
            <div class="feat-card">
                <div class="feat-icon">
                    <svg width="22" height="22" fill="none" stroke="#10b981" stroke-width="2" viewBox="0 0 24 24"><path d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                </div>
                <div class="feat-name">{{ __('landing.feat_roles_name') }}</div>
                <div class="feat-desc">{{ __('landing.feat_roles_desc') }}</div>
            </div>
        </div>
    </div>
</section>

{{-- ══ PRICING ══ --}}
<section class="section section-gray" id="pricing">
    <div class="container">
        <div class="section-label reveal"><span class="badge-pill">{{ __('landing.pricing_badge') }}</span></div>
        <h2 class="section-title reveal">{{ __('landing.pricing_title') }}</h2>
        <p class="section-sub reveal">{{ __('landing.pricing_sub') }}</p>

        <div class="billing-toggle reveal" id="billingToggle">
            <span class="toggle-label active" id="lblMonthly">{{ __('landing.billing_monthly') }}</span>
            <button class="toggle-track" id="toggleTrack" type="button" onclick="switchBilling()">
                <span class="toggle-thumb"></span>
            </button>
            <span class="toggle-label" id="lblAnnual">{{ __('landing.billing_annual') }}</span>
            <span class="save-badge">{{ __('landing.billing_save') }}</span>
        </div>

        @if($plans->count())
        <div class="plans-grid stagger">
            @foreach($plans as $i => $plan)
            @php
                $isPopular  = $i === 1 && $plans->count() >= 2;
                $isFree     = !$plan->price_monthly || (float)$plan->price_monthly === 0.0;
                $features   = is_array($plan->features) ? $plan->features : [];
            @endphp
            <div class="plan-card {{ $isPopular ? 'popular' : '' }}">
                @if($isPopular)<div class="popular-tag">{{ __('landing.popular_badge') }}</div>@endif
                <div class="plan-name">{{ $plan->name }}</div>

                @if($isFree)
                    <div class="plan-price"><span class="plan-free-price">{{ __('landing.plan_free_label') }}</span></div>
                @else
                    <div class="plan-price" id="price-{{ $plan->id }}">
                        <span class="plan-curr">TND</span>
                        <span class="plan-amount" data-monthly="{{ number_format((float)$plan->price_monthly, 0) }}" data-annual="{{ $plan->price_annual ? number_format((float)$plan->price_annual/12, 0) : number_format((float)$plan->price_monthly * 0.8, 0) }}">
                            {{ number_format((float)$plan->price_monthly, 0) }}
                        </span>
                        <span class="plan-period monthly-label">{{ __('landing.plan_per_month') }}</span>
                        <span class="plan-period annual-label" style="display:none">{{ __('landing.plan_per_year') }}</span>
                    </div>
                @endif

                <div class="plan-feats">
                    @if($plan->max_users)
                    <div class="plan-feat">
                        <div class="plan-feat-check"><svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg></div>
                        <span><strong>{{ $plan->max_users }}</strong> {{ __('ui.platform_plans_page.users_limit', ['count' => $plan->max_users]) }}</span>
                    </div>
                    @endif
                    @if($plan->max_instances)
                    <div class="plan-feat">
                        <div class="plan-feat-check"><svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg></div>
                        <span><strong>{{ $plan->max_instances }}</strong> {{ $plan->max_instances > 1 ? __('landing.limit_instances_pl', ['n' => $plan->max_instances]) : __('landing.limit_instances', ['n' => 1]) }}</span>
                    </div>
                    @endif
                    @if($plan->max_conversations_per_month)
                    <div class="plan-feat">
                        <div class="plan-feat-check"><svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg></div>
                        <span>{{ __('landing.limit_convos', ['n' => number_format($plan->max_conversations_per_month)]) }}</span>
                    </div>
                    @endif
                    <div class="plan-feat">
                        <div class="plan-feat-check" style="{{ $plan->ai_included ? '' : 'background:#fee2e2' }}">
                            @if($plan->ai_included)
                                <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                            @else
                                <svg width="10" height="10" fill="none" stroke="#ef4444" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                            @endif
                        </div>
                        <span {{ $plan->ai_included ? '' : 'style="color:#94a3b8"' }}>{{ $plan->ai_included ? __('landing.feat_ai_included') : __('landing.feat_ai_not') }}</span>
                    </div>
                    @foreach($features as $feat)
                    <div class="plan-feat">
                        <div class="plan-feat-check"><svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg></div>
                        <span>{{ $feat }}</span>
                    </div>
                    @endforeach
                </div>

                <a href="{{ route('register', ['plan' => $plan->id]) }}" class="plan-btn {{ $isPopular ? 'plan-btn-primary' : 'plan-btn-outline' }}">
                    {{ $isFree ? __('landing.plan_start_btn') : __('landing.plan_subscribe_btn') }}
                </a>
            </div>
            @endforeach
        </div>
        @else
        <div style="text-align:center;padding:60px 0;color:var(--muted)">
            <p style="font-size:16px">Aucun forfait disponible pour le moment. Revenez bientôt.</p>
        </div>
        @endif
    </div>
</section>

{{-- ══ HOW IT WORKS ══ --}}
<section class="section" id="how">
    <div class="container">
        <div class="section-label reveal"><span class="badge-pill">{{ __('landing.how_badge') }}</span></div>
        <h2 class="section-title reveal">{{ __('landing.how_title') }}</h2>
        <p class="section-sub reveal">{{ __('landing.how_sub') }}</p>

        <div class="how-grid stagger">
            <div class="how-step">
                <div class="how-number">1</div>
                <div class="how-title">{{ __('landing.step_1_title') }}</div>
                <p class="how-desc">{{ __('landing.step_1_desc') }}</p>
            </div>
            <div class="how-step">
                <div class="how-number">2</div>
                <div class="how-title">{{ __('landing.step_2_title') }}</div>
                <p class="how-desc">{{ __('landing.step_2_desc') }}</p>
            </div>
            <div class="how-step">
                <div class="how-number">3</div>
                <div class="how-title">{{ __('landing.step_3_title') }}</div>
                <p class="how-desc">{{ __('landing.step_3_desc') }}</p>
            </div>
            <div class="how-step">
                <div class="how-number">4</div>
                <div class="how-title">{{ __('landing.step_4_title') }}</div>
                <p class="how-desc">{{ __('landing.step_4_desc') }}</p>
            </div>
        </div>
    </div>
</section>

{{-- ══ FAQ ══ --}}
<section class="section section-gray" id="faq">
    <div class="container">
        <div class="section-label reveal"><span class="badge-pill">{{ __('landing.faq_badge') }}</span></div>
        <h2 class="section-title reveal">{{ __('landing.faq_title') }}</h2>
        <p class="section-sub reveal">{{ __('landing.faq_sub') }}</p>

        <div class="faq-grid reveal">
            @for($q = 1; $q <= 6; $q++)
            <div class="faq-item" onclick="toggleFaq(this)">
                <button class="faq-q" type="button">
                    {{ __('landing.faq_' . $q . '_q') }}
                    <div class="faq-icon">
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                    </div>
                </button>
                <div class="faq-a"><p class="faq-a-inner">{{ __('landing.faq_' . $q . '_a') }}</p></div>
            </div>
            @endfor
        </div>
    </div>
</section>

{{-- ══ CTA ══ --}}
<section class="section section-cta">
    <div class="cta-blob cta-blob-1"></div>
    <div class="cta-blob cta-blob-2"></div>
    <div class="container">
        <div class="cta-inner reveal">
            <h2>{{ __('landing.cta_title') }}</h2>
            <p>{{ __('landing.cta_sub') }}</p>
            <div class="cta-actions">
                <a href="{{ route('register') }}" class="btn-primary-dark">{{ __('landing.cta_btn') }}</a>
                <a href="{{ route('login') }}" class="btn-ghost-dark">{{ __('landing.cta_login') }}</a>
            </div>
        </div>
    </div>
</section>

{{-- ══ FOOTER ══ --}}
<footer class="footer">
    <div class="container">
        <div class="footer-inner">
            <div class="footer-brand">
                <a href="{{ route('landing') }}" class="logo">
                    <div class="nav-logo-icon" style="width:30px;height:30px;border-radius:7px;background:linear-gradient(135deg,#10b981,#059669);display:flex;align-items:center;justify-content:center;color:#fff">
                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
                    </div>
                    {{ config('app.name', 'WA Support') }}
                </a>
                <p>{{ __('landing.footer_tagline') }}</p>
            </div>
            <div class="footer-col">
                <h4>{{ __('landing.footer_product') }}</h4>
                <a href="#features">{{ __('landing.footer_features') }}</a>
                <a href="#pricing">{{ __('landing.footer_pricing') }}</a>
                <a href="#how">{{ __('landing.footer_how') }}</a>
            </div>
            <div class="footer-col">
                <h4>{{ __('landing.footer_company') }}</h4>
                <a href="{{ route('login') }}">{{ __('landing.nav_login') }}</a>
                <a href="{{ route('register') }}">{{ __('landing.nav_start') }}</a>
            </div>
            <div class="footer-col">
                <h4>{{ __('landing.footer_legal') }}</h4>
                <a href="#">{{ __('landing.footer_privacy') }}</a>
                <a href="#">{{ __('landing.footer_terms') }}</a>
            </div>
        </div>
        <div class="footer-bottom">
            {{ __('landing.footer_copyright', ['year' => date('Y'), 'app' => config('app.name', 'WA Support')]) }}
        </div>
    </div>
</footer>

<script>
// ── scroll reveal ──
const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => {
        if (e.isIntersecting) { e.target.classList.add('visible'); observer.unobserve(e.target); }
    });
}, { threshold: 0.1 });
document.querySelectorAll('.reveal, .stagger').forEach(el => observer.observe(el));

// ── lang dropdown ──
const langBtn = document.getElementById('langBtn');
const langDD  = document.getElementById('langDropdown');
langBtn.addEventListener('click', (e) => { e.stopPropagation(); langDD.classList.toggle('open'); });
document.addEventListener('click', () => langDD.classList.remove('open'));
langDD.addEventListener('click', e => e.stopPropagation());

// ── faq ──
function toggleFaq(item) {
    const wasOpen = item.classList.contains('open');
    document.querySelectorAll('.faq-item.open').forEach(i => i.classList.remove('open'));
    if (!wasOpen) item.classList.add('open');
}

// ── billing toggle ──
let isAnnual = false;
function switchBilling() {
    isAnnual = !isAnnual;
    const track = document.getElementById('toggleTrack');
    const lblM  = document.getElementById('lblMonthly');
    const lblA  = document.getElementById('lblAnnual');
    track.classList.toggle('on', isAnnual);
    lblM.classList.toggle('active', !isAnnual);
    lblA.classList.toggle('active', isAnnual);

    document.querySelectorAll('.plan-amount').forEach(el => {
        el.textContent = isAnnual ? el.dataset.annual : el.dataset.monthly;
    });
    document.querySelectorAll('.monthly-label').forEach(el => el.style.display = isAnnual ? 'none' : '');
    document.querySelectorAll('.annual-label').forEach(el => el.style.display = isAnnual ? '' : 'none');
}
</script>
</body>
</html>
