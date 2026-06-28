<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
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

    public function test_show_returns_category(): void
    {
        $user     = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/categories/{$category->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $category->id);
    }

    public function test_show_returns_403_for_other_users_category(): void
    {
        $category = Category::factory()->create();
        $other    = User::factory()->create();

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/v1/categories/{$category->id}")
            ->assertStatus(403);
    }

    public function test_assign_item_to_category(): void
    {
        $user     = User::factory()->create();
        $issuer   = Issuer::factory()->create();
        $invoice  = Invoice::factory()->for($user)->for($issuer)->create();
        $item     = InvoiceItem::factory()->for($invoice)->create();
        $category = Category::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/categories/assign-item', [
                'item_id'     => $item->id,
                'category_id' => $category->id,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('invoices_items', ['id' => $item->id, 'category_id' => $category->id]);
    }

    public function test_assign_item_returns_403_when_item_belongs_to_another_user(): void
    {
        $owner   = User::factory()->create();
        $other   = User::factory()->create();
        $issuer  = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($owner)->for($issuer)->create();
        $item    = InvoiceItem::factory()->for($invoice)->create();
        $cat     = Category::factory()->for($other)->create();

        $this->actingAs($other, 'sanctum')
            ->postJson('/api/v1/categories/assign-item', [
                'item_id'     => $item->id,
                'category_id' => $cat->id,
            ])
            ->assertStatus(403);
    }

    public function test_auto_categorize_returns_categorized_count(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/categories/auto-categorize')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['categorized']]);
    }
}
