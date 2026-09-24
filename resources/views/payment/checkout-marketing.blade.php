@php
    $isRtl       = (bool) data_get(config('locales.supported', []), app()->getLocale() . '.rtl');
    $backUrl     = $backUrl ?? url('/register/plan');
    $homeUrl     = url('/');
    // Currency is what will be CHARGED. Passed from the controller after
    // Plan::priceFor() resolves the visitor's country. Defaults kept for
    // any legacy caller that predates the country-aware update.
    $currency       = $currency       ?? config('services.paypal.currency', 'USD');
    $currencySymbol = $currencySymbol ?? '$';
    $isLocal        = (bool) ($isLocal ?? false);
    $baseAmount     = (float) ($baseAmount ?? $amount);

    // Format the visible price string. Latin symbols prefix ($39, €39),
    // non-Latin follow (149 ر.س) — matches Plan::formatLocalPrice(). When
    // the visitor is being charged in a non-USD currency, append the base
    // USD in parens ("149 ر.س (≈ $39)") so they always see the reference.
    $fmtAmount = function ($value, $symbol) {
        $value = (float) $value;
        $n = fmod($value, 1.0) === 0.0 ? number_format($value, 0) : number_format($value, 2);
        return in_array($symbol, ['$','€','£','¥','₹'], true) ? $symbol . $n : $n . ' ' . $symbol;
    };

    $displayPrice = $fmtAmount($amount, $currencySymbol);
    $baseUsdPrice = $fmtAmount($baseAmount, '$');
    // Only show the "(≈ $X)" suffix when local currency differs from USD;
    // showing "($39)" next to "$39" would just be noise.
    $displayWithBase = $isLocal && strtoupper($currency) !== 'USD'
        ? $displayPrice . ' (≈ ' . $baseUsdPrice . ')'
        : $displayPrice;

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

        .paysplit{display:flex;align-items:center;gap:14px;margin:30px 0 20px;color:var(--muted-2);font-size:12px;font-weight:600;letter-spacing:.08em;text-transform:uppercase}
        .paysplit::before,.paysplit::after{content:"";flex:1;height:1px;background:var(--border)}

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
        .btn-card.primary{background:var(--teal);border-color:var(--teal);color:#fff;font-weight:800;font-size:15.5px}
        .btn-card.primary:hover{background:var(--teal-d);border-color:var(--teal-d)}
        .btn-card.primary i{color:#fff;font-size:17px}

        .cardhead{display:flex;align-items:center;gap:8px;font-size:13.5px;font-weight:700;margin-bottom:13px}
        .cardhead i{font-size:17px;color:var(--teal)}

        .cardskel .sk{height:46px;border-radius:10px;margin-bottom:13px;background:linear-gradient(90deg,var(--soft) 25%,#eef2f5 37%,var(--soft) 63%);background-size:400% 100%;animation:skel 1.4s ease infinite}
        .cardskel .sk-row{display:grid;grid-template-columns:1fr 1fr;gap:11px}
        .cardskel .sk-note{font-size:12px;color:var(--muted);text-align:center;margin-top:2px}
        @keyframes skel{0%{background-position:100% 50%}100%{background-position:0 50%}}
        @media (prefers-reduced-motion:reduce){.cardskel .sk{animation:none}}

        .cardform{display:flex;flex-direction:column;gap:0}
        .cf-l{font-size:12.5px;font-weight:600;color:var(--muted);margin-bottom:6px;display:block}
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

        {{-- LEFT --}}
        <div class="card">
            <div class="ch">
                <h2>{{ __('ui.payment_page.plan_title') }}</h2>
                <p>{{ __('ui.payment_page.plan_sub', ['tenant' => $tenant->name]) }}</p>
            </div>
            <div class="cb">

                @if($errors->any())
                <div class="errbox">
                    <i class="ri-error-warning-line"></i>
                    <div>{{ $errors->first() }}</div>
                </div>
                @endif

                <div class="buy">
                    <div class="ic"><i class="ri-vip-crown-2-line"></i></div>
                    <div class="m">
                        <div class="n">{{ __('ui.payment_page.plan_name', ['plan' => $plan->name]) }}</div>
                        <div class="s">{{ __('ui.payment_page.plan_billed_monthly') }}</div>
                    </div>
                    <div class="amt">{{ $displayWithBase }}</div>
                </div>

                <ul class="feats">
                    <li><i class="ri-check-line"></i>{{ __('landing.plan_unlimited_headline') }}</li>
                    @if($plan->max_users)
                    <li><i class="ri-check-line"></i>{{ __('ui.platform_plans_page.users_limit', ['count' => $plan->max_users]) }}</li>
                    @endif
                    <li><i class="ri-check-line"></i>
                        {{ $plan->ai_message_quota === null
                            ? __('landing.attr_ai_unlimited')
                            : __('landing.attr_ai_messages', ['n' => number_format((int) $plan->ai_message_quota)]) }}
                    </li>
                    <li><i class="ri-check-line"></i>{{ __('ui.payment_page.plan_feat_cancel') }}</li>
                </ul>

                <div class="note">
                    <i class="ri-information-line"></i>
                    <div>{{ __('ui.payment_page.plan_note') }}</div>
                </div>

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

                    <div class="cards">
                        <span class="cm visa" aria-label="Visa">VISA</span>
                        <span class="cm mc" aria-hidden="true"><i></i><i></i></span>
                        <span class="cm amex" aria-label="American Express">AMEX</span>
                        <span class="t">{{ __('ui.payment_page.cards_accepted') }}</span>
                    </div>

                    {{-- No NCP branch here on purpose. This host cannot run that
                         flow: the confirm URL PayPal returns to reads a single-use
                         intent out of core's session, and this box's session is not
                         core's. Linking straight to the NCP page from here would
                         take the buyer's money and leave the plan unactivated.
                         Core decides instead — /payment/paypal/initiate proxies to
                         its billing API, which answers with a signed link into its
                         own NCP checkout when the plan has one. --}}
                    {{-- Single-button PayPal redirect flow. Marketing POSTs
                         to /payment/paypal/initiate which proxies to core; core
                         answers with wherever the buyer should go — a PayPal
                         Orders API approval URL, or a signed link into its own
                         NCP checkout when the plan is paid on a fixed PayPal
                         page — and the browser is 302-redirected there. PayPal
                         returns to core either way, which captures or confirms
                         and auto-logs the buyer in from the signed SSO handoff. --}}
                    <form method="POST" action="{{ route('payment.paypal.initiate') }}"
                          onsubmit="this.querySelectorAll('button').forEach(b => b.disabled = true)">
                        @csrf
                        <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                        <button type="submit" class="btn-paypal">
                            <i class="ri-paypal-fill"></i>
                            {{ __('ui.payment_page.pay_with_paypal', ['amount' => $displayPrice]) }}
                        </button>
                    </form>
                    <p class="payhint">{{ __('ui.payment_page.paypal_hint') }}</p>
                </div>

                <a href="{{ $backUrl }}" class="backlink">← {{ __('ui.payment_page.back_cancel') }}</a>
            </div>
        </div>

        {{-- RIGHT --}}
        <div>
            <div class="sum">
                <div class="sh"><h3>{{ __('ui.payment_page.summary') }}</h3></div>
                <div class="sb">
                    <div class="li">
                        <div class="m">
                            <div class="n">{{ $plan->name }}</div>
                            <div class="s">{{ __('ui.payment_page.plan_line_sub') }}</div>
                        </div>
                        <div class="v">{{ $displayWithBase }}</div>
                    </div>
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
                            <b>{{ $displayPrice }}</b>
                            @if($isLocal && strtoupper($currency) !== 'USD')
                                <span style="display:block;font-size:.75rem;color:var(--muted);">≈ {{ $baseUsdPrice }} USD · {{ __('ui.payment_page.excl_tax') }}</span>
                            @else
                                <span>{{ $currency }} · {{ __('ui.payment_page.excl_tax') }}</span>
                            @endif
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

</body>
</html>
