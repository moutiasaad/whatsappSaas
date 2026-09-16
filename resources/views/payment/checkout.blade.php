@php
    use App\Models\TenantPayment;

    $isRtl    = (bool) data_get(config('locales.supported', []), app()->getLocale() . '.rtl');
    $kind     = $kind ?? TenantPayment::KIND_SUBSCRIPTION;
    $isPack   = $kind === TenantPayment::KIND_AI_PACK;
    $isSeat   = $kind === TenantPayment::KIND_SEAT_PACK;
    $isCart   = $kind === TenantPayment::KIND_CART;
    $isAddon  = $isPack || $isSeat;
    $cart     = $cart ?? null;
    // Resolved server-side and validated same-host; see PaymentController::backUrl().
    $backUrl  = $backUrl ?? url('/');
    // The logo goes home: a signed-in user's own panel, the marketing site for
    // a guest still registering — who has no dashboard to land on yet.
    $homeUrl  = auth()->check() ? route(auth()->user()->homeRouteName()) : url('/');
    $packs    = $packs ?? 0;
    $seats    = $seats ?? 0;
    $messages = $messages ?? 0;
    $currency = config('services.paypal.currency', 'USD');
    // Two ways to take a PayPal payment: the REST/SDK smart buttons when a
    // client id is configured, or the email-only Standard flow when only a
    // payee address is. Standard needs no developer app at all.
    // The client id alone drives the SDK: card fields render and are eligible
    // without a secret, so the buyer sees a card form regardless.
    $sdkReady = (bool) config('services.paypal.client_id');
    $stdReady = !$sdkReady && (bool) config('services.paypal.payee_email');
    $ready    = $sdkReady;

    // Creating and capturing the order is server-side and does need a working
    // secret. A failure there surfaces as the red error box on submit, so the
    // page does not pre-empt it — this only decides whether it is worth asking
    // PayPal for a client token below.
    $restOk = $sdkReady && app(\App\Services\PayPalService::class)->credentialsValid();

    // Optional for card fields, so only fetched when the secret works.
    $clientToken = $restOk
        ? app(\App\Services\PayPalService::class)->clientToken()
        : null;

    // What the Standard form has to post to rebuild the same order server-side.
    $stdFields = match (true) {
        $isCart => ['plan_id' => $cart['plan']?->id, 'seats' => $cart['seats'], 'packs' => $cart['packs']],
        $isSeat => ['seats' => $seats],
        $isPack => ['packs' => $packs],
        default => ['plan_id' => $plan->id ?? null],
    };
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('auth.register.checkout_title', ['app' => config('app.name', 'Wavadesk')]) }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        :root{
            --teal:#0f7e7a;--teal-d:#0a5e5b;--teal-50:#ecf7f6;--teal-100:#d6efed;--accent:#15b6a8;
            --ink:#0d1417;--ink-2:#161e22;
            --text:#0f172a;--muted:#64748b;--muted-2:#94a3b8;
            --border:#e6ebf0;--border-2:#cbd5e1;--soft:#f7f9fa;
            --red:#dc2626;--red-50:#fef2f2;--amber:#d97706;--amber-50:#fef3e2;
        }
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Outfit',system-ui,sans-serif;background:var(--soft);color:var(--text);min-height:100vh;display:flex;flex-direction:column;font-size:14px;line-height:1.5;-webkit-font-smoothing:antialiased}
        html[dir="rtl"] body{font-family:'Cairo',sans-serif;line-height:1.65}
        a{color:var(--teal);text-decoration:none}

        nav{background:#fff;border-bottom:1px solid var(--border);padding:0 24px;height:62px;display:flex;align-items:center;gap:12px;flex-shrink:0}
        .nav-logo{display:flex;align-items:center;gap:10px;font-size:17px;font-weight:800;color:var(--text);letter-spacing:-.03em;border-radius:9px;padding:4px 8px;margin-inline-start:-8px;transition:.14s}
        .nav-logo:hover{background:var(--soft);color:var(--text)}
        .nav-logo:focus-visible{outline:2px solid rgba(15,126,122,.5);outline-offset:2px}
        .nav-logo-icon{width:32px;height:32px;border-radius:9px;overflow:hidden;display:grid;place-items:center}
        .nav-sp{flex:1}
        .nav-back{display:inline-flex;align-items:center;gap:7px;height:36px;padding:0 13px 0 10px;border:1px solid var(--border);border-radius:9px;font-size:13.5px;font-weight:600;color:var(--text);background:#fff;transition:.14s;flex-shrink:0}
        .nav-back:hover{border-color:var(--border-2);background:var(--soft);color:var(--text)}
        .nav-back i{font-size:16px;color:var(--muted)}
        html[dir="rtl"] .nav-back i{transform:scaleX(-1)}
        .nav-sep{width:1px;height:24px;background:var(--border);flex-shrink:0}
        @media (max-width:520px){.nav-back span{display:none}.nav-back{padding:0 10px}}
        .nav-secure{font-size:12.5px;color:var(--muted);display:flex;align-items:center;gap:6px}
        .nav-secure i{color:var(--teal)}

        main{flex:1;display:flex;justify-content:center;padding:32px 20px 56px}
        .shell{width:100%;max-width:940px}

        /* ═══ STEPS ═══ */
        .steps{display:flex;align-items:center;gap:0;margin-bottom:26px;max-width:620px;margin-inline:auto}
        .stp{display:flex;align-items:center;gap:10px;flex-shrink:0}
        .stp .dot{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;font-size:13px;font-weight:700;background:#fff;border:1.5px solid var(--border);color:var(--muted-2);flex-shrink:0;transition:.2s}
        .stp .lb{font-size:13px;font-weight:600;color:var(--muted-2);white-space:nowrap;transition:.2s}
        .stp.on .dot{background:var(--teal);border-color:var(--teal);color:#fff}
        .stp.on .lb{color:var(--text)}
        .stp.done .dot{background:var(--teal-50);border-color:var(--teal-100);color:var(--teal)}
        .stp.done .lb{color:var(--teal-d)}
        .stpline{flex:1;height:1.5px;background:var(--border);margin:0 12px;min-width:18px;transition:.2s}
        .stpline.done{background:var(--teal-100)}

        .grid{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:18px;align-items:start}
        .card{background:#fff;border:1px solid var(--border);border-radius:16px;overflow:hidden}
        .ch{padding:18px 20px;border-bottom:1px solid var(--border)}
        .ch h2{font-size:16px;font-weight:700;letter-spacing:-.02em}
        .ch p{font-size:12.5px;color:var(--muted);margin-top:3px}
        .cb{padding:20px}

        /* ═══ WHAT YOU GET ═══ */
        .buy{display:flex;align-items:center;gap:14px;padding:16px;border:1px solid var(--border);border-radius:13px;background:var(--soft)}
        .buy .ic{width:46px;height:46px;border-radius:13px;display:grid;place-items:center;flex-shrink:0;font-size:22px;background:var(--teal-50);color:var(--teal)}
        .buy .m{flex:1;min-width:0}
        .buy .n{font-size:15px;font-weight:700;letter-spacing:-.01em}
        .buy .s{font-size:12.5px;color:var(--muted);margin-top:2px;line-height:1.45}
        .buy .amt{font-size:22px;font-weight:800;letter-spacing:-.03em;white-space:nowrap;font-variant-numeric:tabular-nums}

        .feats{list-style:none;margin:18px 0 0;display:flex;flex-direction:column;gap:10px}
        .feats li{font-size:13.5px;display:flex;gap:9px;align-items:flex-start;line-height:1.45}
        .feats li i{color:var(--teal);font-size:15px;flex-shrink:0;line-height:1.35}

        .note{background:var(--teal-50);border:1px solid var(--teal-100);border-radius:11px;padding:12px 14px;margin-top:18px;display:flex;gap:9px;align-items:flex-start;font-size:12.5px;color:var(--teal-d);line-height:1.5}
        .note i{flex-shrink:0;font-size:15px;line-height:1.3}

        /* ═══ PAY ═══ */
        /* The order and the payment method are two decisions; the rule and the
           margin stop them reading as one continuous block. */
        .paysplit{display:flex;align-items:center;gap:14px;margin:30px 0 20px;color:var(--muted-2);font-size:12px;font-weight:600;letter-spacing:.08em;text-transform:uppercase}
        .paysplit::before,.paysplit::after{content:"";flex:1;height:1px;background:var(--border)}

        .cartlines{border:1px solid var(--border);border-radius:13px;overflow:hidden}
        .cline{display:flex;align-items:center;gap:13px;padding:14px 16px;background:#fff}
        .cline + .cline{border-top:1px solid var(--border)}
        .cline .ic{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;flex-shrink:0;font-size:19px;background:var(--teal-50);color:var(--teal)}
        .cline .m{flex:1;min-width:0}
        .cline .n{font-size:14px;font-weight:600;letter-spacing:-.01em}
        .cline .s{font-size:12.5px;color:var(--muted);margin-top:2px}
        .cline .v{font-size:15.5px;font-weight:700;white-space:nowrap;font-variant-numeric:tabular-nums}

        /* accepted-card marks */
        .cards{display:flex;align-items:center;gap:9px;margin-bottom:16px;flex-wrap:wrap}
        .cm{height:24px;min-width:38px;border:1px solid var(--border);border-radius:5px;background:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;letter-spacing:.04em}
        .cm.visa{color:#1a1f71}
        .cm.amex{color:#016fd0;font-size:9px}
        .cm.mc{gap:0;padding:0 9px}
        .cm.mc i{width:13px;height:13px;border-radius:50%;display:block}
        .cm.mc i:first-child{background:#eb001b}
        .cm.mc i:last-child{background:#f79e1b;margin-inline-start:-5px;mix-blend-mode:multiply}
        .cards .t{font-size:11.5px;color:var(--muted)}

        .btn-paypal{width:100%;height:52px;border:none;border-radius:11px;background:#ffc439;color:#003087;font-family:inherit;font-size:15.5px;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;transition:.15s}
        .btn-paypal:hover{background:#f0b429}
        .btn-paypal:disabled{opacity:.6;cursor:default}
        .btn-paypal i{font-size:20px}
        .btn-card{width:100%;height:52px;border:1px solid var(--border-2);border-radius:11px;background:#fff;color:var(--text);font-family:inherit;font-size:15px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;transition:.15s}
        .btn-card:hover{background:var(--soft);border-color:var(--muted-2)}
        .btn-card:disabled{opacity:.6;cursor:default}
        .btn-card i{font-size:19px;color:var(--muted)}
        /* Card is the primary path now, so its button carries the brand colour
           and the PayPal buttons below read as the alternative. */
        .btn-card.primary{background:var(--teal);border-color:var(--teal);color:#fff;font-weight:800;font-size:15.5px}
        .btn-card.primary:hover{background:var(--teal-d);border-color:var(--teal-d)}
        .btn-card.primary i{color:#fff;font-size:17px}

        .cardhead{display:flex;align-items:center;gap:8px;font-size:13.5px;font-weight:700;margin-bottom:13px}
        .cardhead i{font-size:17px;color:var(--teal)}

        /* Placeholder while the SDK reports card-field eligibility, so the
           panel never flashes empty on a slow connection. */
        .cardskel .sk{height:46px;border-radius:10px;margin-bottom:13px;background:linear-gradient(90deg,var(--soft) 25%,#eef2f5 37%,var(--soft) 63%);background-size:400% 100%;animation:skel 1.4s ease infinite}
        .cardskel .sk-row{display:grid;grid-template-columns:1fr 1fr;gap:11px}
        .cardskel .sk-note{font-size:12px;color:var(--muted);text-align:center;margin-top:2px}
        @keyframes skel{0%{background-position:100% 50%}100%{background-position:0 50%}}
        @media (prefers-reduced-motion:reduce){.cardskel .sk{animation:none}}

        /* PayPal renders each field as an iframe inside these boxes. */
        .cardform{display:flex;flex-direction:column;gap:0}
        .cf-l{font-size:12.5px;font-weight:600;color:var(--muted);margin-bottom:6px;display:block}
        /* Deliberately unstyled. PayPal renders its own input inside here and
           we leave its appearance alone: the buyer should recognise a PayPal
           field rather than one dressed up to look like ours. That also means
           no wrapper border to double up against PayPal's, and no fixed height
           to fight the size PayPal picks. Focus and validity styling come from
           inside the iframe for the same reason. */
        .cf{margin-bottom:4px}
        .cf-row{display:grid;grid-template-columns:1fr 1fr;gap:11px}
        #cf-submit{margin-top:4px}

        .paysep{display:flex;align-items:center;gap:12px;margin:12px 0;color:var(--muted-2);font-size:12px;font-weight:600}
        .paysep::before,.paysep::after{content:"";flex:1;height:1px;background:var(--border)}

        .paybox{margin-top:4px}
        .paybrand{display:flex;align-items:center;gap:12px;border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:16px;background:#fff}
        .paybrand .lg{width:46px;height:34px;border-radius:7px;background:#003087;color:#fff;display:grid;place-items:center;flex-shrink:0;font-size:20px}
        .paybrand .m{flex:1;min-width:0}
        .paybrand .n{font-size:13.5px;font-weight:600}
        .paybrand .s{font-size:12px;color:var(--muted);margin-top:1px}
        .paybrand .tick{color:var(--teal);font-size:18px}

        #paypal-button-container{min-height:52px}
        .payhint{font-size:11.5px;color:var(--muted);text-align:center;margin-top:12px;line-height:1.5}

        .errbox{background:var(--red-50);border:1px solid #fecaca;border-radius:11px;padding:12px 14px;font-size:13px;color:var(--red);margin-bottom:16px;display:flex;gap:9px;align-items:flex-start;line-height:1.45}
        .errbox i{flex-shrink:0;font-size:15px;line-height:1.3}

        .working{display:none;align-items:center;justify-content:center;gap:10px;padding:18px;font-size:13.5px;color:var(--muted);font-weight:500}
        .working.on{display:flex}
        .spin{width:17px;height:17px;border:2.2px solid var(--teal-100);border-top-color:var(--teal);border-radius:50%;animation:sp .7s linear infinite}
        @keyframes sp{to{transform:rotate(360deg)}}
        @media (prefers-reduced-motion:reduce){.spin{animation-duration:2s}}

        /* ═══ SUMMARY ═══ */
        .sum{position:sticky;top:18px;background:#fff;border:1px solid var(--border);border-radius:16px;overflow:hidden}
        .sum .sh{padding:16px 18px;border-bottom:1px solid var(--border)}
        .sum .sh h3{font-size:15px;font-weight:700;letter-spacing:-.015em}
        .sum .sb{padding:15px 18px}
        .li{display:flex;align-items:flex-start;gap:10px;padding:8px 0;font-size:13.5px}
        .li .m{flex:1;min-width:0}
        .li .n{font-weight:500}
        .li .s{font-size:11.5px;color:var(--muted-2);margin-top:1px;line-height:1.4}
        .li .v{font-weight:600;white-space:nowrap;font-variant-numeric:tabular-nums}
        .divr{height:1px;background:var(--border);margin:9px 0}
        .tot{display:flex;align-items:flex-end;justify-content:space-between;gap:10px;padding-top:4px}
        .tot .l{font-size:13.5px;font-weight:600}
        .tot .r{text-align:end}
        .tot .r b{font-size:27px;font-weight:800;letter-spacing:-.04em;line-height:1;display:block;font-variant-numeric:tabular-nums}
        .tot .r span{font-size:11.5px;color:var(--muted)}
        .trust{display:flex;flex-direction:column;gap:8px;padding:0 18px 18px}
        .trust span{font-size:11.5px;color:var(--muted);display:flex;align-items:center;gap:7px;line-height:1.4}
        .trust i{flex-shrink:0;color:var(--teal);font-size:13px}
        .backlink{display:block;text-align:center;margin-top:16px;font-size:13px;color:var(--muted)}
        .backlink:hover{color:var(--teal)}

        @media (max-width:900px){
            .grid{grid-template-columns:minmax(0,1fr)}
            .sum{position:static}
            .steps{margin-bottom:20px}
            .stp .lb{display:none}
            .stp:first-child .lb,.stp.on .lb{display:block}
        }
        @media (max-width:520px){
            main{padding:20px 14px 40px}
            .cb{padding:16px}
            .buy{flex-wrap:wrap}
            .buy .amt{margin-inline-start:auto}
        }
    </style>
</head>
<body>

<nav>
    {{-- Leaving a checkout should not mean hunting for the browser's back
         button, or losing the page the buyer arrived from. --}}
    <a class="nav-back" href="{{ $backUrl }}">
        <i class="ri-arrow-left-line"></i>
        <span>{{ __('ui.payment_page.back') }}</span>
    </a>
    <span class="nav-sep" aria-hidden="true"></span>
    <a class="nav-logo" href="{{ $homeUrl }}" aria-label="{{ __('ui.payment_page.go_home') }}" title="{{ __('ui.payment_page.go_home') }}">
        <span class="nav-logo-icon">
            <svg width="32" height="32" viewBox="0 0 512 512" fill="none" aria-hidden="true"><rect x="7" y="7" width="498" height="498" rx="118" fill="#0f7e7a"/><g transform="translate(256,256) scale(.8) translate(-284,-267)"><path d="M 96 326 C 162 326, 162 184, 240 184 C 320 184, 320 350, 388 350 C 432 350, 432 226, 472 226" stroke="#fff" stroke-width="46" stroke-linecap="round" fill="none"/><circle cx="96" cy="326" r="34" fill="#fff"/><circle cx="472" cy="226" r="34" fill="#d6efed"/></g></svg>
        </span>
        {{ config('app.name', 'wavadesk') }}
    </a>
    <span class="nav-sp"></span>
    <span class="nav-secure"><i class="ri-lock-2-line"></i>{{ __('ui.payment_page.secure') }}</span>
</nav>

<main>
<div class="shell">

    {{-- ═══ STEP RAIL — reflects where the buyer actually is ═══ --}}
    <div class="steps" id="steps">
        <div class="stp done" data-step="1">
            <span class="dot"><i class="ri-check-line"></i></span>
            <span class="lb">{{ __('ui.payment_page.step_choose') }}</span>
        </div>
        <span class="stpline done"></span>
        <div class="stp on" data-step="2">
            <span class="dot">2</span>
            <span class="lb">{{ __('ui.payment_page.step_pay') }}</span>
        </div>
        <span class="stpline" id="line3"></span>
        <div class="stp" data-step="3">
            <span class="dot">3</span>
            <span class="lb">{{ __('ui.payment_page.step_done') }}</span>
        </div>
    </div>

    <div class="grid">

        {{-- ═══ LEFT ═══ --}}
        <div class="card">
            <div class="ch">
                <h2>{{ $isCart ? __('ui.payment_page.cart_title') : ($isSeat ? __('ui.payment_page.seat_title') : ($isPack ? __('ui.payment_page.pack_title') : __('ui.payment_page.plan_title'))) }}</h2>
                <p>{{ $isCart ? __('ui.payment_page.cart_sub', ['tenant' => $tenant->name]) : ($isAddon ? __('ui.payment_page.addon_sub') : __('ui.payment_page.plan_sub', ['tenant' => $tenant->name])) }}</p>
            </div>
            <div class="cb">

                @if($errors->any())
                <div class="errbox">
                    <i class="ri-error-warning-line"></i>
                    <div>{{ $errors->first() }}</div>
                </div>
                @endif

                @if($isCart)
                {{-- One row per thing in the order, so the buyer can see exactly
                     what the single charge covers. --}}
                <div class="cartlines">
                    @if($cart['plan'])
                    <div class="cline">
                        <span class="ic"><i class="ri-vip-crown-2-line"></i></span>
                        <div class="m">
                            <div class="n">{{ __('ui.payment_page.plan_name', ['plan' => $cart['plan']->name]) }}</div>
                            <div class="s">{{ __('ui.payment_page.plan_billed_monthly') }}</div>
                        </div>
                        <div class="v">${{ number_format($cart['plan_cost'], 2) }}</div>
                    </div>
                    @endif
                    @if($cart['seats'] > 0)
                    <div class="cline">
                        <span class="ic"><i class="ri-user-add-line"></i></span>
                        <div class="m">
                            <div class="n">{{ trans_choice('ui.payment_page.seat_name', $cart['seats'], ['count' => $cart['seats']]) }}</div>
                            <div class="s">{{ __('ui.payment_page.seat_one_off') }}</div>
                        </div>
                        <div class="v">${{ number_format($cart['seat_cost'], 2) }}</div>
                    </div>
                    @endif
                    @if($cart['packs'] > 0)
                    <div class="cline">
                        <span class="ic"><i class="ri-sparkling-2-line"></i></span>
                        <div class="m">
                            <div class="n">{{ __('ui.payment_page.pack_name', ['n' => number_format($cart['messages'])]) }}</div>
                            <div class="s">{{ trans_choice('ui.payment_page.pack_count', $cart['packs'], ['count' => $cart['packs']]) }}</div>
                        </div>
                        <div class="v">${{ number_format($cart['pack_cost'], 2) }}</div>
                    </div>
                    @endif
                </div>
                @else
                <div class="buy">
                    <div class="ic"><i class="{{ $isSeat ? 'ri-user-add-line' : ($isPack ? 'ri-sparkling-2-line' : 'ri-vip-crown-2-line') }}"></i></div>
                    <div class="m">
                        <div class="n">
                            @if($isSeat)
                                {{ trans_choice('ui.payment_page.seat_name', $seats, ['count' => $seats]) }}
                            @elseif($isPack)
                                {{ __('ui.payment_page.pack_name', ['n' => number_format($messages)]) }}
                            @else
                                {{ __('ui.payment_page.plan_name', ['plan' => $plan?->name ?? '—']) }}
                            @endif
                        </div>
                        <div class="s">
                            @if($isSeat)
                                {{ __('ui.payment_page.seat_one_off') }}
                            @elseif($isPack)
                                {{ trans_choice('ui.payment_page.pack_count', $packs, ['count' => $packs]) }}
                            @else
                                {{ __('ui.payment_page.plan_billed_monthly') }}
                            @endif
                        </div>
                    </div>
                    <div class="amt">${{ number_format($amount, 2) }}</div>
                </div>
                @endif

                <ul class="feats">
                    @if($isCart)
                        <li><i class="ri-check-line"></i>{{ __('ui.payment_page.cart_feat_1') }}</li>
                        @if($cart['plan'])<li><i class="ri-check-line"></i>{{ __('landing.plan_unlimited_headline') }}</li>@endif
                        @if($cart['seats'] > 0)<li><i class="ri-check-line"></i>{{ __('ui.payment_page.seat_feat_2') }}</li>@endif
                        @if($cart['packs'] > 0)<li><i class="ri-check-line"></i>{{ __('ui.payment_page.pack_feat_3') }}</li>@endif
                    @else
                    @if($isSeat)
                        <li><i class="ri-check-line"></i>{{ trans_choice('ui.payment_page.seat_feat_1', $seats, ['count' => $seats]) }}</li>
                        <li><i class="ri-check-line"></i>{{ __('ui.payment_page.seat_feat_2') }}</li>
                        <li><i class="ri-check-line"></i>{{ __('ui.payment_page.seat_feat_3') }}</li>
                    @elseif($isPack)
                        <li><i class="ri-check-line"></i>{{ __('ui.payment_page.pack_feat_1', ['n' => number_format($messages)]) }}</li>
                        <li><i class="ri-check-line"></i>{{ __('ui.payment_page.pack_feat_2') }}</li>
                        <li><i class="ri-check-line"></i>{{ __('ui.payment_page.pack_feat_3') }}</li>
                    @else
                        <li><i class="ri-check-line"></i>{{ __('landing.plan_unlimited_headline') }}</li>
                        @if($plan?->max_users)
                        <li><i class="ri-check-line"></i>{{ __('ui.platform_plans_page.users_limit', ['count' => $plan->max_users]) }}</li>
                        @endif
                        <li><i class="ri-check-line"></i>
                            {{ $plan?->ai_message_quota === null
                                ? __('landing.attr_ai_unlimited')
                                : __('landing.attr_ai_messages', ['n' => number_format((int) $plan?->ai_message_quota)]) }}
                        </li>
                        <li><i class="ri-check-line"></i>{{ __('ui.payment_page.plan_feat_cancel') }}</li>
                    @endif
                    @endif
                </ul>

                <div class="note">
                    <i class="ri-information-line"></i>
                    <div>{{ $isCart ? __('ui.payment_page.cart_note') : ($isSeat ? __('ui.payment_page.seat_note') : ($isPack ? __('ui.payment_page.pack_note') : __('ui.payment_page.plan_note'))) }}</div>
                </div>

                {{-- ═══ PAY ═══ --}}
                <div class="paysplit"><span>{{ __('ui.payment_page.pay_divider') }}</span></div>

                <div class="paybox">
                    <div class="paybrand">
                        <div class="lg"><i class="ri-paypal-fill"></i></div>
                        <div class="m">
                            <div class="n">{{ __('ui.payment_page.paypal_name') }}</div>
                            <div class="s">{{ __('ui.payment_page.paypal_sub') }}</div>
                        </div>
                        <i class="ri-checkbox-circle-fill tick"></i>
                    </div>

                    {{-- The cards PayPal's guest checkout accepts. Buyers look
                         for these marks before they commit. --}}
                    <div class="cards">
                        <span class="cm visa" aria-label="Visa">VISA</span>
                        <span class="cm mc" aria-hidden="true"><i></i><i></i></span>
                        <span class="cm amex" aria-label="American Express">AMEX</span>
                        <span class="t">{{ __('ui.payment_page.cards_accepted') }}</span>
                    </div>

                    <div id="paypal-error" class="errbox" style="display:none">
                        <i class="ri-error-warning-line"></i><div id="paypal-error-text"></div>
                    </div>

                    @if($sdkReady)
                        {{-- Card first, PayPal second. Most buyers arriving here
                             have a card and no PayPal account, so the card form
                             is the open default rather than something behind a
                             second click.

                             These are PayPal Card Fields: each input is an
                             iframe hosted by PayPal, so no card number ever
                             reaches this server — but the buyer stays on this
                             page and never sees a PayPal login. --}}
                        <div id="card-block" style="display:none">
                            <div class="cardhead">
                                <i class="ri-bank-card-line"></i>
                                <span>{{ __('ui.payment_page.pay_card_title') }}</span>
                            </div>

                            <div class="cardform">
                                <label class="cf-l" for="cf-name">{{ __('ui.payment_page.card_name') }}</label>
                                <div id="cf-name" class="cf"></div>

                                <label class="cf-l" for="cf-number">{{ __('ui.payment_page.card_number') }}</label>
                                <div id="cf-number" class="cf"></div>

                                <div class="cf-row">
                                    <div>
                                        <label class="cf-l" for="cf-exp">{{ __('ui.payment_page.card_expiry') }}</label>
                                        <div id="cf-exp" class="cf"></div>
                                    </div>
                                    <div>
                                        <label class="cf-l" for="cf-cvv">{{ __('ui.payment_page.card_cvv') }}</label>
                                        <div id="cf-cvv" class="cf"></div>
                                    </div>
                                </div>

                                <button type="button" id="cf-submit" class="btn-card primary">
                                    <i class="ri-lock-line"></i>
                                    {{ __('ui.payment_page.pay_by_card_amount', ['amount' => '$' . number_format($amount, 2)]) }}
                                </button>
                            </div>
                        </div>

                        {{-- Shown only while the SDK decides whether this
                             merchant account can use inline card fields, so the
                             panel is never just blank on a slow connection. --}}
                        <div id="card-loading" class="cardskel">
                            <div class="cardhead">
                                <i class="ri-bank-card-line"></i>
                                <span>{{ __('ui.payment_page.pay_card_title') }}</span>
                            </div>
                            <div class="sk"></div><div class="sk"></div>
                            <div class="sk-row"><div class="sk"></div><div class="sk"></div></div>
                            <div class="sk-note">{{ __('ui.payment_page.card_loading') }}</div>
                        </div>

                        {{-- Fallback when the account is not approved for inline
                             card fields: PayPal's own hosted card button, which
                             still takes a card from a buyer with no PayPal
                             account. Kept in the same slot, above PayPal. --}}
                        <div id="card-fallback" style="display:none"></div>

                        <div id="pay-or" class="paysep" style="display:none">
                            <span>{{ __('ui.payment_page.or_paypal') }}</span>
                        </div>

                        <div id="paypal-button-container"></div>

                        <div class="working" id="working">
                            <span class="spin"></span>{{ __('ui.payment_page.finalising') }}
                        </div>
                        <p class="payhint">{{ __('ui.payment_page.paypal_hint') }}</p>
                    @elseif($stdReady)
                        {{-- Email-only flow: a plain POST to PayPal's hosted
                             checkout. No SDK, so no in-page card button — the
                             hosted page offers guest card payment itself. --}}
                        {{-- Two doors to the same hosted checkout. `card=1`
                             opens PayPal on the card form instead of the account
                             login; both allow guest payment. --}}
                        <form method="POST" action="{{ route('payment.paypal.standard') }}"
                              onsubmit="this.querySelectorAll('button').forEach(b => b.disabled = true)">
                            @csrf
                            @foreach($stdFields as $k => $v)
                                @if($v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
                            @endforeach

                            <button type="submit" class="btn-paypal">
                                <i class="ri-paypal-fill"></i>
                                {{ __('ui.payment_page.pay_with_paypal', ['amount' => '$' . number_format($amount, 2)]) }}
                            </button>

                            <div class="paysep"><span>{{ __('ui.payment_page.or') }}</span></div>

                            <button type="submit" name="card" value="1" class="btn-card">
                                <i class="ri-bank-card-line"></i>
                                {{ __('ui.payment_page.pay_by_card') }}
                            </button>
                        </form>
                        <p class="payhint">{{ __('ui.payment_page.card_hint') }}</p>
                    @else
                        <div class="errbox" style="margin:0">
                            <i class="ri-error-warning-line"></i>
                            <div>{{ __('ui.payment_page.not_configured') }}</div>
                        </div>
                    @endif
                </div>

                <a href="{{ $backUrl }}" class="backlink">← {{ __('ui.payment_page.back_cancel') }}</a>
            </div>
        </div>

        {{-- ═══ SUMMARY ═══ --}}
        <div>
            <div class="sum">
                <div class="sh"><h3>{{ __('ui.payment_page.summary') }}</h3></div>
                <div class="sb">
                    @if($isCart)
                        @if($cart['plan'])
                        <div class="li">
                            <div class="m"><div class="n">{{ $cart['plan']->name }}</div><div class="s">{{ __('ui.payment_page.plan_line_sub') }}</div></div>
                            <div class="v">${{ number_format($cart['plan_cost'], 2) }}</div>
                        </div>
                        @endif
                        @if($cart['seats'] > 0)
                        <div class="li">
                            <div class="m"><div class="n">{{ __('ui.payment_page.seat_line') }}</div><div class="s">{{ trans_choice('ui.payment_page.seat_line_sub', $cart['seats'], ['count' => $cart['seats']]) }}</div></div>
                            <div class="v">${{ number_format($cart['seat_cost'], 2) }}</div>
                        </div>
                        @endif
                        @if($cart['packs'] > 0)
                        <div class="li">
                            <div class="m"><div class="n">{{ __('ui.payment_page.pack_line') }}</div><div class="s">{{ __('ui.payment_page.pack_line_sub', ['n' => number_format($cart['messages'])]) }}</div></div>
                            <div class="v">${{ number_format($cart['pack_cost'], 2) }}</div>
                        </div>
                        @endif
                    @else
                    <div class="li">
                        <div class="m">
                            <div class="n">{{ $isSeat ? __('ui.payment_page.seat_line') : ($isPack ? __('ui.payment_page.pack_line') : ($plan?->name ?? '—')) }}</div>
                            <div class="s">
                                @if($isSeat)
                                    {{ trans_choice('ui.payment_page.seat_line_sub', $seats, ['count' => $seats]) }}
                                @elseif($isPack)
                                    {{ __('ui.payment_page.pack_line_sub', ['n' => number_format($messages)]) }}
                                @else
                                    {{ __('ui.payment_page.plan_line_sub') }}
                                @endif
                            </div>
                        </div>
                        <div class="v">${{ number_format($amount, 2) }}</div>
                    </div>
                    @endif
                    <div class="li">
                        <div class="m">
                            <div class="n">{{ __('ui.payment_page.workspace') }}</div>
                            <div class="s">{{ $admin?->email }}</div>
                        </div>
                        <div class="v" style="font-weight:500;color:var(--muted)">{{ $tenant->name }}</div>
                    </div>
                    <div class="divr"></div>
                    <div class="tot">
                        <span class="l">{{ __('ui.payment_page.due_now') }}</span>
                        <div class="r">
                            <b>${{ number_format($amount, 2) }}</b>
                            <span>{{ $currency }} · {{ __('ui.payment_page.excl_tax') }}</span>
                        </div>
                    </div>
                </div>
                <div class="trust">
                    <span><i class="ri-shield-check-line"></i>{{ __('ui.payment_page.trust_1') }}</span>
                    <span><i class="ri-refund-2-line"></i>{{ __('ui.payment_page.trust_2') }}</span>
                    <span><i class="ri-mail-check-line"></i>{{ __('ui.payment_page.trust_3') }}</span>
                </div>
            </div>
        </div>

    </div>
</div>
</main>

@if($ready)
{{-- enable-funding=card puts PayPal's own Visa/Mastercard guest checkout in
     the stack, so a buyer without a PayPal account can still pay by card. --}}
<script src="https://www.paypal.com/sdk/js?client-id={{ urlencode(config('services.paypal.client_id')) }}&currency={{ urlencode($currency) }}&intent=capture&enable-funding=card&components=buttons,card-fields"
        @if($clientToken) data-client-token="{{ $clientToken }}" @endif
        data-partner-attribution-id="wavadesk_saas"
        onerror="window.__ppFail && window.__ppFail()"></script>
<script>
(function () {
    const errBox  = document.getElementById('paypal-error');
    const errText = document.getElementById('paypal-error-text');
    const working = document.getElementById('working');

    function showError(msg) {
        errBox.style.display = 'flex';
        errText.textContent = msg;
        working.classList.remove('on');
        // If the SDK never loaded, nothing else will ever clear the card
        // skeleton — it would sit there shimmering under the error forever.
        const skel = document.getElementById('card-loading');
        if (skel) skel.style.display = 'none';
    }
    window.__ppFail = function () { showError(@js(__('ui.payment_page.sdk_failed'))); };

    /* Advance the step rail once PayPal hands the order back — the buyer is
       past "pay" and waiting on us, which is a different state from idle. */
    function markFinalising() {
        working.classList.add('on');
        document.getElementById('line3').classList.add('done');
        const s2 = document.querySelector('.stp[data-step="2"]');
        const s3 = document.querySelector('.stp[data-step="3"]');
        s2.classList.remove('on'); s2.classList.add('done');
        s2.querySelector('.dot').innerHTML = '<i class="ri-check-line"></i>';
        s3.classList.add('on');
    }

    if (typeof paypal === 'undefined') { window.__ppFail(); return; }

    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const ORDER_BODY = @js(match (true) {
        $isCart => [
            'kind'    => TenantPayment::KIND_CART,
            'plan_id' => $cart['plan']?->id,
            'seats'   => $cart['seats'],
            'packs'   => $cart['packs'],
        ],
        $isSeat => ['kind' => TenantPayment::KIND_SEAT_PACK, 'seats' => $seats],
        $isPack => ['kind' => TenantPayment::KIND_AI_PACK,   'packs' => $packs],
        default => ['tenant_id' => (int) $tenant->id, 'plan_id' => (int) ($plan->id ?? 0)],
    });

    /* One order pipeline for every funding source on this page: the inline
       card fields, PayPal's hosted card button and the PayPal account button
       all hit the same endpoints, so the server prices and fulfils an order
       the same way no matter which one the buyer used. */
    async function createOrder() {
        errBox.style.display = 'none';
        const res = await fetch(@js(route('payment.paypal.create-order')), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(ORDER_BODY),
        });
        const data = res.ok ? await res.json() : null;
        if (!data || !data.id) {
            showError(@js(__('ui.payment_page.start_failed')));
            throw new Error('create-order failed');
        }
        return data.id;
    }

    async function onApprove(data) {
        markFinalising();
        try {
            const res = await fetch('/payment/paypal/capture-order/' + encodeURIComponent(data.orderID), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const result = await res.json();
            if (result.success && result.redirect) {
                window.location.href = result.redirect;
                return;
            }
            showError(@js(__('ui.payment_page.capture_failed')));
        } catch (e) {
            showError(@js(__('ui.payment_page.capture_failed')));
        }
    }

    function onCancel() { working.classList.remove('on'); }

    function onError(err) {
        console.error('[PayPal]', err);
        showError(@js(__('ui.payment_page.gateway_error')));
    }

    const cardBlock    = document.getElementById('card-block');
    const cardLoading  = document.getElementById('card-loading');
    const cardFallback = document.getElementById('card-fallback');
    const payOr        = document.getElementById('pay-or');

    // The divider only earns its place once something sits above it.
    function revealSeparator() { payOr.style.display = 'flex'; }

    /* ── 1. Card, first ────────────────────────────────────────────────────
       Inline PayPal Card Fields when the merchant account is approved for
       them. The inputs are PayPal-hosted iframes, so the card number never
       reaches this origin, but the buyer types it here and is never asked to
       sign in to PayPal. */
    // On marketing (wavadesk.com) PayPal's Advanced Card Fields SDK fails
    // eligibility per-domain even though the same merchant credentials work
    // on core (app.wavadesk.com) — fixing it properly means whitelisting
    // wavadesk.com in the PayPal developer dashboard, which we don't own.
    // Meanwhile the SDK fires onError during setup and the buyer sees
    // "That card could not be processed" even though they haven't touched
    // the form yet. Skip CardFields entirely on marketing and fall straight
    // through to renderHostedCardButton() below — buyers get PayPal's own
    // hosted card page instead of inline fields, which uses the same
    // account+creds and works reliably.
    const skipCardFields = @js(\App\Support\Wavadesk::isMarketing());
    const cardFields = (!skipCardFields && typeof paypal.CardFields === 'function')
        ? paypal.CardFields({
            createOrder: createOrder,
            onApprove: onApprove,
            onError: function (err) {
                console.error('[PayPal CardFields]', err);
                showError(@js(__('ui.payment_page.card_failed')));
            },
            // No `style` and no `inputEvents`: PayPal's default field rendering
            // is left exactly as it ships, so the buyer sees PayPal's own input
            // and its own focus / error states.
        })
        : null;

    /* Not every merchant account is approved for inline card fields. When this
       one is not, fall back to PayPal's own hosted card button — still a card
       payment with no PayPal account, just entered on PayPal's page — and keep
       it in the same slot above the PayPal button. */
    function renderHostedCardButton() {
        if (!paypal.FUNDING || !paypal.FUNDING.CARD) return;

        const btn = paypal.Buttons({
            fundingSource: paypal.FUNDING.CARD,
            style: { layout: 'vertical', shape: 'rect', height: 48 },
            createOrder: createOrder,
            onApprove: onApprove,
            onCancel: onCancel,
            onError: onError,
        });

        if (!btn.isEligible()) return;

        cardFallback.style.display = 'block';
        btn.render('#card-fallback').then(revealSeparator).catch(function () {
            cardFallback.style.display = 'none';
        });
    }

    if (cardFields && cardFields.isEligible()) {
        Promise.all([
            cardFields.NameField().render('#cf-name'),
            cardFields.NumberField().render('#cf-number'),
            cardFields.ExpiryField().render('#cf-exp'),
            cardFields.CVVField().render('#cf-cvv'),
        ]).then(function () {
            cardLoading.style.display = 'none';
            cardBlock.style.display = 'block';
            revealSeparator();
        }).catch(function (e) {
            console.error('[PayPal CardFields] render', e);
            cardLoading.style.display = 'none';
            renderHostedCardButton();
        });

        const cfBtn = document.getElementById('cf-submit');
        cfBtn.addEventListener('click', async function () {
            cfBtn.disabled = true;
            try {
                await cardFields.submit();
            } catch (e) {
                console.error('[PayPal CardFields] submit', e);
                showError(@js(__('ui.payment_page.card_failed')));
            } finally {
                cfBtn.disabled = false;
            }
        });
    } else {
        cardLoading.style.display = 'none';
        renderHostedCardButton();
    }

    /* ── 2. PayPal account button, below the card ──────────────────────────
       Restricted to the PayPal funding source: the card option is already
       presented above, and a second card button here would just be a
       duplicate of it. */
    paypal.Buttons({
        fundingSource: paypal.FUNDING.PAYPAL,
        style: { layout: 'vertical', shape: 'rect', label: 'paypal', height: 48 },
        createOrder: createOrder,
        onApprove: onApprove,
        onCancel: onCancel,
        onError: onError,
    }).render('#paypal-button-container').catch(function () {
        showError(@js(__('ui.payment_page.render_failed')));
    });

})();
</script>
@endif
</body>
</html>
