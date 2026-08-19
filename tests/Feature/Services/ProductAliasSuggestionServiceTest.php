<?php

namespace Tests\Feature\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\ProductAlias;
use App\Models\User;
use App\Services\ProductAliasSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAliasSuggestionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductAliasSuggestionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProductAliasSuggestionService::class);
    }

    private function createItem(int $userId, string $description): InvoiceItem
    {
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $userId, 'issuer_id' => $issuer->id]);

        return InvoiceItem::factory()->for($invoice)->create(['description' => $description]);
    }

    public function test_suggest_finds_similar_descriptions_above_threshold(): void
    {
        $user = User::factory()->create();
        $this->createItem($user->id, 'REFRIG COCA COLA 350ML LAT');
        $this->createItem($user->id, 'COCA-COLA LATA 350ML');

        $suggestions = $this->service->suggest($user->id);

        $this->assertCount(1, $suggestions);
        $this->assertEqualsCanonicalizing(
            ['REFRIG COCA COLA 350ML LAT', 'COCA-COLA LATA 350ML'],
            [$suggestions[0]['description_a'], $suggestions[0]['description_b']]
        );
    }

    public function test_suggest_and_suggest_all_return_empty_when_feature_disabled(): void
    {
        config(['product-alias.suggestions_enabled' => false]);

        $user = User::factory()->create();
        $this->createItem($user->id, 'REFRIG COCA COLA 350ML LAT');
        $this->createItem($user->id, 'COCA-COLA LATA 350ML');

        $this->assertSame([], $this->service->suggest($user->id));
        $this->assertSame([], $this->service->suggestAll($user->id));
    }

    public function test_suggest_excludes_dissimilar_descriptions(): void
    {
        $user = User::factory()->create();
        $this->createItem($user->id, 'LEITE INTEGRAL 1L');
        $this->createItem($user->id, 'SUCO DE UVA 1L');

        $suggestions = $this->service->suggest($user->id);

        $this->assertCount(0, $suggestions);
    }

    public function test_suggest_excludes_pairs_already_unified(): void
    {
        $user = User::factory()->create();
        $this->createItem($user->id, 'REFRIG COCA COLA 350ML LAT');
        $this->createItem($user->id, 'COCA-COLA LATA 350ML');

        ProductAlias::create(['user_id' => $user->id, 'description' => 'REFRIG COCA COLA 350ML LAT', 'canonical_name' => 'Coca-Cola 350ml']);
        ProductAlias::create(['user_id' => $user->id, 'description' => 'COCA-COLA LATA 350ML', 'canonical_name' => 'Coca-Cola 350ml']);

        $suggestions = $this->service->suggest($user->id);

        $this->assertCount(0, $suggestions);
    }

    public function test_suggest_excludes_dismissed_pairs(): void
    {
        $user = User::factory()->create();
        $this->createItem($user->id, 'REFRIG COCA COLA 350ML LAT');
        $this->createItem($user->id, 'COCA-COLA LATA 350ML');

        $this->service->dismiss($user->id, 'REFRIG COCA COLA 350ML LAT', 'COCA-COLA LATA 350ML');

        $suggestions = $this->service->suggest($user->id);

        $this->assertCount(0, $suggestions);
    }

    public function test_dismiss_is_order_independent(): void
    {
        $user = User::factory()->create();

        $this->service->dismiss($user->id, 'A', 'B');
        $this->service->dismiss($user->id, 'B', 'A');

        $this->assertDatabaseCount('product_alias_suggestion_dismissals', 1);
    }

    public function test_suggest_isolates_by_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->createItem($userB->id, 'REFRIG COCA COLA 350ML LAT');
        $this->createItem($userB->id, 'COCA-COLA LATA 350ML');

        $suggestions = $this->service->suggest($userA->id);

        $this->assertCount(0, $suggestions);
    }

    public function test_suggest_all_is_not_capped_at_the_default_suggestion_limit(): void
    {
        $user = User::factory()->create();

        // Cada grupo {i} só bate consigo mesmo (o token "PROD{i}" tem dígito e
        // exige match exato), então os 25 grupos geram exatamente 25 pares
        // válidos sem contaminação cruzada entre grupos.
        for ($i = 1; $i <= 25; $i++) {
            $this->createItem($user->id, "PROD{$i} REFRIG COCA");
            $this->createItem($user->id, "PROD{$i} REFRIGERANTE COCA");
        }

        $default = $this->service->suggest($user->id);
        $all = $this->service->suggestAll($user->id);

        $this->assertCount(20, $default);
        $this->assertCount(25, $all);
    }
}
