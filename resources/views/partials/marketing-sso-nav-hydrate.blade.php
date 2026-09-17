{{-- SSO nav hydration for the marketing site.

     Every marketing entry point that renders its own CTAs (landing.blade.php
     directly, and partials/marketing-nav.blade.php on the features tree)
     must:
       1. wrap the sign-in / start-free-trial anchors in
          <span id="wavadesk-nav-actions">
       2. include this partial right after that span

     On core (Wavadesk::isCore()) the script does not render at all — the
     SSR branch already knows the visitor's identity from the session and
     rendered "Go to dashboard" up front. On marketing this fetches
     https://app.wavadesk.com/api/v1/session/status with credentials so
     SameSite=Lax core session cookies ride along, then swaps the CTAs. --}}
@if(\App\Support\Wavadesk::isMarketing() && \App\Support\Wavadesk::coreUrl() !== '')
<script>
(function(){
    var el = document.getElementById('wavadesk-nav-actions');
    if (!el) return;

    var endpoint = @json(\App\Support\Wavadesk::coreUrlTo('/api/v1/session/status'));
    var coreOrigin = @json(\App\Support\Wavadesk::coreUrl());
    var mobileEl = document.getElementById('wavadesk-mnav-cta');

    // Label is pulled from the same translation key the SSR branch uses
    // so a locale swap doesn't drift out of sync.
    var dashboardLabel = @json(__('landing.go_to_dashboard'));

    // Expose the endpoint on the container so devtools can grab it via
    // document.getElementById('wavadesk-nav-actions').dataset.endpoint
    // without hunting through inline scripts.
    el.setAttribute('data-endpoint', endpoint);

    // Global tap for postmortem inspection: after the fetch settles,
    // window.__wavadesk_sso holds { ok, status, response|error, at }.
    // Read it in the console when the CTAs aren't swapping.
    window.__wavadesk_sso = { pending: true, endpoint: endpoint };

    console.info('[wavadesk-sso] checking core session at', endpoint);

    fetch(endpoint, {
        method: 'GET',
        credentials: 'include',
        mode: 'cors',
        headers: { 'Accept': 'application/json' }
    })
    .then(function (r) {
        window.__wavadesk_sso.status = r.status;
        window.__wavadesk_sso.pending = false;
        if (!r.ok) {
            console.warn('[wavadesk-sso] endpoint returned', r.status, '— header stays signed-out');
            return null;
        }
        return r.json();
    })
    .then(function (data) {
        window.__wavadesk_sso.response = data;

        if (!data) return;
        if (!data.authenticated) {
            console.info('[wavadesk-sso] not signed in on core — header stays signed-out');
            return;
        }
        if (!data.home_url) {
            console.warn('[wavadesk-sso] signed in but home_url missing from response');
            return;
        }

        // Defence in depth: only accept a home URL that lives on the core
        // origin. Guards against a rogue future response value ending up as
        // a javascript: href.
        if (data.home_url.indexOf(coreOrigin + '/') !== 0) {
            console.warn('[wavadesk-sso] home_url', data.home_url, 'not on core origin', coreOrigin);
            return;
        }

        // The desktop container's class list drives its visible layout; the
        // .btn.p sm class matches the SSR branch, .btn.p (no sm) matches
        // landing.blade.php's own SSR branch. Read the first anchor's class
        // list, fall back to a sensible default.
        var desktopClass = 'btn p sm';
        var firstAnchor = el.querySelector('a.btn');
        if (firstAnchor) desktopClass = firstAnchor.className;

        var a = document.createElement('a');
        a.className = desktopClass;
        a.href = data.home_url;
        a.textContent = dashboardLabel;
        el.replaceChildren(a);

        if (mobileEl) {
            var mobileClass = 'btn p';
            var firstMobileBtn = mobileEl.querySelector('a.btn');
            if (firstMobileBtn) mobileClass = firstMobileBtn.className;

            var m = document.createElement('a');
            m.className = mobileClass;
            m.href = data.home_url;
            m.textContent = dashboardLabel;
            mobileEl.replaceChildren(m);
        }

        console.info('[wavadesk-sso] header swapped for', data.user && data.user.name);
    })
    .catch(function (e) {
        window.__wavadesk_sso.pending = false;
        window.__wavadesk_sso.error = e && (e.message || String(e));
        // A TypeError here is almost always CORS: the endpoint returned but
        // the browser refused to expose the body because the Origin didn't
        // match WAVADESK_MARKETING_ORIGIN on core. Second suspect is the
        // network — refused connection, DNS, etc.
        console.warn('[wavadesk-sso] fetch failed:', e, '— likely CORS (missing/mismatched Access-Control-Allow-Origin) or a network error. Check the Network tab for the raw response.');
    });
})();
</script>
@endif
