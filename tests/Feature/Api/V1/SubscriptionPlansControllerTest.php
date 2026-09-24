<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPlansControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/subscription/plans')->assertStatus(401);
    }

    public function test_free_user_gets_features_and_is_not_pro(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/subscription/plans')
            ->assertOk()
            ->assertJsonPath('data.is_pro', false)
            ->assertJsonCount(count(config('plans.features')), 'data.features')
            ->assertJsonPath('data.features.0', ['key' => 'general_budget', 'label' => 'Orçamento geral (sem categoria)', 'free' => true]);
    }

    public function test_pro_user_gets_the_subscription(): void
    {
        $this->actingAs(User::factory()->pro()->create(), 'sanctum')
            ->getJson('/api/v1/subscription/plans')
            ->assertOk()
            ->assertJsonPath('data.is_pro', true)
            ->assertJsonPath('data.subscription.plan', 'pro');
    }

    public function test_pro_route_402_includes_the_feature(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/recurring-purchases')
            ->assertStatus(402)
            ->assertJsonPath('upgrade_required', true)
            ->assertJsonPath('feature', 'recurring_purchases');
    }
}
