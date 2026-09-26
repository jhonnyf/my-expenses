<?php

namespace Tests\Feature\Jobs;

use App\Jobs\AiCategorizeItemsJob;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\ItemCategoryRule;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\ItemCategoryAiClassifierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiCategorizeItemsJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.gemini.api_key' => 'test-key']);
        Cache::flush();
    }

    private function fakeGemini(array $results, int $status = 200): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode(['results' => $results])]]]]],
            ], $status),
        ]);
    }

    private function makeItem(User $user, string $description): InvoiceItem
    {
        $invoice = Invoice::factory()->for($user)->for(Issuer::factory()->create())->create();

        return InvoiceItem::factory()->for($invoice)->create(['description' => $description, 'category_id' => null]);
    }

    private function runJob(User $user): void
    {
        (new AiCategorizeItemsJob($user->id))->handle(app(ItemCategoryAiClassifierService::class));
    }

    public function test_categorizes_item_and_stores_ai_rule(): void
    {
        $user = User::factory()->pro()->create();
        $category = Category::factory()->for($user)->create(['name' => 'Limpeza']);
        $item = $this->makeItem($user, 'DESINFETANTE PINHO 500ML');
        $this->fakeGemini([['index' => 0, 'category_id' => $category->id, 'confidence' => 0.95]]);

        $this->runJob($user);

        $this->assertDatabaseHas('invoices_items', [
            'id' => $item->id,
            'category_id' => $category->id,
            'categorization_source' => 'ai',
        ]);
        $this->assertDatabaseHas('item_category_rules', [
            'user_id' => $user->id,
            'description_key' => 'DESINFETANTE PINHO 500ML',
            'source' => 'ai',
        ]);
    }

    public function test_flushes_dashboard_cache_after_categorizing(): void
    {
        $user = User::factory()->pro()->create();
        $category = Category::factory()->for($user)->create(['name' => 'Limpeza']);
        $this->makeItem($user, 'DESINFETANTE PINHO 500ML');
        $this->fakeGemini([['index' => 0, 'category_id' => $category->id, 'confidence' => 0.95]]);
        $before = DashboardService::cacheVersion($user->id);

        $this->runJob($user);

        $this->assertNotSame($before, DashboardService::cacheVersion($user->id));
    }

    public function test_skips_free_users_without_calling_gemini(): void
    {
        $user = User::factory()->create();
        Category::factory()->for($user)->create();
        $this->makeItem($user, 'DESINFETANTE');
        Http::fake();

        $this->runJob($user);

        Http::assertNothingSent();
    }

    public function test_ignores_low_confidence_and_unknown_category_and_remembers_miss(): void
    {
        $user = User::factory()->pro()->create();
        $category = Category::factory()->for($user)->create();
        $lowConfidence = $this->makeItem($user, 'COISA ESTRANHA');
        $unknownCategory = $this->makeItem($user, 'OUTRA COISA');
        $this->fakeGemini([
            ['index' => 0, 'category_id' => $category->id, 'confidence' => 0.3],
            ['index' => 1, 'category_id' => 999999, 'confidence' => 0.99],
        ]);

        $this->runJob($user);
        $this->runJob($user);

        $this->assertDatabaseHas('invoices_items', ['id' => $lowConfidence->id, 'category_id' => null]);
        $this->assertDatabaseHas('invoices_items', ['id' => $unknownCategory->id, 'category_id' => null]);
        Http::assertSentCount(1);
    }

    public function test_does_not_override_manual_rule(): void
    {
        $user = User::factory()->pro()->create();
        $manual = Category::factory()->for($user)->create();
        $other = Category::factory()->for($user)->create();
        $item = $this->makeItem($user, 'SABONETE DOVE');
        ItemCategoryRule::create([
            'user_id' => $user->id,
            'description_key' => 'SABONETE DOVE',
            'category_id' => $manual->id,
            'source' => ItemCategoryRule::SOURCE_MANUAL,
        ]);
        $this->fakeGemini([['index' => 0, 'category_id' => $other->id, 'confidence' => 0.99]]);

        $this->runJob($user);

        $this->assertDatabaseHas('invoices_items', [
            'id' => $item->id,
            'category_id' => $manual->id,
            'categorization_source' => 'learned',
        ]);
    }

    public function test_gemini_http_failure_leaves_items_untouched(): void
    {
        $user = User::factory()->pro()->create();
        Category::factory()->for($user)->create();
        $item = $this->makeItem($user, 'DESINFETANTE');
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 500)]);

        $this->runJob($user);

        $this->assertDatabaseHas('invoices_items', ['id' => $item->id, 'category_id' => null]);
        $this->assertDatabaseEmpty('item_category_rules');
    }
}
