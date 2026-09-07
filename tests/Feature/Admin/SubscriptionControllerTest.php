<?php

namespace Tests\Feature\Admin;

use App\Enums\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_redirects_unauthenticated_user(): void
    {
        $this->get('/admin/subscriptions')->assertRedirect('/login');
    }

    public function test_index_returns_403_for_regular_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/subscriptions')
            ->assertStatus(403);
    }

    public function test_index_returns_200_for_super_admin(): void
    {
        $superAdmin = User::factory()->create(['email' => config('subscription.super_admin_email')]);

        $this->actingAs($superAdmin)
            ->get('/admin/subscriptions')
            ->assertStatus(200);
    }

    public function test_update_promotes_user_to_pro(): void
    {
        $superAdmin = User::factory()->create(['email' => config('subscription.super_admin_email')]);
        $user = User::factory()->create();

        $this->actingAs($superAdmin)
            ->patch("/admin/subscriptions/{$user->id}", ['plan' => 'pro'])
            ->assertRedirect();

        $this->assertTrue($user->fresh()->isPro());
        $this->assertSame(SubscriptionPlan::Pro, $user->fresh()->subscription->plan);
    }

    public function test_update_demotes_user_to_free(): void
    {
        $superAdmin = User::factory()->create(['email' => config('subscription.super_admin_email')]);
        $user = User::factory()->pro()->create();

        $this->actingAs($superAdmin)
            ->patch("/admin/subscriptions/{$user->id}", ['plan' => 'free'])
            ->assertRedirect();

        $this->assertFalse($user->fresh()->isPro());
    }

    public function test_update_returns_403_for_regular_user(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($user)
            ->patch("/admin/subscriptions/{$target->id}", ['plan' => 'pro'])
            ->assertStatus(403);

        $this->assertFalse($target->fresh()->isPro());
    }
}
