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

    public function test_search_products_finds_item_via_other_users_alias(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($other)->for($issuer)->create();
        InvoiceItem::factory()->for($invoice)->create(['description' => 'REFRIG GUARANA ANTARCTICA 350ML LAT']);

        // Só o outro usuário apelidou o item; quem busca não tem alias próprio
        // para essa description, e o termo buscado só bate no apelido do outro.
        ProductAlias::create([
            'user_id' => $other->id,
            'description' => 'REFRIG GUARANA ANTARCTICA 350ML LAT',
            'canonical_name' => 'Guaraná gelado',
        ]);

        $results = $this->service->searchProducts($user->id, 'gelado', new Collection);

        $this->assertCount(1, $results);
        // Nome exibido continua a description bruta — sem alias próprio do
        // buscador, o apelido de terceiro só entra no critério de busca.
        $this->assertEquals('REFRIG GUARANA ANTARCTICA 350ML LAT', $results->first()->description);
    }

    public function test_search_products_with_city_state_override_only_returns_matching_city(): void
    {
        $user = User::factory()->create();
        $curitiba = Issuer::factory()->create(['city' => 'Curitiba', 'state' => 'PR']);
        $saoPaulo = Issuer::factory()->create(['city' => 'São Paulo', 'state' => 'SP']);

        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($curitiba)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG']);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($saoPaulo)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG']);

        $results = $this->service->searchProducts($user->id, 'ARROZ', new Collection, 'São Paulo', 'SP');

        $this->assertCount(1, $results);
        $this->assertEquals($saoPaulo->id, $results->first()->issuer_id);
    }

    public function test_search_products_ignores_radius_filter_outside_mysql(): void
    {
        // A suíte roda em SQLite — DistanceCalculator::isMySql() é false, então o
        // raio de 15km é ignorado e o comportamento cai pro "sem filtro" atual,
        // mesmo passando lat/lng de usuário.
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['city' => 'Curitiba', 'state' => 'PR', 'latitude' => null, 'longitude' => null]);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'FEIJAO PRETO 1KG']);

        $results = $this->service->searchProducts($user->id, 'FEIJAO', new Collection, null, null, -25.4284, -49.2733);

        $this->assertCount(1, $results);
    }

    public function test_search_products_default_mode_includes_city_match_without_coordinates(): void
    {
        // Modo padrão (não-override) real: cidade/estado do perfil acompanhados de
        // lat/lng do perfil (mesmo que o emitente em si não tenha coordenadas).
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['city' => 'São Paulo', 'state' => 'SP', 'latitude' => null, 'longitude' => null]);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG']);

        $results = $this->service->searchProducts($user->id, 'ARROZ', new Collection, 'São Paulo', 'SP', -23.5505, -46.6333);

        $this->assertCount(1, $results);
    }

    public function test_search_products_default_mode_includes_issuer_with_no_location_data(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['city' => null, 'state' => null, 'latitude' => null, 'longitude' => null]);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG']);

        $results = $this->service->searchProducts($user->id, 'ARROZ', new Collection, 'São Paulo', 'SP', -23.5505, -46.6333);

        $this->assertCount(1, $results);
    }

    public function test_search_products_default_mode_excludes_issuer_from_different_known_city(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['city' => 'Curitiba', 'state' => 'PR', 'latitude' => null, 'longitude' => null]);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG']);

        $results = $this->service->searchProducts($user->id, 'ARROZ', new Collection, 'São Paulo', 'SP', -23.5505, -46.6333);

        $this->assertCount(0, $results);
    }

    public function test_search_products_combined_mode_degrades_to_city_and_unknown_location_on_sqlite(): void
    {
        // A suíte roda em SQLite — DistanceCalculator::isMySql() é false, então o
        // branch de raio fica inerte mesmo passando lat/lng junto com cidade/estado
        // (a chamada real que o controller faz no modo padrão). Só cidade-exata e
        // "sem dado de localização" valem aqui; o raio em si só é validado manualmente
        // contra o MySQL real (ver plano).
        $user = User::factory()->create();

        $sameCity = Issuer::factory()->create(['city' => 'São Paulo', 'state' => 'SP', 'latitude' => null, 'longitude' => null]);
        $differentCityWithCoords = Issuer::factory()->create(['city' => 'Curitiba', 'state' => 'PR', 'latitude' => -25.4284, 'longitude' => -49.2733]);
        $unknownLocation = Issuer::factory()->create(['city' => null, 'state' => null, 'latitude' => null, 'longitude' => null]);

        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($sameCity)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG']);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($differentCityWithCoords)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG']);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($unknownLocation)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG']);

        $results = $this->service->searchProducts($user->id, 'ARROZ', new Collection, 'São Paulo', 'SP', -23.5505, -46.6333);

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing(
            [$sameCity->id, $unknownLocation->id],
            $results->pluck('issuer_id')->all()
        );
    }

    public function test_available_cities_returns_distinct_city_state_pairs(): void
    {
        Issuer::factory()->create(['city' => 'Curitiba', 'state' => 'PR']);
        Issuer::factory()->create(['city' => 'Curitiba', 'state' => 'PR']);
        Issuer::factory()->create(['city' => 'São Paulo', 'state' => 'SP']);
        Issuer::factory()->create(['city' => null, 'state' => null]);

        $cities = $this->service->availableCities();

        $this->assertCount(2, $cities);
    }
}
