<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
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

