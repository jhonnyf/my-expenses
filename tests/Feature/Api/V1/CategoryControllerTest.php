<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/categories')->assertStatus(401);
    }

    public function test_index_returns_user_categories(): void
    {
        $user = User::factory()->create();
        Category::factory()->count(3)->for($user)->create();
        Category::factory()->count(2)->create(); // de outro usuário

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/categories')
            ->assertStatus(200);
    }

    public function test_store_creates_category(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/categories', [
                'name'  => 'Alimentação',
                'color' => '#FF0000',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Alimentação');

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name'    => 'Alimentação',
        ]);
    }

    public function test_store_returns_401_when_unauthenticated(): void
    {
        $this->postJson('/api/v1/categories', ['name' => 'Teste'])
            ->assertStatus(401);
    }

    public function test_update_returns_403_when_category_belongs_to_another_user(): void
    {
        $category = Category::factory()->create();
        $other    = User::factory()->create();

        $this->actingAs($other, 'sanctum')
            ->patchJson("/api/v1/categories/{$category->id}", ['name' => 'Novo nome'])
            ->assertStatus(403);
    }

    public function test_update_modifies_own_category(): void
    {
        $user     = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/categories/{$category->id}", [
                'name'  => 'Novo nome',
                'color' => '#00FF00',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Novo nome');
    }

    public function test_destroy_returns_403_when_category_belongs_to_another_user(): void
    {
        $category = Category::factory()->create();
        $other    = User::factory()->create();

        $this->actingAs($other, 'sanctum')
            ->deleteJson("/api/v1/categories/{$category->id}")
            ->assertStatus(403);
    }

    public function test_destroy_deletes_own_category(): void
    {
        $user     = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/categories/{$category->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
