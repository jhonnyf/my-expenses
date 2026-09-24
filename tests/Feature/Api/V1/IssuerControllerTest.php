<?php

namespace Tests\Feature\Api\V1;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\IssuerNickname;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssuerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/issuers')->assertStatus(401);
    }

    public function test_index_returns_paginated_issuers(): void
    {
        $user = User::factory()->create();
        $issuers = Issuer::factory()->count(3)->create();
        $issuers->each(fn (Issuer $issuer) => Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id]));

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/issuers')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(3, 'data');
    }

    public function test_index_includes_purchase_count_and_total_spent(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'total_amount' => 50]);
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'total_amount' => 30]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/issuers')
            ->assertStatus(200)
            ->assertJsonPath('data.0.purchase_count', 2)
            ->assertJsonPath('data.0.total_spent', 80);
    }

    public function test_index_excludes_issuers_the_user_never_bought_from(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $ownIssuer = Issuer::factory()->create();
        $otherIssuer = Issuer::factory()->create();

        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $ownIssuer->id]);
        Invoice::factory()->create(['user_id' => $other->id, 'issuer_id' => $otherIssuer->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/issuers')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownIssuer->id);
    }

    public function test_show_returns_404_for_nonexistent_issuer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/issuers/999999')
            ->assertStatus(404);
    }

    public function test_show_returns_issuer_with_stats(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/issuers/{$issuer->id}")
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'issuer' => ['id', 'cnpj', 'name'],
                    'stats',
                    'invoices',
                ],
            ]);
    }

    public function test_show_includes_recent_invoices_with_items_count(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'total_amount' => 35.63]);
        InvoiceItem::factory()->count(4)->create(['invoice_id' => $invoice->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/issuers/{$issuer->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.invoices.0.id', $invoice->id)
            ->assertJsonPath('data.invoices.0.items_count', 4)
            ->assertJsonPath('data.invoices.0.total_amount', '35.63');
    }

    public function test_toggle_favorite_returns_401_when_unauthenticated(): void
    {
        $issuer = Issuer::factory()->create();

        $this->postJson("/api/v1/issuers/{$issuer->id}/favorite")->assertStatus(401);
    }

    public function test_toggle_favorite_marks_issuer_as_favorite(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/issuers/{$issuer->id}/favorite")
            ->assertStatus(200)
            ->assertJsonPath('data.is_favorite', true);
    }

    public function test_toggle_favorite_unmarks_already_favorited_issuer(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id]);
        $user->favoriteIssuers()->attach($issuer->id);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/issuers/{$issuer->id}/favorite")
            ->assertStatus(200)
            ->assertJsonPath('data.is_favorite', false);
    }

    public function test_show_includes_nickname_and_display_name(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['name' => 'Nome Oficial Ltda']);
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id]);
        IssuerNickname::create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'nickname' => 'Padaria']);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/issuers/{$issuer->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.issuer.nickname', 'Padaria')
            ->assertJsonPath('data.issuer.display_name', 'Padaria');
    }

    public function test_index_display_name_falls_back_to_official_name(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['name' => 'Nome Oficial Ltda']);
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/issuers')
            ->assertStatus(200)
            ->assertJsonPath('data.0.display_name', 'Nome Oficial Ltda');
    }

    public function test_update_nickname_returns_401_when_unauthenticated(): void
    {
        $issuer = Issuer::factory()->create();

        $this->putJson("/api/v1/issuers/{$issuer->id}/nickname", ['nickname' => 'Padaria'])
            ->assertStatus(401);
    }

    public function test_update_nickname_sets_and_clears_nickname(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['name' => 'Nome Oficial Ltda']);
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/issuers/{$issuer->id}/nickname", ['nickname' => 'Padaria'])
            ->assertStatus(200)
            ->assertJsonPath('data.nickname', 'Padaria');

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/issuers/{$issuer->id}/nickname", ['nickname' => ''])
            ->assertStatus(200)
            ->assertJsonPath('data.nickname', null)
            ->assertJsonPath('data.display_name', 'Nome Oficial Ltda');
    }

    public function test_update_nickname_rejects_duplicate_for_same_user(): void
    {
        $user = User::factory()->create();
        $issuerA = Issuer::factory()->create();
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuerA->id]);
        $issuerB = Issuer::factory()->create();
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuerB->id]);
        IssuerNickname::create(['user_id' => $user->id, 'issuer_id' => $issuerA->id, 'nickname' => 'Mercado']);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/issuers/{$issuerB->id}/nickname", ['nickname' => 'Mercado'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nickname');
    }

    public function test_show_returns_404_for_issuer_the_user_never_bought_from(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson("/api/v1/issuers/{$issuer->id}")->assertStatus(404);
    }

    public function test_toggle_favorite_returns_404_for_issuer_the_user_never_bought_from(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson("/api/v1/issuers/{$issuer->id}/favorite")->assertStatus(404);

        $this->assertDatabaseMissing('favorite_issuers', ['issuer_id' => $issuer->id]);
    }

    public function test_update_nickname_returns_404_for_issuer_the_user_never_bought_from(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/issuers/{$issuer->id}/nickname", ['nickname' => 'Padaria'])
            ->assertStatus(404);

        $this->assertDatabaseMissing('issuer_nicknames', ['issuer_id' => $issuer->id]);
    }

    public function test_index_filters_by_search_term(): void
    {
        $user = User::factory()->create();
        $match = Issuer::factory()->create(['name' => 'Atacadao Central']);
        $miss = Issuer::factory()->create(['name' => 'Padaria Doce']);
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $match->id]);
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $miss->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/issuers?q=atacadao')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_index_sorts_and_exposes_last_purchase_and_average_ticket(): void
    {
        $user = User::factory()->create();
        $cheap = Issuer::factory()->create(['name' => 'A Barato']);
        $pricey = Issuer::factory()->create(['name' => 'B Caro']);
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $cheap->id, 'total_amount' => 10, 'issued_at' => '2026-01-05 09:00:00']);
        Invoice::factory()->count(2)->create(['user_id' => $user->id, 'issuer_id' => $pricey->id, 'total_amount' => 50, 'issued_at' => '2026-06-05 09:00:00']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/issuers?sort=spent')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $pricey->id)
            ->assertJsonPath('data.0.average_ticket', 50)
            ->assertJsonPath('data.1.id', $cheap->id);
    }

    public function test_index_filters_only_favorites(): void
    {
        $user = User::factory()->create();
        $fav = Issuer::factory()->create();
        $other = Issuer::factory()->create();
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $fav->id]);
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $other->id]);
        $user->favoriteIssuers()->attach($fav->id);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/issuers?favorites=1')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $fav->id);
    }

    public function test_index_rejects_unknown_sort(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/issuers?sort=nope')
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort');
    }

    public function test_show_returns_insights_and_invoices_meta(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        Invoice::factory()->count(17)->create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'total_amount' => 10]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/issuers/{$issuer->id}")
            ->assertStatus(200)
            ->assertJsonCount(15, 'data.invoices')
            ->assertJsonPath('data.invoices_meta.total', 17)
            ->assertJsonPath('data.invoices_meta.last_page', 2)
            ->assertJsonPath('data.insights.average_ticket', 10)
            ->assertJsonCount(12, 'data.insights.monthly')
            ->assertJsonStructure(['data' => ['insights' => ['visit_interval_days', 'top_products', 'categories']]]);
    }

    public function test_show_second_page_of_invoices(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        Invoice::factory()->count(17)->create(['user_id' => $user->id, 'issuer_id' => $issuer->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/issuers/{$issuer->id}?page=2")
            ->assertJsonCount(2, 'data.invoices')
            ->assertJsonPath('data.invoices_meta.current_page', 2);
    }

    public function test_issuer_of_a_pending_invoice_is_reachable_but_not_listed(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'status' => InvoiceStatus::Pending]);

        $this->actingAs($user, 'sanctum')->getJson("/api/v1/issuers/{$issuer->id}")->assertStatus(200);
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/issuers/{$issuer->id}/favorite")->assertStatus(200);
        $this->actingAs($user, 'sanctum')->putJson("/api/v1/issuers/{$issuer->id}/nickname", ['nickname' => 'Novo'])->assertStatus(200);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/issuers')->assertJsonCount(0, 'data');
    }
}
