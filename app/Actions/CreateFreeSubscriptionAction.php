<?php

namespace App\Actions;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;

/**
 * Garante que um usuário tenha uma assinatura Grátis ativa. Chamada automaticamente
 * pelo UserObserver para todo usuário novo, e manualmente nos seeders (que rodam
 * com WithoutModelEvents, então o Observer não dispara ali).
 */
class CreateFreeSubscriptionAction
{
    public function execute(User $user): Subscription
    {
        return Subscription::firstOrCreate(
            ['user_id' => $user->id],
            [
                'plan' => SubscriptionPlan::Free,
                'status' => SubscriptionStatus::Active,
                'started_at' => now(),
            ]
        );
    }
}
