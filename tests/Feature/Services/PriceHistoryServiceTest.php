<?php

namespace Tests\Feature\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\ProductAlias;
use App\Models\User;
use App\Services\PriceHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceHistoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private PriceHistoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PriceHistoryService::class);
    }

    public function test_search_returns_empty_when_no_products_match(): void
    {
        $user = User::factory()->create();

        $result = $this->service->search('produto inexistente', $user->id);

        $this->assertCount(0, $result);
    }

    public function test_search_returns_products_with_prices(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create();
        InvoiceItem::factory()->for($invoice)->create(['description' => 'CAFE MOIDO 500G', 'unit_price' => 12.90]);

        $result = $this->service->search('CAFE', $user->id);

        $this->assertNotEmpty($result);
    }

    public function test_timeline_returns_empty_for_unknown_product(): void
    {
        $user = User::factory()->create();

        $result = $this->service->getTimeline('produto que nao existe', $user->id);

        $this->assertEmpty($result['timeline']);
        $this->assertEquals(0, $result['summary']['min_price']);
    }

    public function test_timeline_returns_price_points_for_product(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()]);
        InvoiceItem::factory()->for($invoice)->create([
            'description' => 'OLEO DE SOJA 900ML',
            'unit_price' => 7.50,
        ]);

        $result = $this->service->getTimeline('OLEO DE SOJA 900ML', $user->id);

        $this->assertNotEmpty($result);
    }

    public function test_search_isolates_data_by_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($userB)->for($issuer)->create();
        InvoiceItem::factory()->for($invoice)->create(['description' => 'SAL REFINADO 1KG']);

        $result = $this->service->search('SAL', $userA->id);

        $this->assertCount(0, $result);
    }

    public function test_search_finds_own_item_via_other_users_alias(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create();
        InvoiceItem::factory()->for($invoice)->create(['description' => 'REFRIG GUARANA ANTARCTICA 350ML LAT']);

        // Outro usuário apelidou a mesma description bruta em outra compra
        // dele; quem busca não tem alias próprio, e o termo só bate no
        // apelido de terceiro.
        ProductAlias::create([
            'user_id' => $other->id,
            'description' => 'REFRIG GUARANA ANTARCTICA 350ML LAT',
            'canonical_name' => 'Guaraná gelado',
        ]);

        $result = $this->service->search('gelado', $user->id);

        $this->assertCount(1, $result);
        // Nome exibido continua a description bruta — sem alias próprio.
        $this->assertEquals('REFRIG GUARANA ANTARCTICA 350ML LAT', $result->first()->description);
    }

    public function test_search_groups_aliased_descriptions_under_canonical_name(): void
    {
        $user = User::factory()->create();
        $issuerA = Issuer::factory()->create();
        $issuerB = Issuer::factory()->create();

        $invoiceA = Invoice::factory()->for($user)->for($issuerA)->create();
        InvoiceItem::factory()->for($invoiceA)->create(['description' => 'REFRIG COCA COLA 350ML LAT', 'unit_price' => 5.00]);

        $invoiceB = Invoice::factory()->for($user)->for($issuerB)->create();
        InvoiceItem::factory()->for($invoiceB)->create(['description' => 'COCA-COLA LATA 350ML', 'unit_price' => 5.50]);

        ProductAlias::create(['user_id' => $user->id, 'description' => 'REFRIG COCA COLA 350ML LAT', 'canonical_name' => 'Coca-Cola 350ml']);
        ProductAlias::create(['user_id' => $user->id, 'description' => 'COCA-COLA LATA 350ML', 'canonical_name' => 'Coca-Cola 350ml']);

        $result = $this->service->search('Coca-Cola', $user->id);

        $this->assertCount(1, $result);
        $this->assertEquals('Coca-Cola 350ml', $result->first()->description);
        $this->assertEquals(2, $result->first()->purchase_count);
    }

    public function test_timeline_includes_all_descriptions_aliased_to_same_canonical_name(): void
    {
        $user = User::factory()->create();
        $issuerA = Issuer::factory()->create();
        $issuerB = Issuer::factory()->create();

        $invoiceA = Invoice::factory()->for($user)->for($issuerA)->create(['issued_at' => now()]);
        InvoiceItem::factory()->for($invoiceA)->create(['description' => 'REFRIG COCA COLA 350ML LAT', 'unit_price' => 5.00]);

        $invoiceB = Invoice::factory()->for($user)->for($issuerB)->create(['issued_at' => now()]);
        InvoiceItem::factory()->for($invoiceB)->create(['description' => 'COCA-COLA LATA 350ML', 'unit_price' => 5.50]);

        ProductAlias::create(['user_id' => $user->id, 'description' => 'REFRIG COCA COLA 350ML LAT', 'canonical_name' => 'Coca-Cola 350ml']);
        ProductAlias::create(['user_id' => $user->id, 'description' => 'COCA-COLA LATA 350ML', 'canonical_name' => 'Coca-Cola 350ml']);

        $result = $this->service->getTimeline('Coca-Cola 350ml', $user->id);

        $this->assertCount(2, $result['timeline']);
    }
}
