<?php

namespace App\Http\Controllers;

use App\Models\LegalPage;
use App\Models\Plan;
use Illuminate\Support\Facades\Auth;

class LandingController extends Controller
{
    public function index()
    {
        // The marketing site stays reachable while signed in — the page swaps its
        // signup CTAs for a link back to the user's own panel instead.
        $plans = Plan::where('is_active', true)->orderBy('id')->get();

        $homeRoute = Auth::check()
            ? route(Auth::user()->homeRouteName())
            : null;

        return view('landing', compact('plans', 'homeRoute'));
    }

    public function terms()
    {
        $dbPage = LegalPage::forSlug('terms', app()->getLocale());
        return view('legal.terms', ['dbPage' => $dbPage]);
    }

    public function privacy()
    {
        $dbPage = LegalPage::forSlug('privacy', app()->getLocale());
        return view('legal.privacy', ['dbPage' => $dbPage]);
    }

    public function cookies()
    {
        $dbPage = LegalPage::forSlug('cookies', app()->getLocale());
        return view('legal.cookies', ['dbPage' => $dbPage]);
    }
}
