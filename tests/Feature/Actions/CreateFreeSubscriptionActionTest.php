<?php

namespace Tests\Feature\Actions;

use App\Actions\CreateFreeSubscriptionAction;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateFreeSubscriptionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_observer_creates_free_subscription_automatically(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->subscription);
        $this->assertSame(SubscriptionPlan::Free, $user->subscription->plan);
        $this->assertSame(SubscriptionStatus::Active, $user->subscription->status);
        $this->assertFalse($user->isPro());
    }

    public function test_execute_does_not_duplicate_existing_subscription(): void
    {
        $user = User::factory()->create();
        $existingId = $user->subscription->id;

        app(CreateFreeSubscriptionAction::class)->execute($user);

        $this->assertSame(1, $user->fresh()->subscription()->count());
        $this->assertSame($existingId, $user->fresh()->subscription->id);
    }
}
