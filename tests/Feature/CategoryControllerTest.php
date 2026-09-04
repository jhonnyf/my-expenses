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

    public function test_suggest_keywords_returns_ai_suggestions(): void
    {
        $user = User::factory()->create();
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
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/categories/suggest-keywords', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }
}
