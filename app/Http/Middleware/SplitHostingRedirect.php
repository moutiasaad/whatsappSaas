<?php

namespace App\Http\Middleware;

use App\Support\Wavadesk;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * SplitHostingRedirect
 *
 * Keeps each half of the split deployment answering only for the pages it
 * owns.
 *
 *   wavadesk.com (marketing) owns the public site: landing, features, legal,
 *   and the login/register forms that proxy to the core app. It owns no
 *   database worth the name, so a panel, a payment or an API answered here
 *   reads and writes the wrong rows.
 *
 *   app.wavadesk.com (core) owns the application. Its inherited copies of the
 *   public pages are the monolith's, and must not stay reachable: a guest who
 *   lands on app.wavadesk.com/login signs in through the local form, skipping
 *   the marketing proxy and the SSO handoff entirely. Two front doors to the
 *   same account, and the split only knows about one of them. That is what
 *   sends a user who just logged out straight back to the wrong login page.
 *
 * Both directions fail safe. Core delegates only when WAVADESK_MARKETING_ORIGIN
 * names a different host; unset, or pointing at itself, it serves its own pages
 * exactly as before. So an un-split box, a dev machine, a replica and a
 * marketing host rolled back to `core` are all unaffected.
 *
 * @see \App\Support\Wavadesk::delegatesToMarketing()
 * @see \App\Support\Wavadesk::delegatesToCore()
 */
class SplitHostingRedirect
{
    /**
     * The only paths core hands back to the marketing site.
     *
     * An allow-list, not a pattern: everything else stays on core, so adding a
     * route to the app can never silently move it onto the host that cannot
     * serve it. The staff doors (/admin/login, /agent/login, /supervisor/login
     * and the control panel) are deliberately absent — they are separate doors
     * into core and were never part of the marketing flow.
     */
    private const CORE_DELEGATES = [
        '/',
        'login',
        'register',
    ];

    /**
     * The only paths the marketing site answers itself.
     *
     * Deny-by-default in this direction, for the opposite reason: a route
     * added to the monolith later must not start answering from the host whose
     * database is a stale copy. Anything not listed goes to core, including
     * /payment/*, /register/plan, every panel and every API.
     */
    private const MARKETING_KEEPS = [
        '/',
        'login',
        'register',
        'logout',
        'locale',
        'up',
        'features',
        'features/*',
        'legal/*',
        'docs/api',
        'sitemap.xml',
        'robots.txt',
        'favicon.ico',
        'build/*',
        'css/*',
        'js/*',
        'images/*',
        'fonts/*',
        'storage/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        if (Wavadesk::isCore()) {
            // Never bounce a signed-in user off the app they are using. Only
            // the guest-facing copies of these pages are the problem, and the
            // `guest` middleware already sends a logged-in visitor home.
            if (Auth::check() || ! Wavadesk::delegatesToMarketing($host)) {
                return $next($request);
            }

            return $request->is(...self::CORE_DELEGATES)
                ? $this->handOff(Wavadesk::marketingUrlTo($this->target($request)), $request)
                : $next($request);
        }

        if (! Wavadesk::delegatesToCore($host)) {
            return $next($request);
        }

        return $request->is(...self::MARKETING_KEEPS)
            ? $next($request)
            : $this->handOff(Wavadesk::coreUrlTo($this->target($request)), $request);
    }

    /**
     * The current path and query, ready to append to the peer's origin.
     *
     * The raw QUERY_STRING, not Request::getQueryString(), which sorts the
     * parameters alphabetically. A handoff should forward what arrived,
     * byte for byte: a third party that signs its callback over the query in
     * the order it sent has no reason to survive our re-ordering.
     */
    private function target(Request $request): string
    {
        $path  = $request->path();
        $query = (string) $request->server->get('QUERY_STRING', '');

        return ($path === '/' ? '/' : '/' . $path) . ($query === '' ? '' : '?' . $query);
    }

    /**
     * 302 for a GET: ordinary navigation, and deliberately not permanent —
     * a 301 would be cached by every browser that saw it and would outlive a
     * rollback. 308 for anything else, because it is the only redirect that
     * keeps the method and body, so a POST that belongs to the other host
     * arrives there as a POST rather than as a GET with its payload dropped.
     */
    private function handOff(string $url, Request $request): Response
    {
        return redirect()->away($url, $request->isMethodSafe() ? 302 : 308);
    }
}
