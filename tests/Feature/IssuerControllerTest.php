<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Issuer;
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
}
