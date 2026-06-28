<?php

namespace Tests\Feature\Api\V1;

use App\Models\Issuer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssuerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/issuers')->assertStatus(401);
    }

    public function test_index_returns_paginated_issuers(): void
    {
        $user = User::factory()->create();
        Issuer::factory()->count(3)->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/issuers')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(3, 'data');
    }

    public function test_show_returns_404_for_nonexistent_issuer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/issuers/999999')
            ->assertStatus(404);
    }

    public function test_show_returns_issuer_with_stats(): void
    {
        $user   = User::factory()->create();
        $issuer = Issuer::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/issuers/{$issuer->id}")
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'issuer' => ['id', 'cnpj', 'name'],
                    'stats',
                ],
            ]);
    }

    public function test_toggle_favorite_returns_401_when_unauthenticated(): void
    {
        $issuer = Issuer::factory()->create();

        $this->postJson("/api/v1/issuers/{$issuer->id}/favorite")->assertStatus(401);
    }

    public function test_toggle_favorite_marks_issuer_as_favorite(): void
    {
        $user   = User::factory()->create();
        $issuer = Issuer::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/issuers/{$issuer->id}/favorite")
            ->assertStatus(200)
            ->assertJsonPath('data.is_favorite', true);
    }

    public function test_toggle_favorite_unmarks_already_favorited_issuer(): void
    {
        $user   = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $user->favoriteIssuers()->attach($issuer->id);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/issuers/{$issuer->id}/favorite")
            ->assertStatus(200)
            ->assertJsonPath('data.is_favorite', false);
    }
}
