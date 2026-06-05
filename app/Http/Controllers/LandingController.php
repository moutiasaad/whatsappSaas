<?php

namespace App\Http\Controllers;

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
        return view('legal.terms');
    }

    public function privacy()
    {
        return view('legal.privacy');
    }

    public function cookies()
    {
        return view('legal.cookies');
    }
}
