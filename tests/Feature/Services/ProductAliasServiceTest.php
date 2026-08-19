<?php

namespace Tests\Feature\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\ProductAlias;
use App\Models\User;
use App\Services\ProductAliasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAliasServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductAliasService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProductAliasService::class);
    }

    public function test_set_alias_creates_a_new_alias(): void
    {
        $user = User::factory()->create();

        $alias = $this->service->setAlias($user->id, 'REFRIG COCA COLA 350ML LAT', 'Coca-Cola 350ml');

        $this->assertEquals('Coca-Cola 350ml', $alias->canonical_name);
        $this->assertDatabaseHas('product_aliases', [
            'user_id' => $user->id,
            'description' => 'REFRIG COCA COLA 350ML LAT',
            'canonical_name' => 'Coca-Cola 350ml',
        ]);
    }

    public function test_set_alias_updates_existing_alias(): void
    {
        $user = User::factory()->create();
        ProductAlias::create(['user_id' => $user->id, 'description' => 'ARROZ 5KG', 'canonical_name' => 'Arroz']);

        $this->service->setAlias($user->id, 'ARROZ 5KG', 'Arroz Branco 5kg');

        $this->assertDatabaseCount('product_aliases', 1);
        $this->assertDatabaseHas('product_aliases', [
            'user_id' => $user->id,
            'description' => 'ARROZ 5KG',
            'canonical_name' => 'Arroz Branco 5kg',
        ]);
    }

    public function test_set_alias_with_empty_name_deletes_alias(): void
    {
        $user = User::factory()->create();
        ProductAlias::create(['user_id' => $user->id, 'description' => 'ARROZ 5KG', 'canonical_name' => 'Arroz']);

        $result = $this->service->setAlias($user->id, 'ARROZ 5KG', '');

        $this->assertNull($result);
        $this->assertDatabaseMissing('product_aliases', ['user_id' => $user->id, 'description' => 'ARROZ 5KG']);
    }

    public function test_merge_into_sets_same_canonical_name_for_all_descriptions(): void
    {
        $user = User::factory()->create();

        $this->service->mergeInto($user->id, 'Coca-Cola 350ml', ['REFRIG COCA COLA 350ML LAT', 'COCA-COLA LATA 350ML']);

        $this->assertDatabaseHas('product_aliases', ['user_id' => $user->id, 'description' => 'REFRIG COCA COLA 350ML LAT', 'canonical_name' => 'Coca-Cola 350ml']);
        $this->assertDatabaseHas('product_aliases', ['user_id' => $user->id, 'description' => 'COCA-COLA LATA 350ML', 'canonical_name' => 'Coca-Cola 350ml']);
    }

    public function test_alias_is_scoped_per_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->service->setAlias($userA->id, 'ARROZ 5KG', 'Arroz');

        $this->assertDatabaseMissing('product_aliases', ['user_id' => $userB->id, 'description' => 'ARROZ 5KG']);
    }

    public function test_attach_canonical_names_sets_dynamic_attribute_on_items(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create();
        $item = InvoiceItem::factory()->for($invoice)->create(['description' => 'ARROZ 5KG']);
        ProductAlias::create(['user_id' => $user->id, 'description' => 'ARROZ 5KG', 'canonical_name' => 'Arroz Branco 5kg']);

        $items = collect([$item]);
        $this->service->attachCanonicalNames($items, $user->id);

        $this->assertEquals('Arroz Branco 5kg', $items->first()->canonical_name);
    }

    public function test_attach_canonical_names_leaves_unaliased_items_null(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create();
        $item = InvoiceItem::factory()->for($invoice)->create(['description' => 'FEIJAO 1KG']);

        $items = collect([$item]);
        $this->service->attachCanonicalNames($items, $user->id);

        $this->assertNull($items->first()->canonical_name);
    }

    public function test_find_shared_canonical_name_returns_consensus_from_other_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();

        ProductAlias::create(['user_id' => $userB->id, 'description' => 'ARROZ 5KG', 'canonical_name' => 'Arroz Branco 5kg']);
        ProductAlias::create(['user_id' => $userC->id, 'description' => 'ARROZ 5KG', 'canonical_name' => 'Arroz Branco 5kg']);

        $result = $this->service->findSharedCanonicalName(['ARROZ 5KG'], $userA->id);

        $this->assertEquals('Arroz Branco 5kg', $result);
    }

    public function test_find_shared_canonical_name_returns_null_when_other_users_disagree(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();

        ProductAlias::create(['user_id' => $userB->id, 'description' => 'ARROZ 5KG', 'canonical_name' => 'Arroz Branco 5kg']);
        ProductAlias::create(['user_id' => $userC->id, 'description' => 'ARROZ 5KG', 'canonical_name' => 'Arroz Tio João 5kg']);

        $result = $this->service->findSharedCanonicalName(['ARROZ 5KG'], $userA->id);

        $this->assertNull($result);
    }

    public function test_find_shared_canonical_name_returns_null_when_not_all_descriptions_are_covered(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        ProductAlias::create(['user_id' => $userB->id, 'description' => 'ARROZ 5KG', 'canonical_name' => 'Arroz Branco 5kg']);

        $result = $this->service->findSharedCanonicalName(['ARROZ 5KG', 'FEIJAO 1KG'], $userA->id);

        $this->assertNull($result);
    }

    public function test_find_shared_canonical_name_ignores_own_alias(): void
    {
        $userA = User::factory()->create();

        ProductAlias::create(['user_id' => $userA->id, 'description' => 'ARROZ 5KG', 'canonical_name' => 'Arroz Branco 5kg']);

        $result = $this->service->findSharedCanonicalName(['ARROZ 5KG'], $userA->id);

        $this->assertNull($result);
    }

    public function test_community_suggestions_returns_unaliased_descriptions_with_consensus(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $issuer = Issuer::factory()->create();

        $invoiceA = Invoice::factory()->for($userA)->for($issuer)->create();
        InvoiceItem::factory()->for($invoiceA)->create(['description' => 'ARROZ 5KG']);

        ProductAlias::create(['user_id' => $userB->id, 'description' => 'ARROZ 5KG', 'canonical_name' => 'Arroz Branco 5kg']);

        $result = $this->service->communitySuggestions($userA->id);

        $this->assertCount(1, $result);
        $this->assertEquals('ARROZ 5KG', $result[0]['description']);
        $this->assertEquals('Arroz Branco 5kg', $result[0]['canonical_name']);
    }

    public function test_community_suggestions_returns_empty_when_feature_disabled(): void
    {
        config(['product-alias.suggestions_enabled' => false]);

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $issuer = Issuer::factory()->create();

        $invoiceA = Invoice::factory()->for($userA)->for($issuer)->create();
        InvoiceItem::factory()->for($invoiceA)->create(['description' => 'ARROZ 5KG']);

        ProductAlias::create(['user_id' => $userB->id, 'description' => 'ARROZ 5KG', 'canonical_name' => 'Arroz Branco 5kg']);

        $result = $this->service->communitySuggestions($userA->id);

        $this->assertCount(0, $result);
    }

    public function test_community_suggestions_excludes_descriptions_already_aliased_by_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $issuer = Issuer::factory()->create();

        $invoiceA = Invoice::factory()->for($userA)->for($issuer)->create();
        InvoiceItem::factory()->for($invoiceA)->create(['description' => 'ARROZ 5KG']);

        ProductAlias::create(['user_id' => $userA->id, 'description' => 'ARROZ 5KG', 'canonical_name' => 'Meu Nome']);
        ProductAlias::create(['user_id' => $userB->id, 'description' => 'ARROZ 5KG', 'canonical_name' => 'Arroz Branco 5kg']);

        $result = $this->service->communitySuggestions($userA->id);

        $this->assertCount(0, $result);
    }

    public function test_community_suggestions_isolates_by_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $issuer = Issuer::factory()->create();

        $invoiceB = Invoice::factory()->for($userB)->for($issuer)->create();
        InvoiceItem::factory()->for($invoiceB)->create(['description' => 'ARROZ 5KG']);

        $result = $this->service->communitySuggestions($userA->id);

        $this->assertCount(0, $result);
    }
}
