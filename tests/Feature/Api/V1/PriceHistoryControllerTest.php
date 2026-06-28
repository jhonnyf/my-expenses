<?php

namespace Tests\Feature\Api\V1;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceHistoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/price-history?q=leite')->assertStatus(401);
    }

    public function test_search_returns_empty_for_short_query(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/price-history?q=a')
            ->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_search_returns_matching_products(): void
    {
        $user    = User::factory()->create();
        $issuer  = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create();

        InvoiceItem::factory()->for($invoice)->create([
            'description' => 'LEITE INTEGRAL 1L',
            'unit_price'  => 5.50,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/price-history?q=LEITE')
            ->assertStatus(200);

        $this->assertNotEmpty($response->json('data'));
    }

    public function test_timeline_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/price-history/timeline?description=leite')->assertStatus(401);
    }

    public function test_timeline_returns_empty_for_blank_description(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/price-history/timeline')
            ->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_timeline_returns_price_history_for_description(): void
    {
        $user    = User::factory()->create();
        $issuer  = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()]);

        InvoiceItem::factory()->for($invoice)->create([
            'description' => 'ARROZ TIPO 1 5KG',
            'unit_price'  => 22.90,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/price-history/timeline?description=ARROZ TIPO 1 5KG')
            ->assertStatus(200);

        $this->assertNotEmpty($response->json('data'));
    }
}
