<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureTermsAcceptedTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_redirects_to_accept_terms_page_when_pending(): void
    {
        $user = User::factory()->withPendingTermsAcceptance()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('terms.accept.form'));
    }

    public function test_web_allows_access_when_terms_accepted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertStatus(200);
    }

    public function test_api_returns_403_with_flag_when_pending(): void
    {
        $user = User::factory()->withPendingTermsAcceptance()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard')
            ->assertStatus(403)
            ->assertJson(['terms_acceptance_required' => true]);
    }

    public function test_api_allows_access_when_terms_accepted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/dashboard')->assertStatus(200);
    }
}
