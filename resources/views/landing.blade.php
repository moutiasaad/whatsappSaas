@php
    $isRtl     = (bool) data_get(config('locales.supported', []), app()->getLocale() . '.rtl');
    $trialDays = (int) config('app.trial_days', 14);
    $planCount = $plans->count();
    $isAuthed  = !empty($homeRoute);
    // Signed-in visitors get one CTA — back to their own panel.
    $ctaUrl    = $homeRoute ?? route('register');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('landing.page_title') }}</title>
<meta name="description" content="{{ __('landing.page_desc') }}">
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --teal:#0f7e7a;--teal-l:#15b6a8;--teal-d:#0a5e5b;--teal-50:#ecf7f6;--teal-100:#d6efed;
  --ink:#0d1417;--ink-2:#161e22;--ink-3:#232b33;
  --text:#0f172a;--muted:#64748b;--muted-2:#94a3b8;
  --border:#e2e8f0;--border-2:#cbd5e1;--soft:#f8fafc;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;font-family:"Outfit",ui-sans-serif,system-ui,sans-serif;color:var(--text);-webkit-font-smoothing:antialiased;background:#fff;line-height:1.5;overflow-x:hidden}
html[dir="rtl"] body{font-family:"Cairo",ui-sans-serif,system-ui,sans-serif;line-height:1.65}
a{color:var(--teal);text-decoration:none}a:hover{color:var(--teal-d)}
h1,h2,h3{margin:0;letter-spacing:-.035em;font-weight:700;text-wrap:pretty}
p{margin:0}
img,svg{max-width:100%}
button,input{font-family:inherit}
.wrap{max-width:1140px;margin:0 auto;padding:0 28px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;height:50px;padding:0 24px;border-radius:11px;font-size:15.5px;font-weight:600;cursor:pointer;border:1px solid transparent;transition:.15s;white-space:nowrap}
.btn.p{background:var(--teal);color:#fff}
.btn.p:hover{background:var(--teal-d);color:#fff}
.btn.g{background:#fff;color:var(--text);border-color:var(--border)}
.btn.g:hover{border-color:var(--border-2);background:var(--soft);color:var(--text)}
.btn.d{background:rgba(255,255,255,.08);color:#fff;border-color:rgba(255,255,255,.2)}
.btn.d:hover{background:rgba(255,255,255,.14);color:#fff}
.eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:var(--teal);background:var(--teal-50);border:1px solid var(--teal-100);padding:7px 14px;border-radius:999px}
.eyebrow.dark{color:var(--teal-l);background:rgba(21,182,168,.12);border-color:rgba(21,182,168,.24)}
.sechead{text-align:center;max-width:640px;margin:0 auto 52px}
.sechead h2{font-size:40px;line-height:1.12}
.sechead p{font-size:17.5px;color:var(--muted);margin-top:14px;line-height:1.55}
section{padding:96px 0}

/* nav */
.nav{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.9);backdrop-filter:blur(14px);border-bottom:1px solid var(--border)}
.nav .in{height:70px;display:flex;align-items:center;gap:32px}
.brand{display:flex;align-items:center;gap:11px;color:var(--text)}
.brand:hover{color:var(--text)}
.brand .wm{font-weight:800;font-size:20px;letter-spacing:-.03em;color:var(--text)}
.nav nav{display:flex;gap:26px;margin-inline-start:14px}
.nav nav a{font-size:14.5px;color:var(--muted);font-weight:500}
.nav nav a:hover{color:var(--text)}
.nav .act{margin-inline-start:auto;display:flex;align-items:center;gap:14px}
.nav .act a.si{font-size:14.5px;color:var(--muted);font-weight:500}
.nav .btn{height:42px;font-size:14.5px;padding:0 18px}

/* lang */
.lang-wrap{position:relative}
.lang{display:flex;align-items:center;gap:6px;font-size:13.5px;color:var(--muted);border:1px solid var(--border);padding:6px 10px;border-radius:8px;cursor:pointer;background:#fff;font-weight:500}
.lang:hover{border-color:var(--border-2)}
.lang-dd{position:absolute;top:calc(100% + 8px);inset-inline-end:0;background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 14px 34px -18px rgba(15,23,42,.35);min-width:150px;overflow:hidden;z-index:200;display:none}
.lang-dd.open{display:block}
.lang-item{display:block;width:100%;background:none;border:none;padding:10px 14px;font-size:13.5px;cursor:pointer;color:var(--text);text-align:start}
.lang-item:hover{background:var(--teal-50)}
.lang-item.active{font-weight:600;color:var(--teal)}

/* hero */
.hero{background:var(--ink);color:#fff;padding:84px 0 96px;position:relative;overflow:hidden}
.hero .glow{position:absolute;width:1100px;height:1100px;border-radius:50%;background:radial-gradient(circle,rgba(21,182,168,.15),transparent 60%);top:-460px;inset-inline-end:-320px;pointer-events:none}
.hero .spine{position:absolute;inset-inline-start:-80px;bottom:-140px;opacity:.07;pointer-events:none}
html[dir="rtl"] .hero .spine{transform:scaleX(-1)}
.hero .in{position:relative;display:grid;grid-template-columns:1.08fr .92fr;gap:64px;align-items:center}
.hero h1{font-size:60px;line-height:1.05;margin-top:24px}
.hero h1 em{font-style:normal;color:var(--teal-l)}
.hero .lede{font-size:19px;color:#94a3b8;margin-top:20px;max-width:46ch;line-height:1.6}
.capture{display:flex;gap:10px;margin-top:32px;max-width:480px}
.capture input{flex:1;height:54px;border-radius:12px;border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.06);color:#fff;padding:0 16px;font-size:15.5px;outline:none;transition:.15s;min-width:0}
.capture input::placeholder{color:#7b8894}
.capture input:focus{border-color:var(--teal-l);background:rgba(255,255,255,.09);box-shadow:0 0 0 3px rgba(21,182,168,.16)}
.capture .btn{height:54px;flex-shrink:0}
.microtrust{display:flex;gap:18px;margin-top:16px;flex-wrap:wrap}
.microtrust span{display:flex;align-items:center;gap:7px;font-size:13.5px;color:#8b99a6}

/* hero card */
.hcard{background:var(--ink-2);border:1px solid #263038;border-radius:18px;overflow:hidden;box-shadow:0 50px 110px -50px rgba(0,0,0,.9)}
.hcard .hh{display:flex;align-items:center;gap:10px;padding:15px 18px;border-bottom:1px solid #263038;font-size:13.5px;font-weight:600}
.hcard .hh .live{margin-inline-start:auto;font-size:11.5px;color:#8b99a6;display:flex;align-items:center;gap:6px;font-weight:400;text-align:end}
.hcard .hh .live i{width:6px;height:6px;border-radius:50%;background:var(--teal-l);animation:blink 1.8s infinite;flex-shrink:0}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.35}}
.thread{padding:20px 18px;display:flex;flex-direction:column;gap:12px}
.bub{max-width:84%;padding:12px 15px;border-radius:15px;font-size:14px;line-height:1.5}
.bub .who{display:block;font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;opacity:.55;margin-bottom:5px}
.bub.them{background:var(--ink-3);color:#e6edf3;align-self:flex-start;border-end-start-radius:5px}
.bub.ai{background:var(--teal);color:#fff;align-self:flex-end;border-end-end-radius:5px}
.bub.ai .who{opacity:.75}
.hcard .hf{display:flex;align-items:center;gap:9px;padding:13px 18px;border-top:1px solid #263038;font-size:12.5px;color:#8b99a6}
.hcard .hf .src{background:rgba(21,182,168,.14);color:var(--teal-l);padding:4px 9px;border-radius:6px;font-weight:600;font-size:11.5px;white-space:nowrap}

/* strip */
.strip{padding:30px 0;border-bottom:1px solid var(--border);background:var(--soft)}
.strip .in{display:flex;align-items:center;justify-content:center;gap:44px;flex-wrap:wrap}
.strip .k{text-align:center}
.strip .k b{display:block;font-size:26px;font-weight:700;letter-spacing:-.03em;color:var(--text)}
.strip .k span{font-size:13.5px;color:var(--muted)}

/* before after */
.ba{display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:900px;margin:0 auto}
.bacol{border-radius:18px;padding:32px;border:1px solid}
.bacol.b{background:var(--soft);border-color:var(--border)}
.bacol.a{background:var(--teal-50);border-color:var(--teal-100)}
.bacol .tag{font-size:11.5px;font-weight:700;letter-spacing:.13em;text-transform:uppercase;display:flex;align-items:center;gap:8px}
.bacol.b .tag{color:var(--muted)}
.bacol.a .tag{color:var(--teal)}
.bacol ul{list-style:none;margin:20px 0 0;padding:0;display:flex;flex-direction:column;gap:14px}
.bacol li{font-size:15.5px;line-height:1.45;display:flex;gap:11px;align-items:flex-start}
.bacol.b li{color:var(--muted)}
.bacol.a li{color:var(--text)}
.bacol li svg{flex-shrink:0;margin-top:3px}

/* steps */
.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}
.step{background:#fff;border:1px solid var(--border);border-radius:18px;padding:30px}
.step .n{width:34px;height:34px;border-radius:10px;background:var(--teal);color:#fff;font-size:15px;font-weight:700;display:grid;place-items:center}
.step h3{font-size:19.5px;margin-top:18px}
.step p{font-size:15px;color:var(--muted);margin-top:9px;line-height:1.55}

/* features */
.feats{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
.feat{border:1px solid var(--border);border-radius:16px;padding:26px;background:#fff;transition:.16s}
.feat:hover{border-color:var(--teal-100);box-shadow:0 12px 32px -18px rgba(15,126,122,.3)}
.feat .ic{width:42px;height:42px;border-radius:11px;background:var(--teal-50);display:grid;place-items:center}
.feat h3{font-size:17.5px;margin-top:16px}
.feat p{font-size:14.5px;color:var(--muted);margin-top:8px;line-height:1.55}

/* pricing */
.pricing{background:var(--soft);border-top:1px solid var(--border);border-bottom:1px solid var(--border)}
.billing{display:flex;align-items:center;justify-content:center;gap:12px;margin:-24px auto 40px;flex-wrap:wrap}
.billing .lbl{font-size:14.5px;font-weight:600;color:var(--muted-2);cursor:pointer}
.billing .lbl.on{color:var(--text)}
.track{width:50px;height:28px;border-radius:999px;background:var(--border-2);border:none;position:relative;cursor:pointer;transition:.2s;padding:0;flex-shrink:0}
.track.on{background:var(--teal)}
.track i{position:absolute;top:3px;inset-inline-start:3px;width:22px;height:22px;border-radius:50%;background:#fff;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.2)}
.track.on i{inset-inline-start:25px}
.billing .save{font-size:11.5px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--teal);background:var(--teal-50);border:1px solid var(--teal-100);padding:4px 10px;border-radius:999px}
.plans{display:grid;grid-template-columns:repeat({{ min(max($planCount, 1), 3) }},1fr);gap:20px;max-width:{{ $planCount >= 3 ? '1140px' : ($planCount === 2 ? '780px' : '400px') }};margin:0 auto}
.plan{background:#fff;border:1px solid var(--border);border-radius:18px;padding:32px;position:relative;display:flex;flex-direction:column}
.plan.hot{border-color:var(--teal);box-shadow:0 20px 50px -28px rgba(15,126,122,.4)}
.plan .badge{position:absolute;top:-11px;inset-inline-start:32px;background:var(--teal);color:#fff;font-size:11.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:5px 12px;border-radius:999px}
.plan .pn{font-size:19px;font-weight:700}
.plan .pp{font-size:42px;font-weight:700;letter-spacing:-.04em;margin-top:10px;line-height:1.1}
.plan .pp span{font-size:16px;font-weight:500;color:var(--muted);letter-spacing:0}
.plan .pd{font-size:14.5px;color:var(--muted);margin-top:6px}
.plan ul{list-style:none;margin:22px 0 0;padding:0;display:flex;flex-direction:column;gap:11px}
.plan li{font-size:14.5px;display:flex;gap:10px;align-items:flex-start;line-height:1.45}
.plan li svg{flex-shrink:0;margin-top:2px}
.plan li.off{color:var(--muted-2)}
.plan .btn{width:100%}
.plan .btnwrap{margin-top:auto;padding-top:24px;display:flex}
.pricenote{text-align:center;font-size:14.5px;color:var(--muted);margin-top:28px}

/* faq */
.faq{max-width:760px;margin:0 auto}
details{border:1px solid var(--border);border-radius:13px;margin-bottom:11px;background:#fff;overflow:hidden}
details[open]{border-color:var(--teal-100)}
summary{padding:19px 22px;font-size:16px;font-weight:600;cursor:pointer;list-style:none;display:flex;align-items:center;gap:14px}
summary::-webkit-details-marker{display:none}
summary::after{content:"";width:9px;height:9px;border-right:2px solid var(--muted-2);border-bottom:2px solid var(--muted-2);transform:rotate(45deg);margin-inline-start:auto;transition:.18s;flex-shrink:0}
details[open] summary::after{transform:rotate(-135deg)}
details .a{padding:0 22px 20px;font-size:15px;color:var(--muted);line-height:1.6}

/* final */
.final{background:var(--ink);color:#fff;text-align:center;position:relative;overflow:hidden}
.final .glow{position:absolute;width:900px;height:900px;border-radius:50%;background:radial-gradient(circle,rgba(21,182,168,.16),transparent 62%);bottom:-540px;left:50%;transform:translateX(-50%);pointer-events:none}
.final .in{position:relative}
.final h2{font-size:44px;line-height:1.1;max-width:18ch;margin:0 auto}
.final p{font-size:18px;color:#94a3b8;margin-top:16px}
.final .capture{margin:32px auto 0;justify-content:center}
.final .microtrust{justify-content:center}

/* footer */
footer{background:var(--ink);color:#8b99a6;padding:40px 0;border-top:1px solid #1f272e}
footer .in{display:flex;align-items:center;gap:20px;flex-wrap:wrap;font-size:13.5px}
footer .wm{color:#fff;font-weight:800;font-size:17px;letter-spacing:-.03em}
footer nav{margin-inline-start:auto;display:flex;gap:22px;flex-wrap:wrap}
footer a{color:#8b99a6}footer a:hover{color:#fff}

/* sticky mobile CTA */
.mobcta{position:fixed;bottom:0;left:0;right:0;z-index:60;background:rgba(255,255,255,.96);backdrop-filter:blur(14px);border-top:1px solid var(--border);padding:12px 16px calc(12px + env(safe-area-inset-bottom));display:none;gap:12px;align-items:center;transform:translateY(110%);transition:transform .28s cubic-bezier(.4,0,.2,1)}
.mobcta.show{transform:translateY(0)}
.mobcta .t{flex:1;min-width:0;line-height:1.25}
.mobcta .t b{display:block;font-size:14px;font-weight:700}
.mobcta .t span{font-size:12.5px;color:var(--muted)}
.mobcta .btn{height:46px;padding:0 20px;font-size:15px}

@media (max-width:1100px){
  .hero h1{font-size:52px}
  .plans{grid-template-columns:repeat({{ min(max($planCount, 1), 2) }},1fr);max-width:{{ $planCount >= 2 ? '780px' : '400px' }}}
  .feats,.steps{grid-template-columns:repeat(2,1fr)}
}
@media (max-width:980px){
  .hero .in{grid-template-columns:1fr;gap:44px}
  .hero h1{font-size:44px}
  .sechead h2{font-size:32px}
  .steps,.feats,.ba,.plans{grid-template-columns:1fr;max-width:520px;margin-inline:auto}
  .nav nav{display:none}
  section{padding:64px 0}
  .capture{flex-direction:column;max-width:none}
  .final h2{font-size:32px}
  .mobcta{display:flex}
  footer{padding-bottom:96px}
  footer nav{margin-inline-start:0;width:100%}
}
@media (max-width:620px){
  .wrap{padding:0 18px}
  .hero{padding:56px 0 68px}
  .hero h1{font-size:34px}
  .hero .lede{font-size:16.5px}
  .sechead{margin-bottom:36px}
  .sechead h2{font-size:27px}
  .sechead p{font-size:15.5px}
  .strip .in{gap:22px}
  .strip .k{flex:1 1 40%}
  .strip .k b{font-size:22px}
  .bacol,.step,.plan{padding:24px}
  .final h2{font-size:27px}
  .nav .act a.si{display:none}
  .bub{max-width:92%}
}
</style>
</head>
<body>

{{-- ══════════ NAV ══════════ --}}
<div class="nav"><div class="wrap in">
  <a class="brand" href="#top">
    <svg width="34" height="34" viewBox="0 0 512 512" fill="none" aria-hidden="true"><rect x="7" y="7" width="498" height="498" rx="118" fill="#0f7e7a"/><g transform="translate(256,256) scale(.8) translate(-284,-267)"><path d="M 96 326 C 162 326, 162 184, 240 184 C 320 184, 320 350, 388 350 C 432 350, 432 226, 472 226" stroke="#fff" stroke-width="46" stroke-linecap="round" fill="none"/><circle cx="96" cy="326" r="34" fill="#fff"/><circle cx="472" cy="226" r="34" fill="#d6efed"/></g></svg>
    <span class="wm">{{ config('app.name', 'wavadesk') }}</span>
  </a>
  <nav>
    <a href="#how">{{ __('landing.nav_how') }}</a>
    <a href="#features">{{ __('landing.nav_features') }}</a>
    <a href="#pricing">{{ __('landing.nav_pricing') }}</a>
    <a href="#faq">{{ __('landing.nav_faq') }}</a>
  </nav>
  <div class="act">
    <div class="lang-wrap">
      <button class="lang" id="langBtn" type="button" aria-haspopup="true" aria-expanded="false">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9.5" stroke="currentColor" stroke-width="1.6"/><path d="M3 12h18M12 2.5c2.5 3 2.5 16 0 19M12 2.5c-2.5 3-2.5 16 0 19" stroke="currentColor" stroke-width="1.6"/></svg>
        {{ strtoupper(app()->getLocale()) }}
      </button>
      <div class="lang-dd" id="langDropdown">
        @foreach(config('locales.supported', []) as $code => $meta)
        <form method="POST" action="{{ route('locale.update') }}" style="margin:0">
          @csrf
          <input type="hidden" name="locale" value="{{ $code }}">
          <input type="hidden" name="redirect" value="{{ url()->full() }}">
          <button type="submit" class="lang-item {{ app()->getLocale() === $code ? 'active' : '' }}">{{ $meta['native'] ?? $code }}</button>
        </form>
        @endforeach
      </div>
    </div>
    @if($isAuthed)
      <a class="btn p" href="{{ $homeRoute }}">{{ __('landing.go_to_dashboard') }}</a>
    @else
      <a class="si" href="{{ route('login') }}">{{ __('landing.nav_login') }}</a>
      <a class="btn p" href="{{ route('register') }}">{{ __('landing.hero_cta') }}</a>
    @endif
  </div>
</div></div>

{{-- ══════════ HERO ══════════ --}}
<div class="hero" id="top">
  <div class="glow"></div>
  <svg class="spine" width="900" height="520" viewBox="0 0 900 520" fill="none" aria-hidden="true"><path d="M 0 380 C 140 380, 140 150, 300 150 C 460 150, 460 430, 620 430 C 750 430, 750 200, 900 200" stroke="#15b6a8" stroke-width="76" stroke-linecap="round" fill="none"/></svg>
  <div class="wrap in">
    <div>
      <span class="eyebrow dark">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z" fill="currentColor"/></svg>
        {{ __('landing.hero_pill', ['days' => $trialDays]) }}
      </span>
      <h1>{!! __('landing.hero_h1') !!}</h1>
      <p class="lede">{{ __('landing.hero_lede') }}</p>

      @if($isAuthed)
      <div class="capture" id="hero-form">
        <a class="btn p" href="{{ $homeRoute }}">{{ __('landing.go_to_dashboard') }}</a>
      </div>
      @else
      <form class="capture" id="hero-form" method="GET" action="{{ route('register') }}">
        <input type="email" name="email" placeholder="{{ __('auth.login.placeholder_email') }}" aria-label="{{ __('auth.register.email') }}" required>
        <button class="btn p" type="submit">{{ __('landing.hero_cta') }}</button>
      </form>
      @endif

      <div class="microtrust">
        <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><rect x="2.5" y="5" width="19" height="14" rx="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M2.5 10h19" stroke="currentColor" stroke-width="1.8"/></svg>{{ __('landing.trust_no_card') }}</span>
        <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 2l8 4v6c0 5-3.4 8.8-8 10-4.6-1.2-8-5-8-10V6l8-4z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>{{ __('landing.trust_own_number') }}</span>
        <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9.5" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v5.5l3.5 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>{{ __('landing.trust_fast_setup') }}</span>
      </div>
    </div>

    <div class="hcard">
      <div class="hh">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z" fill="#15b6a8"/></svg>
        {{ __('landing.card_title') }}
        <span class="live"><i></i>{{ __('landing.card_live') }}</span>
      </div>
      <div class="thread">
        <div class="bub them"><span class="who">{{ __('landing.who_customer') }}</span>{{ __('landing.chat_msg1') }}</div>
        <div class="bub ai"><span class="who">{{ __('landing.who_ai') }}</span>{{ __('landing.chat_ai_reply') }}</div>
        <div class="bub them"><span class="who">{{ __('landing.who_customer') }}</span>{{ __('landing.card_msg2') }}</div>
      </div>
      <div class="hf"><span class="src">{{ __('landing.card_escalated') }}</span>{{ __('landing.card_foot') }}</div>
    </div>
  </div>
</div>

{{-- ══════════ STRIP ══════════ --}}
<div class="strip"><div class="wrap in">
  @foreach([1,2,3,4] as $k)
  <div class="k"><b>{{ __('landing.k' . $k . '_v') }}</b><span>{{ __('landing.k' . $k . '_l') }}</span></div>
  @endforeach
</div></div>

{{-- ══════════ PROBLEM ══════════ --}}
<section>
  <div class="wrap">
    <div class="sechead">
      <h2>{{ __('landing.prob_title') }}</h2>
      <p>{{ __('landing.prob_sub') }}</p>
    </div>
    <div class="ba">
      <div class="bacol b">
        <div class="tag"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9.5" stroke="currentColor" stroke-width="1.8"/><path d="M15 9l-6 6M9 9l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>{{ __('landing.today_tag') }}</div>
        <ul>
          @foreach([1,2,3,4] as $i)
          <li><svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/></svg>{{ __('landing.today_' . $i) }}</li>
          @endforeach
        </ul>
      </div>
      <div class="bacol a">
        <div class="tag"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9.5" stroke="currentColor" stroke-width="1.8"/><path d="M8 12.5l2.5 2.5L16 9.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('landing.with_tag') }}</div>
        <ul>
          @foreach([1,2,3,4] as $i)
          <li><svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M20 6 9 17l-5-5" stroke="#0f7e7a" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('landing.with_' . $i) }}</li>
          @endforeach
        </ul>
      </div>
    </div>
  </div>
</section>

{{-- ══════════ HOW ══════════ --}}
<section id="how" style="background:var(--soft);border-top:1px solid var(--border);border-bottom:1px solid var(--border)">
  <div class="wrap">
    <div class="sechead">
      <h2>{{ __('landing.how2_title') }}</h2>
      <p>{{ __('landing.how2_sub') }}</p>
    </div>
    <div class="steps">
      @foreach([1,2,3] as $i)
      <div class="step">
        <div class="n">{{ $i }}</div>
        <h3>{{ __('landing.hstep_' . $i . '_t') }}</h3>
        <p>{{ __('landing.hstep_' . $i . '_d') }}</p>
      </div>
      @endforeach
    </div>
    <div style="text-align:center;margin-top:44px"><a class="btn p" href="{{ $ctaUrl }}">{{ $isAuthed ? __('landing.go_to_dashboard') : __('landing.how_cta', ['days' => $trialDays]) }}</a></div>
  </div>
</section>

{{-- ══════════ FEATURES ══════════ --}}
<section id="features">
  <div class="wrap">
    <div class="sechead">
      <h2>{{ __('landing.feats_title') }}</h2>
      <p>{{ __('landing.feats_sub') }}</p>
    </div>
    @php
      $featIcons = [
        '<path d="M3 6h18M3 12h18M3 18h11" stroke="#0f7e7a" stroke-width="2.2" stroke-linecap="round"/>',
        '<path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z" fill="#0f7e7a"/>',
        '<path d="M12 3v6M12 15v6M3 12h6M15 12h6" stroke="#0f7e7a" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="12" r="2.6" stroke="#0f7e7a" stroke-width="2.2"/>',
        '<path d="M12 8v5l3 2" stroke="#0f7e7a" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="12" r="9" stroke="#0f7e7a" stroke-width="2.2"/>',
        '<path d="M9 12l2 2 4-4" stroke="#0f7e7a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><rect x="3.5" y="3.5" width="17" height="17" rx="4" stroke="#0f7e7a" stroke-width="2.2"/>',
        '<rect x="3.5" y="3.5" width="7" height="7" rx="1.6" stroke="#0f7e7a" stroke-width="2.2"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.6" stroke="#0f7e7a" stroke-width="2.2"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.6" stroke="#0f7e7a" stroke-width="2.2"/><path d="M14 14h3v3M20 17v3h-3" stroke="#0f7e7a" stroke-width="2.2" stroke-linecap="round"/>',
      ];
    @endphp
    <div class="feats">
      @foreach($featIcons as $i => $icon)
      <div class="feat">
        <div class="ic"><svg width="21" height="21" viewBox="0 0 24 24" fill="none">{!! $icon !!}</svg></div>
        <h3>{{ __('landing.f' . ($i + 1) . '_t') }}</h3>
        <p>{{ __('landing.f' . ($i + 1) . '_d') }}</p>
      </div>
      @endforeach
    </div>
  </div>
</section>

{{-- ══════════ PRICING ══════════ --}}
<section class="pricing" id="pricing">
  <div class="wrap">
    <div class="sechead">
      <h2>{{ __('landing.pricing2_title') }}</h2>
      <p>{{ __('landing.pricing2_sub', ['days' => $trialDays]) }}</p>
    </div>

    @if($planCount)
    @php
        // CALC-013: only show the Monthly/Annual toggle when at least one
        // active plan actually has an annual price. Otherwise the toggle
        // implies a cycle no plan offers, and per-plan fallbacks would render
        // the monthly price under a yearly label — the twelve-fold error
        // CALC-002 corrected, reintroduced through the fallback rather than
        // the division.
        $anyPlanHasAnnual = $plans->contains(fn ($p) => (float) $p->price_annual > 0);
    @endphp
    @if($anyPlanHasAnnual)
    {{-- CALC-012: the "Save 20%" badge was a hardcoded literal, not derived
         from any plan's price_annual vs price_monthly × 12. Combined with
         CALC-003 (annual billing is not wired end-to-end), the page was
         advertising a quantified discount on a cycle the platform cannot
         actually sell. Restore only when CALC-003 ships AND the badge is
         computed per plan (e.g. round((1 - annual / (monthly * 12)) * 100)). --}}
    <div class="billing" id="billing">
      <span class="lbl on" id="lblMonthly">{{ __('landing.billing_monthly') }}</span>
      <button class="track" id="track" type="button" aria-label="{{ __('landing.billing_annual') }}"><i></i></button>
      <span class="lbl" id="lblAnnual">{{ __('landing.billing_annual') }}</span>
    </div>
    @endif

    <div class="plans">
      @foreach($plans as $i => $plan)
      @php
        $isPopular  = $i === 1 && $planCount >= 2;
        $isFree     = !$plan->price_monthly || (float) $plan->price_monthly === 0.0;
        $features   = is_array($plan->features) ? $plan->features : [];
        // CALC-013: 0 and null both mean "no annual price offered". Do not
        // substitute one cycle's price for the other.
        $hasAnnual  = (float) $plan->price_annual > 0;
      @endphp
      <div class="plan {{ $isPopular ? 'hot' : '' }}">
        @if($isPopular)<span class="badge">{{ __('landing.popular_short') }}</span>@endif
        <div class="pn">{{ $plan->name }}</div>
        @if($isFree)
          <div class="pp">{{ __('landing.plan_free_label') }}</div>
        @else
          <div class="pp">
            $<span class="plan-amount" data-monthly="{{ number_format((float) $plan->price_monthly, 0) }}"@if($hasAnnual) data-annual="{{ number_format((float) $plan->price_annual, 0) }}"@endif>{{ number_format((float) $plan->price_monthly, 0) }}</span><span class="monthly-label">{{ __('landing.plan_per_month') }}</span>@if($hasAnnual)<span class="annual-label" style="display:none">{{ __('landing.plan_per_year') }}</span>@endif
          </div>
        @endif
        <div class="pd">{{ __('landing.plan_tagline_' . ($isPopular ? 'growth' : 'starter')) }}</div>

        <ul>
          @if($plan->max_users)
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M20 6 9 17l-5-5" stroke="#0f7e7a" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('ui.platform_plans_page.users_limit', ['count' => $plan->max_users]) }}</li>
          @endif
          @if($plan->max_conversations_per_month)
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M20 6 9 17l-5-5" stroke="#0f7e7a" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('landing.limit_convos', ['n' => number_format($plan->max_conversations_per_month)]) }}</li>
          @endif
          <li class="{{ $plan->ai_included ? '' : 'off' }}">
            @if($plan->ai_included)
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M20 6 9 17l-5-5" stroke="#0f7e7a" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('landing.feat_ai_included') }}
            @else
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="#94a3b8" stroke-width="2.2" stroke-linecap="round"/></svg>{{ __('landing.feat_ai_not') }}
            @endif
          </li>
          @foreach($features as $feat)
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M20 6 9 17l-5-5" stroke="#0f7e7a" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ $feat }}</li>
          @endforeach
        </ul>

        <div class="btnwrap">
          <a class="btn {{ $isPopular ? 'p' : 'g' }}" href="{{ $isAuthed ? $homeRoute : route('register', ['plan' => $plan->id]) }}">{{ $isAuthed ? __('landing.go_to_dashboard') : ($isFree ? __('landing.plan_start_btn') : __('landing.plan_subscribe_btn')) }}</a>
        </div>
      </div>
      @endforeach
    </div>
    <p class="pricenote">{{ __('landing.price_note', ['days' => $trialDays]) }}</p>
    @else
    <p class="pricenote">{{ __('landing.no_plans') }}</p>
    @endif
  </div>
</section>

{{-- ══════════ FAQ ══════════ --}}
<section id="faq">
  <div class="wrap">
    <div class="sechead">
      <h2>{{ __('landing.faq_title') }}</h2>
      <p>{{ __('landing.faq_sub') }}</p>
    </div>
    <div class="faq">
      @for($q = 1; $q <= 6; $q++)
      <details {{ $q === 1 ? 'open' : '' }}>
        <summary>{{ __('landing.faq_' . $q . '_q') }}</summary>
        <div class="a">{{ __('landing.faq_' . $q . '_a', ['days' => $trialDays]) }}</div>
      </details>
      @endfor
    </div>
  </div>
</section>

{{-- ══════════ FINAL ══════════ --}}
<section class="final">
  <div class="glow"></div>
  <div class="wrap in">
    <h2>{{ __('landing.final_title') }}</h2>
    <p>{{ __('landing.final_sub', ['days' => $trialDays]) }}</p>
    @if($isAuthed)
    <div class="capture" id="final-form" style="justify-content:center">
      <a class="btn p" href="{{ $homeRoute }}">{{ __('landing.go_to_dashboard') }}</a>
    </div>
    @else
    <form class="capture" id="final-form" method="GET" action="{{ route('register') }}">
      <input type="email" name="email" placeholder="{{ __('auth.login.placeholder_email') }}" aria-label="{{ __('auth.register.email') }}" required>
      <button class="btn p" type="submit">{{ __('landing.hero_cta') }}</button>
    </form>
    @endif
    <div class="microtrust">
      @foreach(['trust_no_card','trust_cancel','trust_keep_number'] as $k)
      <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M20 6 9 17l-5-5" stroke="#15b6a8" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('landing.' . $k) }}</span>
      @endforeach
    </div>
  </div>
</section>

{{-- ══════════ FOOTER ══════════ --}}
<footer><div class="wrap in">
  <span class="wm">{{ config('app.name', 'wavadesk') }}</span>
  <span>{{ __('landing.footer_tagline') }}</span>
  <nav>
    <a href="#features">{{ __('landing.footer_features') }}</a>
    <a href="#pricing">{{ __('landing.footer_pricing') }}</a>
    <a href="#faq">{{ __('landing.nav_faq') }}</a>
    <a href="{{ route('legal.privacy') }}">{{ __('landing.footer_privacy') }}</a>
    <a href="{{ route('legal.terms') }}">{{ __('landing.footer_terms') }}</a>
    <a href="{{ route('legal.cookies') }}">{{ __('landing.footer_cookies') }}</a>
    @if($isAuthed)
      <a href="{{ $homeRoute }}">{{ __('landing.go_to_dashboard') }}</a>
    @else
      <a href="{{ route('login') }}">{{ __('landing.nav_login') }}</a>
    @endif
  </nav>
</div></div></footer>

<div class="mobcta" id="mobcta">
  @if($isAuthed)
    <div class="t"><b>{{ config('app.name', 'wavadesk') }}</b><span>{{ __('landing.signed_in_as', ['name' => auth()->user()->name]) }}</span></div>
    <a class="btn p" href="{{ $homeRoute }}">{{ __('landing.go_to_dashboard') }}</a>
  @else
    <div class="t"><b>{{ __('landing.mob_title', ['days' => $trialDays]) }}</b><span>{{ __('landing.trust_no_card') }}</span></div>
    <a class="btn p" href="{{ route('register') }}">{{ __('landing.hero_cta') }}</a>
  @endif
</div>

<script>
(() => {
  // ── lang dropdown ──
  const langBtn = document.getElementById('langBtn');
  const langDD  = document.getElementById('langDropdown');
  if (langBtn && langDD) {
    langBtn.addEventListener('click', e => {
      e.stopPropagation();
      const open = langDD.classList.toggle('open');
      langBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    langDD.addEventListener('click', e => e.stopPropagation());
    document.addEventListener('click', () => { langDD.classList.remove('open'); langBtn.setAttribute('aria-expanded', 'false'); });
  }

  // ── billing toggle ──
  // Toggle is rendered only when at least one plan has price_annual > 0
  // (CALC-013). Individual plans without an annual price keep their monthly
  // display when Annual is selected — no `data-annual` on their .plan-amount,
  // no `.annual-label` span, so the swaps below are no-ops for them.
  const track = document.getElementById('track');
  if (track) {
    let annual = false;
    const lblM = document.getElementById('lblMonthly');
    const lblA = document.getElementById('lblAnnual');
    const apply = () => {
      track.classList.toggle('on', annual);
      lblM.classList.toggle('on', !annual);
      lblA.classList.toggle('on', annual);
      document.querySelectorAll('.plan-amount').forEach(el => {
        // Guard: if this plan has no annual price, stay on monthly (do not
        // render "undefined") — a plan without annual pricing keeps showing
        // its monthly figure in both toggle states.
        el.textContent = (annual && el.dataset.annual) ? el.dataset.annual : el.dataset.monthly;
      });
      document.querySelectorAll('.plan').forEach(card => {
        const hasAnnual = !!card.querySelector('.plan-amount[data-annual]');
        const monthly = card.querySelector('.monthly-label');
        const annualL = card.querySelector('.annual-label');
        if (monthly) monthly.style.display = (annual && hasAnnual) ? 'none' : '';
        if (annualL) annualL.style.display = (annual && hasAnnual) ? '' : 'none';
      });
    };
    track.addEventListener('click', () => { annual = !annual; apply(); });
    lblM.addEventListener('click', () => { annual = false; apply(); });
    lblA.addEventListener('click', () => { annual = true;  apply(); });
  }

  // ── sticky mobile CTA: show once the hero form is out of view, hide over the final form ──
  const mob    = document.getElementById('mobcta');
  const heroF  = document.getElementById('hero-form');
  const finalF = document.getElementById('final-form');
  if (mob && heroF && finalF && 'IntersectionObserver' in window) {
    let heroOut = false, finalIn = false;
    const sync = () => mob.classList.toggle('show', heroOut && !finalIn);
    new IntersectionObserver(([e]) => { heroOut = !e.isIntersecting; sync(); }, { threshold: 0 }).observe(heroF);
    new IntersectionObserver(([e]) => { finalIn = e.isIntersecting;  sync(); }, { threshold: .2 }).observe(finalF);
  }
})();
</script>
</body>
</html>
