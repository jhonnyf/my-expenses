<?php

namespace Tests\Feature\Security;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\IssuerNickname;
use App\Models\ProductAlias;
use App\Models\ProductAliasSuggestionDismissal;
use App\Models\RecurringDismissal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Dados que são "por usuário" mesmo quando o registro-base é compartilhado (emissor, descrição do produto):
 * apelidos, favoritos, nomes de produto, ocultações, além de dashboard, busca global e notificações.
 */
class PersonalDataIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $attacker;

    private User $victim;

    private Issuer $issuer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attacker = User::factory()->pro()->create();
        $this->victim = User::factory()->pro()->create();
        // Os dois compraram no mesmo mercado: o emissor é o mesmo registro para ambos.
        $this->issuer = Issuer::factory()->create(['name' => 'MERCADO COMPARTILHADO']);
        Invoice::factory()->for($this->attacker)->create(['issuer_id' => $this->issuer->id]);
        Invoice::factory()->for($this->victim)->create(['issuer_id' => $this->issuer->id]);
    }

    private function notificationFor(User $user, string $message = 'aviso'): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'x',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['message' => $message],
        ]);
    }

    // ─── emissores compartilhados: apelido e favorito são por usuário ───────

    public function test_nickname_is_per_user_on_a_shared_issuer(): void
    {
        IssuerNickname::create(['user_id' => $this->victim->id, 'issuer_id' => $this->issuer->id, 'nickname' => 'APELIDO DA VITIMA']);

        $this->actingAs($this->attacker)
            ->putJson(route('issuers.nickname.update', $this->issuer->id), ['nickname' => 'APELIDO DO ATACANTE'])
            ->assertOk();
        $this->actingAs($this->attacker, 'sanctum')
            ->putJson("/api/v1/issuers/{$this->issuer->id}/nickname", ['nickname' => 'APELIDO DO ATACANTE'])
            ->assertOk();

        $this->assertSame('APELIDO DA VITIMA', IssuerNickname::where(['user_id' => $this->victim->id, 'issuer_id' => $this->issuer->id])->value('nickname'));
        $this->assertSame(1, IssuerNickname::where('issuer_id', $this->issuer->id)->where('user_id', $this->attacker->id)->count());

        $this->actingAs($this->attacker)->get(route('issuers.index'))->assertOk()
            ->assertSee('APELIDO DO ATACANTE')->assertDontSee('APELIDO DA VITIMA');
        $this->actingAs($this->victim)->get(route('issuers.index'))->assertOk()
            ->assertSee('APELIDO DA VITIMA')->assertDontSee('APELIDO DO ATACANTE');
        $this->actingAs($this->attacker, 'sanctum')->getJson('/api/v1/issuers')->assertOk()->assertDontSee('APELIDO DA VITIMA');
    }

    public function test_clearing_a_nickname_never_clears_the_other_users_nickname(): void
    {
        IssuerNickname::create(['user_id' => $this->victim->id, 'issuer_id' => $this->issuer->id, 'nickname' => 'APELIDO DA VITIMA']);

        $this->actingAs($this->attacker)->putJson(route('issuers.nickname.update', $this->issuer->id), ['nickname' => null])->assertOk();

        $this->assertSame(1, IssuerNickname::where('user_id', $this->victim->id)->count());
    }

    public function test_favorite_issuer_is_per_user(): void
    {
        $this->victim->favoriteIssuers()->attach($this->issuer->id);

        $this->actingAs($this->attacker)->postJson(route('issuers.favorite', $this->issuer->id))->assertOk();
        $this->assertTrue($this->attacker->favoriteIssuers()->where('issuers.id', $this->issuer->id)->exists());
        $this->assertTrue($this->victim->favoriteIssuers()->where('issuers.id', $this->issuer->id)->exists());

        // Desfavoritar é só do atacante.
        $this->actingAs($this->attacker)->postJson(route('issuers.favorite', $this->issuer->id))->assertOk();
        $this->assertFalse($this->attacker->favoriteIssuers()->where('issuers.id', $this->issuer->id)->exists());
        $this->assertTrue($this->victim->favoriteIssuers()->where('issuers.id', $this->issuer->id)->exists());
    }

    // ─── nomes de produto (alias) ────────────────────────────────────────────

    public function test_product_alias_changes_only_affect_the_caller(): void
    {
        ProductAlias::create(['user_id' => $this->victim->id, 'description' => 'COCA COLA 350ML LT', 'canonical_name' => 'Coca-Cola 350ml']);

        $this->actingAs($this->attacker);
        $this->postJson(route('product-aliases.store'), ['description' => 'COCA COLA 350ML LT', 'canonical_name' => 'NOME DO ATACANTE'])->assertOk();
        $this->postJson(route('product-aliases.merge'), ['canonical_name' => 'OUTRO NOME', 'descriptions' => ['COCA COLA 350ML LT', 'GUARANA 350ML']])->assertOk();
        $this->postJson(route('product-aliases.dismiss'), ['description_a' => 'COCA COLA 350ML LT', 'description_b' => 'GUARANA 350ML'])->assertOk();
        $this->postJson(route('product-aliases.store'), ['description' => 'COCA COLA 350ML LT', 'canonical_name' => null])->assertOk();

        $this->assertSame(
            'Coca-Cola 350ml',
            ProductAlias::where(['user_id' => $this->victim->id, 'description' => 'COCA COLA 350ML LT'])->value('canonical_name')
        );
        $this->assertSame(0, ProductAliasSuggestionDismissal::where('user_id', $this->victim->id)->count());
        $this->assertSame(1, ProductAliasSuggestionDismissal::where('user_id', $this->attacker->id)->count());
        $this->assertSame([], ProductAlias::where('user_id', '!=', $this->victim->id)->where('user_id', '!=', $this->attacker->id)->pluck('id')->all());
    }

    public function test_alias_of_one_user_never_renames_the_products_of_another(): void
    {
        $item = InvoiceItem::factory()
            ->for(Invoice::factory()->for($this->victim)->create(['issued_at' => now(), 'issuer_id' => $this->issuer->id]))
            ->create(['description' => 'LEITE INTEGRAL 1L', 'unit_price' => 5]);
        ProductAlias::create(['user_id' => $this->attacker->id, 'description' => 'LEITE INTEGRAL 1L', 'canonical_name' => 'NOME FORJADO PELO ATACANTE']);

        $this->actingAs($this->victim, 'sanctum')->getJson('/api/v1/invoices/'.$item->invoice_id)
            ->assertOk()->assertDontSee('NOME FORJADO PELO ATACANTE');
        $this->actingAs($this->victim)->get(route('my-purchases.detail', $item->invoice_id))
            ->assertOk()->assertDontSee('NOME FORJADO PELO ATACANTE');
        $this->actingAs($this->victim)->get(route('reports.index', ['start_date' => '2000-01-01']))
            ->assertOk()->assertDontSee('NOME FORJADO PELO ATACANTE');
    }

    public function test_community_suggestions_never_identify_who_named_the_product(): void
    {
        config(['product-alias.suggestions_enabled' => true]);
        InvoiceItem::factory()
            ->for(Invoice::factory()->for($this->attacker)->create(['issued_at' => now()]))
            ->create(['description' => 'ARROZ TIPO 1 5KG']);
        ProductAlias::create(['user_id' => $this->victim->id, 'description' => 'ARROZ TIPO 1 5KG', 'canonical_name' => 'Arroz 5kg']);

        $response = $this->actingAs($this->attacker)->getJson(route('product-aliases.community-suggestions'))->assertOk();

        $this->assertSame([['description' => 'ARROZ TIPO 1 5KG', 'canonical_name' => 'Arroz 5kg']], $response->json());
        $this->assertStringNotContainsString($this->victim->email, $response->getContent());
        $this->assertStringNotContainsString((string) $this->victim->name, $response->getContent());
    }

    // ─── compras recorrentes ─────────────────────────────────────────────────

    public function test_recurring_dismissals_are_per_user(): void
    {
        RecurringDismissal::create(['user_id' => $this->victim->id, 'description' => 'LEITE']);

        $this->actingAs($this->attacker);
        $this->postJson(route('recurring-purchases.dismiss'), ['description' => 'LEITE'])->assertOk();
        $this->postJson(route('recurring-purchases.restore'), ['description' => 'LEITE'])->assertOk();
        $this->postJson('/api/v1/recurring-purchases/restore', ['description' => 'LEITE']);

        $this->assertSame(1, RecurringDismissal::where('user_id', $this->victim->id)->count());
        $this->assertSame(0, RecurringDismissal::where('user_id', $this->attacker->id)->count());
    }

    // ─── dashboard e busca global ────────────────────────────────────────────

    public function test_dashboard_and_search_never_include_another_users_data(): void
    {
        $victimIssuer = Issuer::factory()->create(['name' => 'LOJA SECRETA DA VITIMA', 'city' => 'Cidade', 'state' => 'GO']);
        $invoice = Invoice::factory()->for($this->victim)->create(['issuer_id' => $victimIssuer->id, 'issued_at' => now(), 'total_amount' => 4321.00]);
        InvoiceItem::factory()->for($invoice)->create(['description' => 'PRODUTO SIGILOSO DA VITIMA']);

        $this->actingAs($this->attacker);

        $this->get(route('dashboard.index', ['start_date' => '2000-01-01']))->assertOk()
            ->assertDontSee('LOJA SECRETA DA VITIMA')->assertDontSee('4.321,00');
        $this->getJson('/api/v1/dashboard?start_date=2000-01-01')->assertOk()
            ->assertDontSee('LOJA SECRETA DA VITIMA')->assertDontSee('4321');

        foreach (['SECRETA', 'SIGILOSO', (string) $invoice->number] as $term) {
            $body = $this->actingAs($this->attacker, 'sanctum')->getJson('/api/v1/search?q='.urlencode($term))->assertOk()->getContent();
            $this->assertStringNotContainsString('LOJA SECRETA', $body);
            $this->assertStringNotContainsString('PRODUTO SIGILOSO', $body);
            $this->assertStringNotContainsString($invoice->access_key, $body);
        }

        $this->actingAs($this->attacker)->getJson(route('search', ['q' => 'SECRETA']))->assertOk()->assertDontSee('LOJA SECRETA');
    }

    // ─── notificações ────────────────────────────────────────────────────────

    public function test_mark_all_as_read_only_affects_own_notifications(): void
    {
        $own = $this->notificationFor($this->attacker);
        $victims = $this->notificationFor($this->victim);

        $this->actingAs($this->attacker)->postJson(route('notifications.read-all'))->assertOk();

        $this->assertNotNull($own->fresh()->read_at);
        $this->assertNull($victims->fresh()->read_at);

        $victimsSecond = $this->notificationFor($this->victim);
        $this->actingAs($this->attacker, 'sanctum')->postJson('/api/v1/notifications/read-all')->assertSuccessful();
        $this->assertNull($victimsSecond->fresh()->read_at);
    }

    public function test_notification_lists_and_counters_are_own_only_on_web_and_api(): void
    {
        $this->notificationFor($this->victim, 'SEGREDO DA VITIMA');
        $this->notificationFor($this->victim, 'SEGREDO DA VITIMA 2');

        $this->actingAs($this->attacker)->getJson(route('notifications.index'))->assertOk()
            ->assertJsonPath('unread_count', 0)->assertDontSee('SEGREDO DA VITIMA');
        $this->actingAs($this->attacker)->getJson(route('notifications.unread-count'))->assertJsonPath('unread_count', 0);
        $this->actingAs($this->attacker, 'sanctum')->getJson('/api/v1/notifications')->assertOk()->assertDontSee('SEGREDO DA VITIMA');

        $id = $this->victim->notifications()->first()->id;
        $this->actingAs($this->attacker, 'sanctum')->postJson("/api/v1/notifications/{$id}/read")->assertNotFound();
        $this->assertNull(DatabaseNotification::find($id)->read_at);
    }
}
