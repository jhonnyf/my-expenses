<?php

namespace Tests\Feature\Api\V1;

use App\Models\Issuer;
use App\Models\IssuerNickname;
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
        $user = User::factory()->create();
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
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/issuers/{$issuer->id}/favorite")
            ->assertStatus(200)
            ->assertJsonPath('data.is_favorite', true);
    }

    public function test_toggle_favorite_unmarks_already_favorited_issuer(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $user->favoriteIssuers()->attach($issuer->id);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/issuers/{$issuer->id}/favorite")
            ->assertStatus(200)
            ->assertJsonPath('data.is_favorite', false);
    }

    public function test_show_includes_nickname_and_display_name(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['name' => 'Nome Oficial Ltda']);
        IssuerNickname::create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'nickname' => 'Padaria']);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/issuers/{$issuer->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.issuer.nickname', 'Padaria')
            ->assertJsonPath('data.issuer.display_name', 'Padaria');
    }

    public function test_index_display_name_falls_back_to_official_name(): void
    {
        $user = User::factory()->create();
        Issuer::factory()->create(['name' => 'Nome Oficial Ltda']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/issuers')
            ->assertStatus(200)
            ->assertJsonPath('data.0.display_name', 'Nome Oficial Ltda');
    }

    public function test_update_nickname_returns_401_when_unauthenticated(): void
    {
        $issuer = Issuer::factory()->create();

        $this->putJson("/api/v1/issuers/{$issuer->id}/nickname", ['nickname' => 'Padaria'])
            ->assertStatus(401);
    }

    public function test_update_nickname_sets_and_clears_nickname(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['name' => 'Nome Oficial Ltda']);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/issuers/{$issuer->id}/nickname", ['nickname' => 'Padaria'])
            ->assertStatus(200)
            ->assertJsonPath('data.nickname', 'Padaria');

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/issuers/{$issuer->id}/nickname", ['nickname' => ''])
            ->assertStatus(200)
            ->assertJsonPath('data.nickname', null)
            ->assertJsonPath('data.display_name', 'Nome Oficial Ltda');
    }

    public function test_update_nickname_rejects_duplicate_for_same_user(): void
    {
        $user = User::factory()->create();
        $issuerA = Issuer::factory()->create();
        $issuerB = Issuer::factory()->create();
        IssuerNickname::create(['user_id' => $user->id, 'issuer_id' => $issuerA->id, 'nickname' => 'Mercado']);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/issuers/{$issuerB->id}/nickname", ['nickname' => 'Mercado'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nickname');
    }
}
