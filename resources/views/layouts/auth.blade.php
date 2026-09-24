@php
    $isRtl = (bool) data_get(config('locales.supported', []), app()->getLocale() . '.rtl');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
@include('partials.gtm-head')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', __('auth.login.sign_in')) — {{ config('app.name', 'wavadesk') }}</title>
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
  --ink:#0d1417;--ink-2:#1a2226;--text:#0f172a;--muted:#64748b;--muted-2:#94a3b8;
  --border:#e2e8f0;--border-2:#cbd5e1;--page:#fff;--soft:#f8fafc;
  --ok:#15b6a8;--warn:#f59e0b;--bad:#ef4444;--bad-bg:#fef2f2;
}
*{box-sizing:border-box}
html,body{margin:0;height:100%;font-family:"Outfit",ui-sans-serif,system-ui,sans-serif;color:var(--text);-webkit-font-smoothing:antialiased;background:var(--page)}
html[dir="rtl"] body{font-family:"Cairo",ui-sans-serif,system-ui,sans-serif}
body{display:flex;flex-direction:column;height:100dvh;overflow:hidden}
button,input,select,textarea{font-family:inherit}
a{color:var(--teal);text-decoration:none}
a:hover{color:var(--teal-d)}

.app{flex:1;min-height:0;display:flex;flex-direction:column}

/* ---------- top bar ---------- */
.nav{height:68px;flex-shrink:0;display:flex;align-items:center;justify-content:space-between;gap:14px;padding:0 28px;border-bottom:1px solid var(--border);background:#fff;position:relative;z-index:20}
.brand{display:flex;align-items:center;gap:11px;color:var(--text)}
.brand:hover{color:var(--text)}
.brand .wm{font-weight:800;font-size:20px;letter-spacing:-.03em}
.nav .right{display:flex;align-items:center;gap:18px}
.navlink{font-size:14px;color:var(--muted)}
.navlink b{color:var(--teal);font-weight:600}

/* lang */
.lang-wrap{position:relative}
.lang{display:flex;align-items:center;gap:6px;font-size:13.5px;color:var(--muted);border:1px solid var(--border);padding:7px 11px;border-radius:8px;cursor:pointer;background:#fff;font-weight:500}
.lang:hover{border-color:var(--border-2)}
.lang-dd{position:absolute;top:calc(100% + 8px);inset-inline-end:0;background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 14px 34px -18px rgba(15,23,42,.35);min-width:150px;overflow:hidden;z-index:200;display:none}
.lang-dd.open{display:block}
.lang-item{display:block;width:100%;background:none;border:none;padding:10px 14px;font-size:13.5px;cursor:pointer;color:var(--text);text-align:start;transition:background .14s}
.lang-item:hover{background:var(--teal-50)}
.lang-item.active{font-weight:600;color:var(--teal)}

/* ---------- split ---------- */
.split{flex:1;display:grid;grid-template-columns:1fr 560px;min-height:0}

/* ---------- left / proof ---------- */
.proof{background:var(--ink);color:#fff;padding:40px 48px;display:flex;flex-direction:column;position:relative;overflow:hidden;min-height:0}
.proof .glow{position:absolute;width:900px;height:900px;border-radius:50%;background:radial-gradient(circle,rgba(21,182,168,.16),transparent 62%);top:-320px;inset-inline-end:-300px;pointer-events:none}
.proof .spine{position:absolute;inset-inline-start:0;bottom:-60px;opacity:.10;pointer-events:none}
html[dir="rtl"] .proof .spine{transform:scaleX(-1)}
.proof>*{position:relative}
.eyebrow{display:inline-flex;align-items:center;gap:9px;font-size:13px;font-weight:600;letter-spacing:.02em;color:var(--teal-l);background:rgba(21,182,168,.12);border:1px solid rgba(21,182,168,.22);padding:7px 14px;border-radius:999px;align-self:flex-start}
.proof h1{font-size:37px;font-weight:700;letter-spacing:-.035em;line-height:1.15;margin:20px 0 0;max-width:16ch}
.proof h1 em{font-style:normal;color:var(--teal-l)}
.proof .lede{font-size:15.5px;color:#94a3b8;line-height:1.55;margin-top:12px;max-width:44ch}

/* before / after */
.ba{margin-top:26px;display:grid;grid-template-columns:1fr 1fr;gap:12px}
.bacol{border-radius:14px;padding:18px;border:1px solid}
.bacol.before{background:rgba(255,255,255,.02);border-color:#263038}
.bacol.after{background:rgba(21,182,168,.07);border-color:rgba(21,182,168,.3)}
.bacol .tag{font-size:11px;font-weight:700;letter-spacing:.13em;text-transform:uppercase;display:flex;align-items:center;gap:7px}
.bacol.before .tag{color:#64748b}
.bacol.after .tag{color:var(--teal-l)}
.bacol ul{list-style:none;margin:14px 0 0;padding:0;display:flex;flex-direction:column;gap:9px}
.bacol li{font-size:13.5px;line-height:1.4;display:flex;gap:8px;align-items:flex-start}
.bacol.before li{color:#8b99a6}
.bacol.after li{color:#dbe7e6}
.bacol li svg{flex-shrink:0;margin-top:3px}

.proofline{margin-top:auto;padding-top:26px;display:flex;flex-direction:column;gap:10px}
.stat{display:flex;align-items:center;gap:10px;font-size:13.5px;color:#cbd5e1}
.stat svg{flex-shrink:0}
.avatars{display:flex;align-items:center;gap:11px;margin-top:4px}
.avatars .row{display:flex}
.avatars .row span{width:29px;height:29px;border-radius:50%;border:2px solid var(--ink);margin-inline-start:-8px;display:grid;place-items:center;font-size:11px;font-weight:700;color:#fff}
.avatars .row span:first-child{margin-inline-start:0}
.avatars .cap{font-size:12.5px;color:#8b99a6}

/* signup progress (otp view) */
.flow{margin-top:28px;display:flex;flex-direction:column;gap:14px}
.flow .fs{display:flex;align-items:center;gap:12px;font-size:14px;color:#8b99a6}
.flow .fs .no{width:26px;height:26px;border-radius:50%;display:grid;place-items:center;font-size:12px;font-weight:700;flex-shrink:0;background:rgba(255,255,255,.06);color:#8b99a6;border:1px solid #263038}
.flow .fs.done{color:#dbe7e6}
.flow .fs.done .no{background:rgba(21,182,168,.16);color:var(--teal-l);border-color:rgba(21,182,168,.3)}
.flow .fs.active{color:#fff;font-weight:600}
.flow .fs.active .no{background:var(--teal);color:#fff;border-color:var(--teal)}

/* ---------- right / form ---------- */
.pane{background:#fff;padding:44px 56px;overflow-y:auto;display:flex;flex-direction:column;justify-content:flex-start}
.pane .inner{width:100%;max-width:400px;margin:auto}
.pane .inner.wide{max-width:440px}

.trialbadge{display:flex;align-items:center;gap:12px;background:var(--teal-50);border:1px solid var(--teal-100);border-radius:12px;padding:13px 15px;margin-bottom:26px}
.trialbadge .ic{width:34px;height:34px;border-radius:9px;background:var(--teal);display:grid;place-items:center;flex-shrink:0}
.trialbadge .tx{font-size:13.5px;line-height:1.4}
.trialbadge .tx b{display:block;font-weight:700;font-size:14.5px;color:var(--teal-d)}
.trialbadge .tx span{color:var(--teal)}

h2.formtitle{font-size:29px;font-weight:700;letter-spacing:-.03em;margin:0}
.formsub{font-size:14.5px;color:var(--muted);margin:7px 0 0;line-height:1.5}

.divider{display:flex;align-items:center;gap:14px;margin:20px 0;color:var(--muted-2);font-size:12.5px}
.divider::before,.divider::after{content:"";flex:1;height:1px;background:var(--border)}

.alert{display:flex;align-items:flex-start;gap:10px;background:var(--bad-bg);border:1px solid #fecaca;color:#b91c1c;border-radius:11px;padding:12px 14px;font-size:13.5px;line-height:1.45;margin-top:20px}
.alert svg{flex-shrink:0;margin-top:1px}
.alert.warn{background:#fffbeb;border-color:#fde68a;color:#92400e}

.section-label{font-size:11.5px;font-weight:700;letter-spacing:.13em;text-transform:uppercase;color:var(--muted-2);margin:22px 0 12px}

.field{margin-bottom:15px}
.field label{display:block;font-size:13px;font-weight:600;color:var(--text);margin-bottom:6px}
.field .opt{color:var(--muted-2);font-weight:400}
.req{color:var(--bad)}
.ctrl{position:relative}
.ctrl input{width:100%;height:46px;border:1px solid var(--border);border-radius:10px;padding:0 14px;font-size:15px;color:var(--text);background:#fff;transition:.14s;outline:none}
.ctrl input::placeholder{color:var(--muted-2)}
.ctrl input:hover{border-color:var(--border-2)}
.ctrl input:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(15,126,122,.11)}
.ctrl.has-icon input{padding-inline-end:44px}
.ctrl .eye{position:absolute;inset-inline-end:6px;top:6px;width:34px;height:34px;display:grid;place-items:center;border:none;background:none;color:var(--muted-2);cursor:pointer;border-radius:7px}
.ctrl .eye:hover{color:var(--muted);background:var(--soft)}
.ctrl .tick{position:absolute;inset-inline-end:14px;top:50%;transform:translateY(-50%);opacity:0;transition:.15s;pointer-events:none}
.ctrl.valid input{border-color:var(--teal-100)}
.ctrl.valid .tick{opacity:1}
.ctrl.invalid input,.ctrl input.is-error{border-color:#fca5a5;background:#fffafa}
.hint{font-size:12.5px;color:var(--muted);margin-top:6px;line-height:1.45}
.hint.err{color:var(--bad);font-weight:500}

.meter{display:flex;gap:4px;margin-top:8px}
.meter i{flex:1;height:3px;border-radius:2px;background:var(--border);transition:.2s}
.meter.s1 i:nth-child(1){background:var(--bad)}
.meter.s2 i:nth-child(-n+2){background:var(--warn)}
.meter.s3 i:nth-child(-n+3){background:var(--teal-l)}
.meter.s4 i{background:var(--teal)}
.meterlbl{font-size:12px;font-weight:600;margin-top:6px;min-height:15px}

.grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}

.cta{width:100%;height:52px;border:none;border-radius:11px;background:var(--teal);color:#fff;font-size:16px;font-weight:600;cursor:pointer;margin-top:20px;transition:.15s;display:flex;align-items:center;justify-content:center;gap:9px;text-decoration:none}
.cta:hover{background:var(--teal-d);color:#fff}
.cta:active{transform:translateY(1px)}
.cta:disabled{opacity:.45;cursor:not-allowed}
.cta .sp{width:17px;height:17px;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:50%;animation:spin .65s linear infinite;display:none}
@keyframes spin{to{transform:rotate(360deg)}}
.cta.loading .sp{display:block}
.cta.loading .lbl{opacity:.75}
.cta.ghost{background:#fff;color:var(--text);border:1px solid var(--border);height:46px;font-size:15px}
.cta.ghost:hover{background:var(--soft);border-color:var(--border-2);color:var(--text)}

.reassure{display:flex;align-items:center;justify-content:center;gap:16px;margin-top:14px;flex-wrap:wrap}
.reassure span{display:flex;align-items:center;gap:6px;font-size:12.5px;color:var(--muted)}
.legal{font-size:12px;color:var(--muted-2);text-align:center;margin-top:16px;line-height:1.55}
.swap{text-align:center;font-size:14px;color:var(--muted);margin-top:22px;padding-top:20px;border-top:1px solid var(--border)}
.swap b{font-weight:600}

.rowline{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:6px}
.check{display:flex;align-items:center;gap:8px;font-size:13.5px;color:var(--muted);cursor:pointer;user-select:none}
.check input{width:16px;height:16px;accent-color:var(--teal);cursor:pointer}

/* plan picker */
.plans{position:relative;display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:4px}
.plans.one{grid-template-columns:1fr}
.planopt{position:absolute;opacity:0;pointer-events:none}
.planlbl{position:relative;display:block;border:1px solid var(--border);border-radius:12px;padding:14px 14px 13px;cursor:pointer;transition:.15s;background:#fff}
.planlbl:hover{border-color:var(--border-2)}
.planopt:checked+.planlbl{border-color:var(--teal);background:var(--teal-50);box-shadow:0 0 0 3px rgba(15,126,122,.09)}
.planopt:focus-visible+.planlbl{box-shadow:0 0 0 3px rgba(15,126,122,.22)}
.planlbl .pn{display:block;font-size:14.5px;font-weight:700;letter-spacing:-.01em}
.planlbl .pp{display:block;font-size:13px;color:var(--muted);margin-top:3px}
.planopt:checked+.planlbl .pp{color:var(--teal-d)}
.planlbl .mark{position:absolute;inset-inline-end:12px;top:12px;width:18px;height:18px;border-radius:50%;border:1.5px solid var(--border-2);display:grid;place-items:center;color:#fff;font-size:10px;font-weight:800;transition:.15s}
.planopt:checked+.planlbl .mark{background:var(--teal);border-color:var(--teal)}
.planlbl .hot{position:absolute;top:-9px;inset-inline-start:12px;background:var(--teal);color:#fff;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:3px 8px;border-radius:999px}

/* signup wizard header (step 1 / step 2) */
.wizsteps{display:flex;align-items:center;gap:10px;margin-bottom:22px}
.wizsteps .st{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:var(--muted-2);white-space:nowrap}
.wizsteps .st i{width:22px;height:22px;border-radius:50%;display:grid;place-items:center;font-style:normal;font-size:11.5px;font-weight:700;background:var(--soft);border:1px solid var(--border);color:var(--muted-2)}
.wizsteps .st.on{color:var(--teal-d)}
.wizsteps .st.on i{background:var(--teal);border-color:var(--teal);color:#fff}
.wizsteps .st.done{color:var(--teal)}
.wizsteps .st.done i{background:var(--teal-50);border-color:var(--teal-100);color:var(--teal)}
.wizsteps .bar{flex:1;height:2px;border-radius:2px;background:var(--border)}
.ctahint{font-size:12.5px;color:var(--muted);text-align:center;margin-top:10px;line-height:1.45}

/* ---------- step 2: plan choice ---------- */
.pane .inner.plans-wide{max-width:560px}
.pickplans{display:grid;gap:11px;margin-top:22px}
.pickplan{position:relative;display:block;border:1px solid var(--border);border-radius:14px;padding:16px 18px;cursor:pointer;transition:.15s;background:#fff}
.pickplan:hover{border-color:var(--border-2)}
.planopt:checked+.pickplan{border-color:var(--teal);background:var(--teal-50);box-shadow:0 0 0 3px rgba(15,126,122,.09)}
.planopt:focus-visible+.pickplan{box-shadow:0 0 0 3px rgba(15,126,122,.22)}
.pickplan .top{display:flex;align-items:flex-start;gap:12px}
.pickplan .mark{width:20px;height:20px;flex-shrink:0;margin-top:2px;border-radius:50%;border:1.5px solid var(--border-2);display:grid;place-items:center;color:#fff;font-size:11px;font-weight:800;transition:.15s}
.planopt:checked+.pickplan .mark{background:var(--teal);border-color:var(--teal)}
.pickplan .mid{flex:1;min-width:0}
.pickplan .nm{font-size:15.5px;font-weight:700;letter-spacing:-.015em;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.pickplan .price{text-align:end;flex-shrink:0}
.pickplan .price b{font-size:20px;font-weight:700;letter-spacing:-.03em;display:block;line-height:1.1}
.pickplan .price span{font-size:12px;color:var(--muted)}
.pickplan .free{display:inline-flex;align-items:center;gap:5px;background:var(--teal);color:#fff;font-size:10.5px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;padding:3px 8px;border-radius:999px}
.pickplan .pop{display:inline-flex;align-items:center;background:var(--soft);border:1px solid var(--border);color:var(--muted);font-size:10.5px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;padding:2px 8px;border-radius:999px}
.pickplan .note{font-size:12.5px;color:var(--muted);margin-top:4px;line-height:1.45}
.planopt:checked+.pickplan .note{color:var(--teal-d)}
.pickplan ul{list-style:none;margin:11px 0 0;padding:0;display:flex;flex-wrap:wrap;gap:6px 14px}
.pickplan li{font-size:12.5px;color:var(--muted);display:flex;align-items:center;gap:6px}
.pickplan li svg{flex-shrink:0}
.whoami{display:flex;align-items:center;gap:11px;background:var(--soft);border:1px solid var(--border);border-radius:12px;padding:12px 14px;font-size:13.5px;margin-bottom:22px}
.whoami .av{width:34px;height:34px;border-radius:9px;background:var(--teal-50);color:var(--teal-d);display:grid;place-items:center;font-weight:700;font-size:14px;flex-shrink:0}
.whoami .m{min-width:0}
.whoami .n{font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.whoami .e{font-size:12.5px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.signout{margin-inline-start:auto;font-size:12.5px;background:none;border:none;color:var(--muted);cursor:pointer;padding:0;text-decoration:underline}
.signout:hover{color:var(--teal)}

/* otp digits */
.otpdigits{display:flex;gap:9px;justify-content:space-between;margin:6px 0 4px}
.otpdigits input{width:100%;height:58px;text-align:center;font-size:23px;font-weight:700;border:1px solid var(--border);border-radius:11px;background:#fff;color:var(--text);outline:none;transition:.14s;padding:0}
.otpdigits input:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(15,126,122,.11)}
.otpdigits input.filled{border-color:var(--teal-100);background:var(--teal-50)}
.otpdigits input.error{border-color:#fca5a5;background:#fffafa}
.resend{display:flex;align-items:center;justify-content:center;gap:6px;font-size:13.5px;color:var(--muted);margin-top:18px}
.resend button{border:none;background:none;color:var(--teal);font-size:13.5px;font-weight:600;cursor:pointer;padding:0}
.resend button:disabled{color:var(--muted-2);cursor:not-allowed}
.backlink{display:flex;align-items:center;justify-content:center;gap:7px;font-size:13.5px;color:var(--muted);margin-top:18px}
.backlink:hover{color:var(--teal)}
html[dir="rtl"] .backlink svg{transform:scaleX(-1)}

/* success */
.done{text-align:center;padding:10px 0}
.done .big{width:66px;height:66px;border-radius:50%;background:var(--teal-50);border:1px solid var(--teal-100);display:grid;place-items:center;margin:0 auto 20px}
.done h2{font-size:26px;font-weight:700;letter-spacing:-.025em;margin:0}
.done p{font-size:15px;color:var(--muted);line-height:1.6;margin-top:10px}
.steps{text-align:start;margin-top:24px;display:flex;flex-direction:column;gap:12px}
.stp{display:flex;gap:12px;align-items:flex-start;font-size:14px;color:var(--muted);line-height:1.45}
.stp .no{width:22px;height:22px;border-radius:50%;background:var(--teal-50);color:var(--teal-d);font-size:12px;font-weight:700;display:grid;place-items:center;flex-shrink:0;margin-top:1px}

/* the proof panel never scrolls — it sheds detail as the viewport gets shorter */
@media (max-height:880px){
  .proof{padding:32px 44px}
  .proof h1{font-size:32px;margin-top:16px}
  .proof .lede{font-size:14.5px;margin-top:10px}
  .ba{margin-top:20px;gap:10px}
  .bacol{padding:15px}
  .bacol ul{margin-top:11px;gap:7px}
  .bacol li{font-size:13px}
  .proofline{padding-top:20px;gap:8px}
  .flow{margin-top:22px;gap:11px}
}
@media (max-height:760px){
  .proof{padding:26px 40px}
  .proof h1{font-size:28px}
  .ba{display:none}
  .flow{margin-top:18px}
  .avatars{display:none}
}
@media (max-height:600px){
  .proof .lede{display:none}
  .proofline .stat:nth-child(n+3){display:none}
}
@media (max-width:1180px){
  .split{grid-template-columns:1fr 480px}
  .proof{padding:36px 34px}
  .proof h1{font-size:32px}
}
@media (max-width:1080px){
  html,body{height:auto;overflow:visible}
  body{display:block;min-height:100dvh}
  .app{display:block}
  .nav{position:sticky;top:0}
  .split{display:block}
  .proof{display:none}
  .pane{padding:40px 24px 56px;overflow:visible;min-height:calc(100dvh - 68px)}
  .pane .inner{margin:0 auto}
}
@media (max-width:560px){
  .nav{padding:0 16px;height:62px;gap:10px}
  .brand .wm{font-size:18px}
  .navlink{display:none}
  .pane{padding:28px 18px 44px}
  h2.formtitle{font-size:25px}
  .grid2{grid-template-columns:1fr;gap:0}
  .plans{grid-template-columns:1fr}
  .pickplan{padding:14px}
  .otpdigits{gap:6px}
  .otpdigits input{height:52px;font-size:20px}
  .reassure{gap:12px}
}
@media (max-width:360px){
  .otpdigits input{height:46px;font-size:18px}
}
</style>
@stack('head')
</head>
<body>
@include('partials.gtm-body')
<div class="app">

  <div class="nav">
    <a class="brand" href="{{ route('landing') }}">
      <svg width="34" height="34" viewBox="0 0 512 512" fill="none" aria-hidden="true"><rect x="7" y="7" width="498" height="498" rx="118" fill="#0f7e7a"/><g transform="translate(256,256) scale(.8) translate(-284,-267)"><path d="M 96 326 C 162 326, 162 184, 240 184 C 320 184, 320 350, 388 350 C 432 350, 432 226, 472 226" stroke="#fff" stroke-width="46" stroke-linecap="round" fill="none"/><circle cx="96" cy="326" r="34" fill="#fff"/><circle cx="472" cy="226" r="34" fill="#d6efed"/></g></svg>
      <span class="wm">{{ config('app.name', 'wavadesk') }}</span>
    </a>
    <div class="right">
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
      @yield('navlink')
    </div>
  </div>

  <div class="split">

    {{-- ══ LEFT / proof ══ --}}
    <div class="proof">
      <div class="glow"></div>
      <svg class="spine" width="700" height="420" viewBox="0 0 700 420" fill="none" aria-hidden="true"><path d="M 0 300 C 110 300, 110 120, 240 120 C 370 120, 370 340, 490 340 C 590 340, 590 160, 700 160" stroke="#15b6a8" stroke-width="60" stroke-linecap="round" fill="none"/></svg>

      @hasSection('proof')
        @yield('proof')
      @else
        <span class="eyebrow">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z" fill="currentColor"/></svg>
          {{ __('auth.shell.trial_pill', ['days' => config('app.trial_days', 14)]) }}
        </span>

        <h1>{!! __('auth.shell.title') !!}</h1>
        <p class="lede">{{ __('auth.shell.lede') }}</p>

        <div class="ba">
          <div class="bacol before">
            <div class="tag"><svg width="13" height="13" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9.5" stroke="currentColor" stroke-width="1.8"/><path d="M15 9l-6 6M9 9l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>{{ __('auth.shell.before_tag') }}</div>
            <ul>
              @foreach(['before_1','before_2','before_3'] as $k)
              <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="#475569" stroke-width="2" stroke-linecap="round"/></svg>{{ __('auth.shell.' . $k) }}</li>
              @endforeach
            </ul>
          </div>
          <div class="bacol after">
            <div class="tag"><svg width="13" height="13" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9.5" stroke="currentColor" stroke-width="1.8"/><path d="M8 12.5l2.5 2.5L16 9.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('auth.shell.after_tag') }}</div>
            <ul>
              @foreach(['after_1','after_2','after_3'] as $k)
              <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M20 6 9 17l-5-5" stroke="#15b6a8" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('auth.shell.' . $k) }}</li>
              @endforeach
            </ul>
          </div>
        </div>

        <div class="proofline">
          @foreach(['stat_1','stat_2','stat_3'] as $k)
          <div class="stat"><svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M20 6 9 17l-5-5" stroke="#15b6a8" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('auth.shell.' . $k, ['days' => config('app.trial_days', 14)]) }}</div>
          @endforeach
          <div class="avatars">
            <div class="row">
              <span style="background:#0f7e7a">HR</span><span style="background:#15b6a8">MK</span><span style="background:#0a5e5b">AB</span><span style="background:#334155">+</span>
            </div>
            <span class="cap">{{ __('auth.shell.avatars_caption') }}</span>
          </div>
        </div>
      @endif
    </div>

    {{-- ══ RIGHT / form ══ --}}
    <div class="pane">
      <div class="inner @yield('pane_class')">
        @yield('pane')
      </div>
    </div>

  </div>
</div>

<script>
(() => {
  const btn = document.getElementById('langBtn');
  const dd  = document.getElementById('langDropdown');
  if (btn && dd) {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      const open = dd.classList.toggle('open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    dd.addEventListener('click', e => e.stopPropagation());
    document.addEventListener('click', () => { dd.classList.remove('open'); btn.setAttribute('aria-expanded','false'); });
  }

  const EYE = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7"/></svg>';
  const EYE_OFF = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M3 3l18 18M10.6 10.7a3 3 0 004.2 4.2M9.4 5.2A9.6 9.6 0 0112 5c6.4 0 10 7 10 7a17 17 0 01-3.2 4M6.2 6.7A17 17 0 002 12s3.6 7 10 7c1.3 0 2.4-.2 3.5-.6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>';

  document.querySelectorAll('[data-eye]').forEach(b => {
    b.innerHTML = EYE;
    b.addEventListener('click', () => {
      const i = b.parentElement.querySelector('input');
      const show = i.type === 'password';
      i.type = show ? 'text' : 'password';
      b.innerHTML = show ? EYE_OFF : EYE;
    });
  });

  // live "valid" tick on text/email inputs
  document.querySelectorAll('[data-wrap] input[type=email],[data-wrap] input[type=text]').forEach(i => {
    const wrap = i.closest('[data-wrap]');
    const check = () => {
      const ok = i.type === 'email'
        ? /^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i.test(i.value)
        : i.value.trim().length > 1;
      wrap.classList.toggle('valid', ok);
    };
    i.addEventListener('input', check);
    if (i.value) check();
  });

  // submit spinner
  document.querySelectorAll('form[data-spin]').forEach(f => {
    f.addEventListener('submit', () => {
      const btn = f.querySelector('.cta');
      if (btn) { btn.classList.add('loading'); btn.disabled = true; }
    });
  });
})();
</script>
@stack('scripts')
{{-- No chat widget on the auth pages. Sign-in and sign-up are the two
     screens where a floating bubble competes with the only thing on the
     page, and it answers questions about the product to someone who is
     already past deciding. It stays on the marketing pages and inside the
     panel, which is where it gets asked anything. --}}
</body>
</html>
