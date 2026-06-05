<?php

namespace App\Http\Controllers;

use App\Models\LegalPage;
use App\Models\Plan;
use Illuminate\Support\Facades\Auth;

class LandingController extends Controller
{
    public function index()
    {
        if (Auth::check()) {
            return redirect()->route(auth()->user()->homeRouteName());
        }

        $plans = Plan::where('is_active', true)->orderBy('id')->get();

        return view('landing', compact('plans'));
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
