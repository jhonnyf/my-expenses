<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Issuer;
use App\Models\IssuerNickname;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssuerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_redirects_unauthenticated_user(): void
    {
        $this->get('/issuers')->assertRedirect('/login');
    }

    public function test_index_returns_200_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/issuers')
            ->assertStatus(200);
    }

    public function test_index_returns_purchase_stats_scoped_to_current_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $issuer = Issuer::factory()->create();

        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'total_amount' => 40.00]);
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'total_amount' => 60.00]);
        Invoice::factory()->create(['user_id' => $other->id, 'issuer_id' => $issuer->id, 'total_amount' => 999.00]);

        $this->actingAs($user)
            ->get('/issuers')
            ->assertStatus(200)
            ->assertViewHas('totalSpent', 100.0)
            ->assertViewHas('records', function ($records) use ($issuer) {
                $found = $records->getCollection()->firstWhere('id', $issuer->id);

                return $found->purchase_count === 2 && (float) $found->total_spent === 100.0;
            });
    }

    public function test_index_falls_back_to_official_name_when_no_nickname_set(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['name' => 'Nome Oficial Ltda']);

        $this->actingAs($user)
            ->get('/issuers')
            ->assertStatus(200)
            ->assertViewHas('records', function ($records) use ($issuer) {
                $found = $records->getCollection()->firstWhere('id', $issuer->id);

                return $found->nickname === null;
            });
    }

    public function test_index_shows_nickname_scoped_to_current_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $issuer = Issuer::factory()->create();

        IssuerNickname::create(['user_id' => $other->id, 'issuer_id' => $issuer->id, 'nickname' => 'Apelido do Outro']);

        $this->actingAs($user)
            ->get('/issuers')
            ->assertStatus(200)
            ->assertViewHas('records', function ($records) use ($issuer) {
                $found = $records->getCollection()->firstWhere('id', $issuer->id);

                return $found->nickname === null;
            });
    }

    public function test_update_nickname_redirects_unauthenticated_user(): void
    {
        $issuer = Issuer::factory()->create();

        $this->put("/issuers/{$issuer->id}/nickname", ['nickname' => 'Padaria'])
            ->assertRedirect('/login');
    }

    public function test_update_nickname_sets_custom_nickname(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['name' => 'Nome Oficial Ltda']);

        $this->actingAs($user)
            ->putJson("/issuers/{$issuer->id}/nickname", ['nickname' => 'Padaria da Esquina'])
            ->assertStatus(200)
            ->assertJson(['nickname' => 'Padaria da Esquina', 'display_name' => 'Padaria da Esquina']);

        $this->assertDatabaseHas('issuer_nicknames', [
            'user_id' => $user->id,
            'issuer_id' => $issuer->id,
            'nickname' => 'Padaria da Esquina',
        ]);
    }

    public function test_update_nickname_clears_nickname_and_falls_back_to_official_name(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['name' => 'Nome Oficial Ltda']);
        IssuerNickname::create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'nickname' => 'Apelido']);

        $this->actingAs($user)
            ->putJson("/issuers/{$issuer->id}/nickname", ['nickname' => ''])
            ->assertStatus(200)
            ->assertJson(['nickname' => null, 'display_name' => 'Nome Oficial Ltda']);

        $this->assertDatabaseMissing('issuer_nicknames', [
            'user_id' => $user->id,
            'issuer_id' => $issuer->id,
        ]);
    }

    public function test_update_nickname_rejects_duplicate_within_same_user(): void
    {
        $user = User::factory()->create();
        $issuerA = Issuer::factory()->create();
        $issuerB = Issuer::factory()->create();
        IssuerNickname::create(['user_id' => $user->id, 'issuer_id' => $issuerA->id, 'nickname' => 'Mercado']);

        $this->actingAs($user)
            ->putJson("/issuers/{$issuerB->id}/nickname", ['nickname' => 'Mercado'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nickname');
    }

    public function test_update_nickname_allows_same_nickname_for_different_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $issuerA = Issuer::factory()->create();
        $issuerB = Issuer::factory()->create();
        IssuerNickname::create(['user_id' => $other->id, 'issuer_id' => $issuerA->id, 'nickname' => 'Mercado']);

        $this->actingAs($user)
            ->putJson("/issuers/{$issuerB->id}/nickname", ['nickname' => 'Mercado'])
            ->assertStatus(200)
            ->assertJson(['nickname' => 'Mercado']);
    }

    public function test_update_nickname_allows_resaving_same_nickname_for_same_issuer(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        IssuerNickname::create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'nickname' => 'Mercado']);

        $this->actingAs($user)
            ->putJson("/issuers/{$issuer->id}/nickname", ['nickname' => 'Mercado'])
            ->assertStatus(200)
            ->assertJson(['nickname' => 'Mercado']);
    }
}
