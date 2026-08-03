<?php

namespace Tests\Feature;

use App\Models\FavoriteProduct;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_redirects_unauthenticated_user(): void
    {
        $this->post('/favorite-products', ['canonical_name' => 'Arroz'])->assertRedirect('/login');
    }

    public function test_store_creates_favorite(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/favorite-products', ['canonical_name' => 'Arroz Branco 5kg', 'unit' => 'UN'])
            ->assertStatus(201);

        $this->assertDatabaseHas('favorite_products', [
            'user_id' => $user->id,
            'canonical_name' => 'Arroz Branco 5kg',
        ]);
    }

    public function test_store_is_idempotent_for_same_product(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/favorite-products', ['canonical_name' => 'Arroz Branco 5kg']);
        $this->actingAs($user)->post('/favorite-products', ['canonical_name' => 'Arroz Branco 5kg']);

        $this->assertDatabaseCount('favorite_products', 1);
    }

    public function test_index_returns_favorites_with_current_offer(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['city' => 'Curitiba', 'state' => 'PR']);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG', 'unit_price' => 20.00]);

        FavoriteProduct::factory()->for($user)->create(['canonical_name' => 'ARROZ BRANCO 5KG']);

        $response = $this->actingAs($user)->getJson('/favorite-products');

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(20.00, $response->json('0.current_offer.price'), 0.001);
    }

    public function test_destroy_returns_403_for_other_users_favorite(): void
    {
        $favorite = FavoriteProduct::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($other)
            ->delete("/favorite-products/{$favorite->id}")
            ->assertStatus(403);
    }

    public function test_destroy_deletes_own_favorite(): void
    {
        $user = User::factory()->create();
        $favorite = FavoriteProduct::factory()->for($user)->create();

        $this->actingAs($user)
            ->delete("/favorite-products/{$favorite->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('favorite_products', ['id' => $favorite->id]);
    }
}
