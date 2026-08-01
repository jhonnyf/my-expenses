<?php

namespace Tests\Feature\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\ProductAlias;
use App\Models\User;
use App\Services\ShoppingListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ShoppingListServiceTest extends TestCase
{
    use RefreshDatabase;

    private ShoppingListService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ShoppingListService::class);
    }

    public function test_search_products_includes_items_from_other_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($other)->for($issuer)->create();
        InvoiceItem::factory()->for($invoice)->create(['description' => 'MACARRAO ESPAGUETE 500G']);

        $results = $this->service->searchProducts($user->id, 'MACARRAO', new Collection);

        $this->assertCount(1, $results);
        $this->assertEquals(0, $results->first()->is_own);
    }

    public function test_search_products_marks_own_items_as_own(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create();
        InvoiceItem::factory()->for($invoice)->create(['description' => 'LEITE INTEGRAL 1L']);

        $results = $this->service->searchProducts($user->id, 'LEITE', new Collection);

        $this->assertCount(1, $results);
        $this->assertEquals(1, $results->first()->is_own);
    }

    public function test_search_products_uses_searching_users_own_alias_for_other_users_item(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($other)->for($issuer)->create();
        InvoiceItem::factory()->for($invoice)->create(['description' => 'REFRIG COCA COLA 350ML LAT']);

        ProductAlias::create([
            'user_id' => $user->id,
            'description' => 'REFRIG COCA COLA 350ML LAT',
            'canonical_name' => 'Coca-Cola 350ml',
        ]);

        $results = $this->service->searchProducts($user->id, 'Coca-Cola', new Collection);

        $this->assertEquals('Coca-Cola 350ml', $results->first()->description);
    }

    public function test_search_products_falls_back_to_raw_description_when_searching_user_has_no_alias(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($other)->for($issuer)->create();
        InvoiceItem::factory()->for($invoice)->create(['description' => 'REFRIG COCA COLA 350ML LAT']);

        // Apelido pertence a um terceiro usuário, não a quem está buscando.
        ProductAlias::create([
            'user_id' => $other->id,
            'description' => 'REFRIG COCA COLA 350ML LAT',
            'canonical_name' => 'Coca-Cola 350ml',
        ]);

        $results = $this->service->searchProducts($user->id, 'COCA COLA', new Collection);

        $this->assertEquals('REFRIG COCA COLA 350ML LAT', $results->first()->description);
    }
}
