<?php

namespace App\Http\Controllers;

use App\Support\LoginThrottle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard.index');
        }

        return view('login.index');
    }

    public function execute(Request $request, LoginThrottle $throttle): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if ($throttle->isLocked($credentials['email'])) {
            return back()->withErrors(['email' => $throttle->message($credentials['email'])])->onlyInput('email');
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $throttle->reset($credentials['email']);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard.index'));
        }

        $throttle->recordFailure($credentials['email']);

        return back()->withErrors([
            'email' => __('auth.failed'),
        ])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login.index');
    }
}
