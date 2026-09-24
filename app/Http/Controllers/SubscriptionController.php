<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function upgrade(): View
    {
        return view('subscription.upgrade', [
            'user' => Auth::user()->load('subscription'),
            'features' => config('plans.features'),
            'highlight' => session('paywall_feature'),
        ]);
    }
}
