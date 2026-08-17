<?php

namespace App\Http\Controllers;

use App\Actions\CreateDefaultCategoriesAction;
use App\Actions\UpdateUserLocationAction;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard.index');
        }

        return view('register.index');
    }

    public function store(
        RegisterRequest $request,
        UpdateUserLocationAction $locationAction,
        CreateDefaultCategoriesAction $categoriesAction
    ): RedirectResponse {
        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => $request->input('password'),
        ]);

        $locationAction->execute($user, $request->input('cidade'), $request->input('estado'));
        $categoriesAction->execute($user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard.index');
    }
}
