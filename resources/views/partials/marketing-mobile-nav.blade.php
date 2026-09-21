{{-- Mobile navigation for the marketing surfaces.

     The header hides its links below 980px, and until now put nothing in their
     place — the product menu, pricing and FAQ were simply unreachable on a
     phone. This adds the button and the panel behind it.

     Shared by the landing page and every /features page. Both carry the same
     header markup but load different stylesheets, so the styles and behaviour
     travel with the component rather than being written twice.

     Include this AFTER the header element, never inside it — see the note in
     partials/marketing-mobile-nav-button.blade.php. The button is included
     separately, inside the header row.

     Language links go through the /{locale}/{path} entry route, so they need no
     CSRF token and keep the reader on the page they were on. The dropdown
     switcher in the desktop header binds with querySelector and would break if
     it appeared twice, which is the other reason this does not reuse it. --}}
@php
    $mnavPath   = trim(request()->path(), '/');
    $mnavLocale = app()->getLocale();
    $mnavItems  = config('seo_pages', []);
    $mnavIcons  = [
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



<div class="mnav" id="mnav" data-mnav aria-hidden="true" inert>
    <div class="mnav-in">
        <div class="mnav-sec">{{ __('landing.nav_product') }}</div>
        <div class="mnav-feats">
            @foreach($mnavItems as $slug => $f)
                <a href="{{ url('/features/' . $slug) }}">
                    <span class="mnav-ic"><i class="{{ $mnavIcons[$slug] ?? 'ri-checkbox-blank-circle-line' }}"></i></span>
                    <span class="mnav-txt">
                        <b>{{ __('features.' . $slug . '.nav_title') }}</b>
                        <span>{{ __('features.' . $slug . '.nav_sub') }}</span>
                    </span>
                </a>
            @endforeach
        </div>

        <div class="mnav-sec">{{ __('landing.nav_primary') }}</div>
        <div class="mnav-links">
            <a href="{{ url('/#how') }}">{{ __('landing.nav_how') }}</a>
            <a href="{{ url('/#pricing') }}">{{ __('landing.nav_pricing') }}</a>
            <a href="{{ url('/#faq') }}">{{ __('landing.nav_faq') }}</a>
        </div>

        @if(count(config('locales.supported', [])) > 1)
            <div class="mnav-sec">{{ __('landing.nav_language') }}</div>
            <div class="mnav-langs">
                @foreach(config('locales.supported', []) as $code => $meta)
                    <a class="{{ $mnavLocale === $code ? 'on' : '' }}"
                       href="{{ url('/' . $code . ($mnavPath === '' ? '' : '/' . $mnavPath)) }}"
                       @if($mnavLocale === $code) aria-current="true" @endif>
                        {{ $meta['native'] ?? strtoupper($code) }}
                    </a>
                @endforeach
            </div>
        @endif

        {{-- id lets marketing-nav.blade.php's hydration swap the mobile CTA
             the same time it swaps the desktop one. See the <script> tag at
             the bottom of that partial. --}}
        <div class="mnav-cta" id="wavadesk-mnav-cta">
            @if($homeRoute ?? null)
                <a class="btn p" href="{{ $homeRoute }}">{{ __('landing.go_to_dashboard') }}</a>
            @else
                <a class="btn p" href="{{ route('register') }}">{{ __('landing.nav_cta') }}</a>
                <a class="mnav-si" href="{{ route('login') }}">{{ __('landing.nav_signin') }}</a>
            @endif
        </div>
    </div>
</div>

<style>
/* Desktop keeps the existing header untouched; everything here is mobile-only. */
.mnav-t{display:none}
.mnav{display:none}

@media (max-width:980px){
    .mnav-t{
        display:inline-flex;align-items:center;justify-content:center;
        width:42px;height:42px;flex:0 0 42px;margin-inline-start:4px;
        border:1px solid var(--border);border-radius:11px;background:#fff;
        cursor:pointer;padding:0;color:var(--text);
    }
    .mnav-bars{display:block;width:18px;height:13px;position:relative}
    .mnav-bars i{
        position:absolute;inset-inline:0;height:2px;border-radius:2px;
        background:currentColor;transition:transform .22s ease,opacity .18s ease;
    }
    .mnav-bars i:nth-child(1){top:0}
    .mnav-bars i:nth-child(2){top:5.5px}
    .mnav-bars i:nth-child(3){top:11px}
    .mnav-t[aria-expanded="true"] .mnav-bars i:nth-child(1){transform:translateY(5.5px) rotate(45deg)}
    .mnav-t[aria-expanded="true"] .mnav-bars i:nth-child(2){opacity:0}
    .mnav-t[aria-expanded="true"] .mnav-bars i:nth-child(3){transform:translateY(-5.5px) rotate(-45deg)}

    /* Slides down from under the sticky header, so it behaves the same in LTR
       and RTL without mirroring a horizontal transform. --navh is measured from
       the real header height at runtime. */
    .mnav{
        display:block;position:fixed;z-index:49;
        top:var(--navh,70px);inset-inline:0;bottom:0;
        background:#fff;overflow-y:auto;-webkit-overflow-scrolling:touch;
        opacity:0;visibility:hidden;transform:translateY(-10px);
        transition:opacity .2s ease,transform .22s ease,visibility .22s;
        overscroll-behavior:contain;
    }
    .mnav.open{opacity:1;visibility:visible;transform:translateY(0)}
    .mnav-in{padding:18px 18px calc(30px + env(safe-area-inset-bottom))}

    .mnav-sec{
        font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
        color:var(--muted);margin:6px 0 10px;
    }
    .mnav-feats{display:grid;gap:2px;margin-bottom:22px}
    .mnav-feats a{display:flex;align-items:center;gap:12px;padding:11px 10px;border-radius:11px;color:var(--text)}
    .mnav-feats a:active{background:var(--soft,#f5f8f8)}
    .mnav-ic{
        width:36px;height:36px;flex:0 0 36px;border-radius:10px;
        display:grid;place-items:center;background:rgba(15,126,122,.09);
    }
    .mnav-ic i{font-size:18px;color:#0f7e7a}
    .mnav-txt{min-width:0}
    .mnav-txt b{display:block;font-size:14.5px;font-weight:600;line-height:1.3}
    .mnav-txt span{display:block;font-size:12.5px;color:var(--muted);margin-top:2px;line-height:1.35}

    .mnav-links{display:grid;gap:2px;margin-bottom:22px}
    .mnav-links a{display:block;padding:12px 10px;border-radius:11px;font-size:15.5px;font-weight:500;color:var(--text)}
    .mnav-links a:active{background:var(--soft,#f5f8f8)}

    .mnav-langs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:24px}
    .mnav-langs a{
        padding:9px 15px;border:1px solid var(--border);border-radius:999px;
        font-size:14px;font-weight:500;color:var(--muted);
    }
    .mnav-langs a.on{border-color:#0f7e7a;color:#0f7e7a;font-weight:700;background:rgba(15,126,122,.07)}

    .mnav-cta{display:grid;gap:12px;border-top:1px solid var(--border);padding-top:20px}
    .mnav-cta .btn{width:100%;height:50px;font-size:15.5px}
    .mnav-si{text-align:center;font-size:15px;color:var(--muted);font-weight:500;padding:6px}

    /* The header itself has to survive 390px: the desktop row of language
       picker + sign-in + CTA overflowed the viewport by ~41px on /features.
       Hamburger has flex-shrink:0 so it never gets pushed off-screen; the
       CTA button gets to shrink instead (min-width:0 + truncate). */
    .nav .in{gap:10px;min-width:0}
    .nav .brand{flex-shrink:1;min-width:0;overflow:hidden}
    .nav .act{gap:8px;min-width:0;flex-shrink:1}
    .nav .act a.si{display:none}
    .nav .act .lang-wrap{display:none}
    .nav .btn.sm{height:40px;padding:0 14px;font-size:14px;white-space:nowrap;min-width:0;overflow:hidden;text-overflow:ellipsis;flex-shrink:1}
    .mnav-t{flex-shrink:0}
    .brand .wm{font-size:19px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0}
}

@media (max-width:440px){
    /* Signed-in state on French/Arabic has a MUCH longer CTA label
       ("Accéder à mon tableau de bord" — 29 chars) than the signed-out one
       ("Start free trial" — 16). Shrink the button and hide the wordmark
       so the hamburger stays on screen no matter which state we render. */
    .brand .wm{display:none}
    .nav .in{gap:8px}
    .nav .act{gap:6px}
    .nav .act .btn.sm{padding:0 12px;font-size:13.5px;max-width:220px}
}

@media (max-width:360px){
    /* Very narrow phones — iPhone Mini (360), older Androids. Squeeze
       more from the CTA before the hamburger is at risk. */
    .nav .act .btn.sm{padding:0 10px;font-size:12.5px;max-width:180px}
    .nav .in{gap:6px}
    .nav .act{gap:4px}
    .mnav-t{width:38px;height:38px;flex:0 0 38px}
}

@media (prefers-reduced-motion:reduce){
    .mnav{transition:none}
    .mnav-bars i{transition:none}
}

body.mnav-lock{overflow:hidden}
</style>

<script>
(function () {
    const btn = document.querySelector('[data-mnav-toggle]');
    const panel = document.querySelector('[data-mnav]');
    const header = document.querySelector('.nav');
    if (!btn || !panel) return;

    // The panel hangs off the bottom of the real header rather than a guessed
    // height, so it still lines up if the header wraps or changes size.
    function syncOffset() {
        const h = header ? Math.round(header.getBoundingClientRect().height) : 70;
        document.documentElement.style.setProperty('--navh', h + 'px');
    }

    function setOpen(open) {
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.setAttribute('aria-label', open
            ? @js(__('landing.nav_menu_close'))
            : @js(__('landing.nav_menu_open')));
        panel.classList.toggle('open', open);
        panel.setAttribute('aria-hidden', open ? 'false' : 'true');
        // inert keeps the closed panel out of the tab order and off screen
        // readers, which visibility:hidden alone does not guarantee mid-transition.
        if (open) { panel.removeAttribute('inert'); } else { panel.setAttribute('inert', ''); }
        document.body.classList.toggle('mnav-lock', open);
        if (open) syncOffset();
    }

    syncOffset();
    window.addEventListener('resize', function () {
        syncOffset();
        // Crossing back to desktop must not leave the body scroll-locked.
        if (window.innerWidth > 980 && panel.classList.contains('open')) setOpen(false);
    });

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        setOpen(!panel.classList.contains('open'));
    });

    // Following a link inside the panel navigates; close so a same-page anchor
    // does not leave the overlay covering the section it just jumped to.
    panel.addEventListener('click', function (e) {
        if (e.target.closest('a')) setOpen(false);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && panel.classList.contains('open')) {
            setOpen(false);
            btn.focus();
        }
    });
})();
</script>
