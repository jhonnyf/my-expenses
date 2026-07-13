<?php

namespace Tests\Feature;

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
        $invoice = Invoice::factory()->for($user)->for($issuer)->create();

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
}
