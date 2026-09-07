<?php

namespace App\Observers;

use App\Actions\CreateFreeSubscriptionAction;
use App\Models\User;

class UserObserver
{
    public function __construct(private readonly CreateFreeSubscriptionAction $createFreeSubscription) {}

    public function created(User $user): void
    {
        $this->createFreeSubscription->execute($user);
    }
}
