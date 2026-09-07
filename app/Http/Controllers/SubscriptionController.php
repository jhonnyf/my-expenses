<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function upgrade(): View
    {
        return view('subscription.upgrade');
    }
}
