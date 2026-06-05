<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalPage;
use Illuminate\Http\Request;

class LegalPageController extends Controller
{
    private const PAGES = [
        'terms'   => ['label_key' => 'footer_terms',   'icon' => 'ri-file-text-line',    'route' => 'legal.terms'],
        'privacy' => ['label_key' => 'footer_privacy',  'icon' => 'ri-shield-check-line', 'route' => 'legal.privacy'],
        'cookies' => ['label_key' => 'footer_cookies',  'icon' => 'ri-cookie-line',       'route' => 'legal.cookies'],
    ];

    private const LOCALES = [
        'en' => ['label' => 'English', 'flag' => '🇬🇧'],
        'fr' => ['label' => 'Français', 'flag' => '🇫🇷'],
        'ar' => ['label' => 'العربية', 'flag' => '🇸🇦'],
    ];

    public function index()
    {
        $records = LegalPage::all()->groupBy('slug');

        return view('admin.legal-pages.index', [
            'pages'   => self::PAGES,
            'locales' => self::LOCALES,
            'records' => $records,
        ]);
    }

    public function edit(string $slug, string $locale)
    {
        abort_if(!array_key_exists($slug, self::PAGES), 404);
        abort_if(!array_key_exists($locale, self::LOCALES), 404);

        $page = LegalPage::firstOrNew(
            ['slug' => $slug, 'locale' => $locale],
            ['title' => __('landing.' . self::PAGES[$slug]['label_key'])]
        );

        return view('admin.legal-pages.edit', [
            'page'    => $page,
            'slug'    => $slug,
            'locale'  => $locale,
            'pages'   => self::PAGES,
            'locales' => self::LOCALES,
        ]);
    }

    public function update(Request $request, string $slug, string $locale)
    {
        abort_if(!array_key_exists($slug, self::PAGES), 404);
        abort_if(!array_key_exists($locale, self::LOCALES), 404);

        $data = $request->validate([
            'title'   => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        LegalPage::updateOrCreate(
            ['slug' => $slug, 'locale' => $locale],
            $data
        );

        return back()->with('success', 'Page saved successfully.');
    }
}
