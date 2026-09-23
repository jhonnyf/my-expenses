<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_redirects_unauthenticated_user(): void
    {
        $this->get('/categories')->assertRedirect('/login');
    }

    public function test_index_returns_200_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/categories')
            ->assertStatus(200);
    }

    public function test_index_returns_spending_stats(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()]);

        $low = Category::factory()->for($user)->create(['name' => 'Baixo']);
        $high = Category::factory()->for($user)->create(['name' => 'Alto']);

        InvoiceItem::factory()->for($invoice)->create(['category_id' => $low->id, 'total_price' => 10.00]);
        InvoiceItem::factory()->for($invoice)->create(['category_id' => $high->id, 'total_price' => 90.00]);
        InvoiceItem::factory()->for($invoice)->create(['category_id' => null]);

        $this->actingAs($user)
            ->get('/categories')
            ->assertStatus(200)
            ->assertViewHas('totalSpent', 100.0)
            ->assertViewHas('uncategorizedCount', 1)
            ->assertViewHas('topCategory', fn ($topCategory) => $topCategory->id === $high->id);
    }

    public function test_index_filters_spending_by_date_range(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $category = Category::factory()->for($user)->create();

        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => '2026-01-15']);
        InvoiceItem::factory()->for($invoice)->create(['category_id' => $category->id, 'total_price' => 30.00]);

        $oldInvoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => '2025-01-15']);
        InvoiceItem::factory()->for($oldInvoice)->create(['category_id' => $category->id, 'total_price' => 70.00]);

        $this->actingAs($user)
            ->get('/categories?start_date=2026-01-01&end_date=2026-01-31')
            ->assertStatus(200)
            ->assertViewHas('totalSpent', 30.0);
    }

    public function test_suggest_keywords_redirects_unauthenticated_user(): void
    {
        $this->post('/categories/suggest-keywords', ['name' => 'Alimentação'])
            ->assertRedirect('/login');
    }

    public function test_suggest_keywords_redirects_to_upgrade_for_free_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/categories/suggest-keywords', ['name' => 'Alimentação'])
            ->assertRedirect(route('subscription.upgrade'));
    }

    public function test_suggest_keywords_returns_ai_suggestions(): void
    {
        $user = User::factory()->pro()->create();
        config(['ai.gemini.api_key' => 'test-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode(['keywords' => ['ARROZ', 'FEIJAO']])]]]],
                ],
            ], 200),
        ]);

        $this->actingAs($user)
            ->postJson('/categories/suggest-keywords', ['name' => 'Alimentação'])
            ->assertStatus(200)
            ->assertJson(['keywords' => ['ARROZ', 'FEIJAO']]);
    }

    public function test_suggest_keywords_validates_name_required(): void
    {
        $user = User::factory()->pro()->create();

        $this->actingAs($user)
            ->postJson('/categories/suggest-keywords', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    private function fakeItemSuggestion(?int $categoryId, float $confidence = 0.95): void
    {
        config(['ai.gemini.api_key' => 'test-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode([
                    'results' => [['index' => 0, 'category_id' => $categoryId, 'confidence' => $confidence]],
                ])]]]]],
            ], 200),
        ]);
    }

    private function makeItemFor(User $user): InvoiceItem
    {
        $invoice = Invoice::factory()->for($user)->for(Issuer::factory()->create())->create();

        return InvoiceItem::factory()->for($invoice)->create(['category_id' => null]);
    }

    public function test_suggest_item_category_redirects_to_upgrade_for_free_user(): void
    {
        $user = User::factory()->create();
        $item = $this->makeItemFor($user);

        $this->actingAs($user)
            ->post('/categories/suggest-item-category', ['item_id' => $item->id])
            ->assertRedirect(route('subscription.upgrade'));
    }

    public function test_suggest_item_category_returns_suggested_category(): void
    {
        $user = User::factory()->pro()->create();
        $category = Category::factory()->for($user)->create();
        $item = $this->makeItemFor($user);
        $this->fakeItemSuggestion($category->id);

        $this->actingAs($user)
            ->postJson('/categories/suggest-item-category', ['item_id' => $item->id])
            ->assertStatus(200)
            ->assertJson(['category_id' => $category->id]);
    }

    public function test_suggest_item_category_returns_403_for_item_of_another_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->pro()->create();
        $item = $this->makeItemFor($owner);

        $this->actingAs($other)
            ->postJson('/categories/suggest-item-category', ['item_id' => $item->id])
            ->assertStatus(403);
    }
}
