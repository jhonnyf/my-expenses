<?php

namespace Tests\Feature;

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

    public function test_store_redirects_unauthenticated_user(): void
    {
        $this->post('/product-aliases', ['description' => 'ARROZ 5KG', 'canonical_name' => 'Arroz'])
            ->assertRedirect('/login');
    }

    public function test_store_sets_canonical_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/product-aliases', ['description' => 'ARROZ 5KG', 'canonical_name' => 'Arroz Branco 5kg'])
            ->assertStatus(200)
            ->assertJson([
                'description' => 'ARROZ 5KG',
                'canonical_name' => 'Arroz Branco 5kg',
                'display_name' => 'Arroz Branco 5kg',
            ]);

        $this->assertDatabaseHas('product_aliases', [
            'user_id' => $user->id,
            'description' => 'ARROZ 5KG',
            'canonical_name' => 'Arroz Branco 5kg',
        ]);
    }

    public function test_store_with_empty_name_clears_alias(): void
    {
        $user = User::factory()->create();
        ProductAlias::create(['user_id' => $user->id, 'description' => 'ARROZ 5KG', 'canonical_name' => 'Arroz']);

        $this->actingAs($user)
            ->postJson('/product-aliases', ['description' => 'ARROZ 5KG', 'canonical_name' => ''])
            ->assertStatus(200)
            ->assertJson(['canonical_name' => null, 'display_name' => 'ARROZ 5KG']);

        $this->assertDatabaseMissing('product_aliases', ['user_id' => $user->id, 'description' => 'ARROZ 5KG']);
    }

    public function test_store_validates_required_description(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/product-aliases', ['canonical_name' => 'Arroz'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('description');
    }

    public function test_merge_sets_same_canonical_name_for_all_descriptions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/product-aliases/merge', [
                'canonical_name' => 'Coca-Cola 350ml',
                'descriptions' => ['REFRIG COCA COLA 350ML LAT', 'COCA-COLA LATA 350ML'],
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('product_aliases', ['user_id' => $user->id, 'description' => 'REFRIG COCA COLA 350ML LAT', 'canonical_name' => 'Coca-Cola 350ml']);
        $this->assertDatabaseHas('product_aliases', ['user_id' => $user->id, 'description' => 'COCA-COLA LATA 350ML', 'canonical_name' => 'Coca-Cola 350ml']);
    }

    public function test_dismiss_creates_dismissal_record(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/product-aliases/dismiss', ['description_a' => 'A', 'description_b' => 'B'])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('product_alias_suggestion_dismissals', [
            'user_id' => $user->id,
            'description_a' => 'A',
            'description_b' => 'B',
        ]);
    }

    public function test_dismiss_rejects_identical_descriptions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/product-aliases/dismiss', ['description_a' => 'A', 'description_b' => 'A'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('description_b');
    }

    public function test_suggestions_returns_similar_descriptions(): void
    {
        $user = User::factory()->create();
        $this->createItem($user->id, 'REFRIG COCA COLA 350ML LAT');
        $this->createItem($user->id, 'COCA-COLA LATA 350ML');

        $response = $this->actingAs($user)->getJson('/product-aliases/suggestions');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
    }

    public function test_suggestions_isolates_data_by_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->createItem($userB->id, 'REFRIG COCA COLA 350ML LAT');
        $this->createItem($userB->id, 'COCA-COLA LATA 350ML');

        $response = $this->actingAs($userA)->getJson('/product-aliases/suggestions');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json());
    }

    public function test_review_redirects_unauthenticated_user(): void
    {
        $this->get('/product-aliases/review')->assertRedirect('/login');
    }

    public function test_review_returns_200_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/product-aliases/review')
            ->assertStatus(200);
    }

    public function test_suggestions_all_returns_similar_descriptions(): void
    {
        $user = User::factory()->create();
        $this->createItem($user->id, 'REFRIG COCA COLA 350ML LAT');
        $this->createItem($user->id, 'COCA-COLA LATA 350ML');

        $response = $this->actingAs($user)->getJson('/product-aliases/suggestions-all');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
    }

    public function test_ai_suggest_name_redirects_unauthenticated_user(): void
    {
        $this->post('/product-aliases/ai-suggest-name', ['descriptions' => ['ARROZ 5KG']])
            ->assertRedirect('/login');
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

        $response = $this->actingAs($user)->postJson('/product-aliases/ai-suggest-name', ['descriptions' => ['ARROZ 5KG']]);

        $response->assertStatus(200)->assertJson([
            'confident' => true,
            'suggested_name' => 'Arroz Branco 5kg',
            'source' => 'ai',
        ]);
    }

    public function test_ai_suggest_name_uses_community_suggestion_without_calling_ai(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        ProductAlias::create(['user_id' => $userB->id, 'description' => 'ARROZ 5KG', 'canonical_name' => 'Arroz Branco 5kg']);
        Http::fake();

        $response = $this->actingAs($userA)->postJson('/product-aliases/ai-suggest-name', ['descriptions' => ['ARROZ 5KG']]);

        $response->assertStatus(200)->assertJson([
            'confident' => true,
            'suggested_name' => 'Arroz Branco 5kg',
            'source' => 'community',
        ]);
        Http::assertNothingSent();
    }

    public function test_ai_suggest_name_returns_not_confident_when_gemini_unavailable(): void
    {
        $user = User::factory()->create();
        config(['ai.gemini.api_key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 500)]);

        $response = $this->actingAs($user)->postJson('/product-aliases/ai-suggest-name', ['descriptions' => ['ARROZ 5KG']]);

        $response->assertStatus(200)->assertJson(['confident' => false, 'suggested_name' => null]);
    }

    public function test_ai_suggest_name_validates_descriptions_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/product-aliases/ai-suggest-name', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('descriptions');
    }

    public function test_ai_suggest_name_validates_max_five_descriptions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/product-aliases/ai-suggest-name', ['descriptions' => ['A', 'B', 'C', 'D', 'E', 'F']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('descriptions');
    }

    public function test_community_suggestions_redirects_unauthenticated_user(): void
    {
        $this->get('/product-aliases/community-suggestions')->assertRedirect('/login');
    }

    public function test_community_suggestions_returns_matches(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->createItem($userA->id, 'ARROZ 5KG');
        ProductAlias::create(['user_id' => $userB->id, 'description' => 'ARROZ 5KG', 'canonical_name' => 'Arroz Branco 5kg']);

        $response = $this->actingAs($userA)->getJson('/product-aliases/community-suggestions');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
    }
}
