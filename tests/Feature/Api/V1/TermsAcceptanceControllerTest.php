<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermsAcceptanceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_accept_returns_401_when_unauthenticated(): void
    {
        $this->postJson('/api/v1/terms/accept')->assertStatus(401);
    }

    public function test_accept_records_current_terms_version(): void
    {
        $user = User::factory()->withPendingTermsAcceptance()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/terms/accept')
            ->assertStatus(200);

        $user->refresh();
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertSame(config('legal.current_terms_version'), $user->terms_version);
    }

    public function test_dashboard_is_accessible_after_accepting(): void
    {
        $user = User::factory()->withPendingTermsAcceptance()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/terms/accept');

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/dashboard')->assertStatus(200);
    }
}
