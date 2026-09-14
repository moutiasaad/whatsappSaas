<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The /features/<slug> content pages.
 *
 * Content is static and lives in config/seo_pages.php plus one Blade partial
 * per page. These exist to rank, so every one carries a unique title and
 * description, a canonical URL, OG/Twitter tags and JSON-LD built from the
 * same FAQ array the visible accordions render — structured data that
 * disagrees with the page is worse than none at all.
 *
 * They follow the session locale. Article bodies live in
 * resources/views/features/content/<locale>/<slug>.blade.php, and any locale
 * that has not been translated yet falls back to the English body via
 * @includeFirst, so the chrome and the article never disagree.
 */
class FeatureController extends Controller
{
    public function index()
    {
        return view('features.index', [
            'pages'     => config('seo_pages', []),
            'homeRoute' => Auth::check() ? route(Auth::user()->homeRouteName()) : null,
        ]);
    }

    public function show(string $slug)
    {
        $page = config("seo_pages.$slug");

        abort_if($page === null, Response::HTTP_NOT_FOUND);

        return view('features.show', [
            'slug'      => $slug,
            'page'      => $page,
            'pages'     => config('seo_pages', []),
            // The pricing CTA quotes the real entry price rather than a
            // hardcoded number that drifts the first time a plan is edited.
            'fromPlan'  => Plan::where('is_active', true)->orderBy('price_monthly')->first(),
            'homeRoute' => Auth::check() ? route(Auth::user()->homeRouteName()) : null,
        ]);
    }

    /**
     * XML sitemap. Built from the same page list the routes use, so a new
     * feature page is crawlable the moment it exists.
     */
    public function sitemap()
    {
        $urls = [
            ['loc' => url('/'),          'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => url('/features'),  'priority' => '0.8', 'changefreq' => 'monthly'],
        ];

        foreach (array_keys(config('seo_pages', [])) as $slug) {
            $urls[] = ['loc' => url("/features/$slug"), 'priority' => '0.8', 'changefreq' => 'monthly'];
        }

        foreach (['terms', 'privacy', 'cookies'] as $legal) {
            $urls[] = ['loc' => url("/legal/$legal"), 'priority' => '0.3', 'changefreq' => 'yearly'];
        }

        return response()
            ->view('seo.sitemap', ['urls' => $urls, 'lastmod' => now()->toDateString()])
            ->header('Content-Type', 'application/xml');
    }

    public function robots()
    {
        return response()
            ->view('seo.robots', ['sitemap' => url('/sitemap.xml')])
            ->header('Content-Type', 'text/plain');
    }
}
