<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TermsAcceptanceController extends Controller
{
    public function form(): View|RedirectResponse
    {
        if (Auth::user()->hasAcceptedCurrentTerms()) {
            return redirect()->route('dashboard.index');
        }

        return view('legal.accept-terms');
    }

    public function store(): RedirectResponse
    {
        Auth::user()->acceptCurrentTerms();

        return redirect()->route('dashboard.index');
    }
}
