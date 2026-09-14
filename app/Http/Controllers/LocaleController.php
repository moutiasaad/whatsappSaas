<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Language entry points: /ar, /fr/features/whatsapp-multi-agent and so on.
     *
     * These exist so a link can be shared in a specific language. The prefix is
     * NOT a canonical URL — it only records the choice in the session and then
     * bounces to the clean path, so the language never sticks in the address
     * bar and there is no second, duplicate URL for every page.
     *
     * 302 rather than 301 on purpose: the destination renders differently per
     * session, so a browser must not cache this hop.
     */
    public function enter(Request $request, string $locale, string $path = ''): RedirectResponse
    {
        $supported = array_keys(config('locales.supported', []));

        abort_unless(in_array($locale, $supported, true), 404);

        $request->session()->put('locale', $locale);

        return redirect()->to($this->sameHostPath($path, $request->getQueryString()), 302);
    }

    /**
     * Rebuild a destination from the captured path segment, keeping it on this
     * host. The segment comes straight off the URL, so anything that could
     * leave the site — an absolute URL, or the protocol-relative "//host" form
     * — is dropped rather than followed. Without this the route would be an
     * open redirect.
     */
    private function sameHostPath(string $path, ?string $query): string
    {
        $path = ltrim($path, '/');

        if ($path !== '' && (str_contains($path, '://') || str_contains($path, "\\"))) {
            $path = '';
        }

        $target = $path === '' ? '/' : '/' . $path;

        return $query ? $target . '?' . $query : $target;
    }

    public function update(Request $request): RedirectResponse
    {
        $supportedLocales = array_keys(config('locales.supported', []));

        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:' . implode(',', $supportedLocales)],
            'redirect' => ['nullable', 'string'],
        ]);

        $request->session()->put('locale', $validated['locale']);

        if (!empty($validated['redirect'])) {
            return redirect()->to($validated['redirect']);
        }

        return back();
    }
}

