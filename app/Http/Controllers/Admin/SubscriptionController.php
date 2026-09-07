<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSubscriptionRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->trim()->toString();

        $users = User::query()
            ->with('subscription')
            ->when($search !== '', fn ($query) => $query->where(
                fn ($inner) => $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
            ))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.subscriptions.index', [
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function update(UpdateSubscriptionRequest $request, User $user): RedirectResponse
    {
        $plan = SubscriptionPlan::from($request->validated('plan'));

        $user->subscription()->updateOrCreate([], [
            'plan' => $plan,
            'status' => SubscriptionStatus::Active,
            'started_at' => now(),
        ]);

        return back()->with('status', "Plano de {$user->name} atualizado para ".mb_strtoupper($plan->value).'.');
    }
}
