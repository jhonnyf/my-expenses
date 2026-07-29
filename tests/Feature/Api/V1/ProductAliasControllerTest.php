<?php

namespace Tests\Feature\Api\V1;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\ProductAlias;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductAliasControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createItem(int $userId, string $description): InvoiceItem
    {
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $userId, 'issuer_id' => $issuer->id]);

        return InvoiceItem::factory()->for($invoice)->create(['description' => $description]);
    }

    public function test_store_returns_401_when_unauthenticated(): void
    {
        $this->postJson('/api/v1/product-aliases', ['description' => 'ARROZ 5KG', 'canonical_name' => 'Arroz'])
            ->assertStatus(401);
    }

    public function test_store_sets_canonical_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/product-aliases', ['description' => 'ARROZ 5KG', 'canonical_name' => 'Arroz Branco 5kg'])
            ->assertStatus(200)
            ->assertJsonPath('data.canonical_name', 'Arroz Branco 5kg')
            ->assertJsonPath('data.display_name', 'Arroz Branco 5kg');

        $this->assertDatabaseHas('product_aliases', [
            'user_id' => $user->id,
            'description' => 'ARROZ 5KG',
            'canonical_name' => 'Arroz Branco 5kg',
        ]);
    }

    public function test_merge_sets_same_canonical_name_for_all_descriptions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/product-aliases/merge', [
                'canonical_name' => 'Coca-Cola 350ml',
                'descriptions' => ['REFRIG COCA COLA 350ML LAT', 'COCA-COLA LATA 350ML'],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.success', true);

        $this->assertDatabaseHas('product_aliases', ['user_id' => $user->id, 'description' => 'REFRIG COCA COLA 350ML LAT', 'canonical_name' => 'Coca-Cola 350ml']);
        $this->assertDatabaseHas('product_aliases', ['user_id' => $user->id, 'description' => 'COCA-COLA LATA 350ML', 'canonical_name' => 'Coca-Cola 350ml']);
    }

    public function test_dismiss_creates_dismissal_record(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/product-aliases/dismiss', ['description_a' => 'A', 'description_b' => 'B'])
            ->assertStatus(200)
            ->assertJsonPath('data.success', true);

        $this->assertDatabaseHas('product_alias_suggestion_dismissals', [
            'user_id' => $user->id,
            'description_a' => 'A',
            'description_b' => 'B',
        ]);
    }

    public function test_suggestions_returns_similar_descriptions(): void
    {
        $user = User::factory()->create();
        $this->createItem($user->id, 'REFRIG COCA COLA 350ML LAT');
        $this->createItem($user->id, 'COCA-COLA LATA 350ML');

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/product-aliases/suggestions');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_suggestions_all_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/product-aliases/suggestions-all')->assertStatus(401);
    }

    public function test_suggestions_all_returns_similar_descriptions(): void
    {
        $user = User::factory()->create();
        $this->createItem($user->id, 'REFRIG COCA COLA 350ML LAT');
        $this->createItem($user->id, 'COCA-COLA LATA 350ML');

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/product-aliases/suggestions-all');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_invoice_show_includes_canonical_name_for_items(): void
    {
        $user = User::factory()->create();
        $item = $this->createItem($user->id, 'ARROZ 5KG');
        ProductAlias::create(['user_id' => $user->id, 'description' => 'ARROZ 5KG', 'canonical_name' => 'Arroz Branco 5kg']);

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/v1/invoices/{$item->invoice_id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.items.0.canonical_name', 'Arroz Branco 5kg');
    }

    public function test_ai_suggest_name_returns_401_when_unauthenticated(): void
    {
        $this->postJson('/api/v1/product-aliases/ai-suggest-name', ['descriptions' => ['ARROZ 5KG']])
            ->assertStatus(401);
    }

    public function test_ai_suggest_name_returns_confident_suggestion_via_ai(): void
    {
        $user = User::factory()->create();
        config(['ai.gemini.api_key' => 'test-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode(['confident' => true, 'suggested_name' => 'Arroz Branco 5kg'])]]]],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/product-aliases/ai-suggest-name', ['descriptions' => ['ARROZ 5KG']]);

        $response->assertStatus(200)
            ->assertJsonPath('data.confident', true)
            ->assertJsonPath('data.suggested_name', 'Arroz Branco 5kg')
            ->assertJsonPath('data.source', 'ai');
    }

    public function test_ai_suggest_name_uses_community_suggestion_without_calling_ai(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        ProductAlias::create(['user_id' => $userB->id, 'description' => 'ARROZ 5KG', 'canonical_name' => 'Arroz Branco 5kg']);
        Http::fake();

        $response = $this->actingAs($userA, 'sanctum')->postJson('/api/v1/product-aliases/ai-suggest-name', ['descriptions' => ['ARROZ 5KG']]);

        $response->assertStatus(200)
            ->assertJsonPath('data.suggested_name', 'Arroz Branco 5kg')
            ->assertJsonPath('data.source', 'community');
        Http::assertNothingSent();
    }

    public function test_ai_suggest_name_validates_descriptions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/product-aliases/ai-suggest-name', [])
            ->assertStatus(422);
    }

    public function test_community_suggestions_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/product-aliases/community-suggestions')->assertStatus(401);
    }

    public function test_community_suggestions_returns_matches(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->createItem($userA->id, 'ARROZ 5KG');
        ProductAlias::create(['user_id' => $userB->id, 'description' => 'ARROZ 5KG', 'canonical_name' => 'Arroz Branco 5kg']);

        $response = $this->actingAs($userA, 'sanctum')->getJson('/api/v1/product-aliases/community-suggestions');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }
}
