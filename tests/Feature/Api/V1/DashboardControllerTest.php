<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/dashboard')->assertStatus(401);
    }

    public function test_index_returns_dashboard_structure(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'totalExpenses',
                    'totalTaxes',
                    'totalPurchases',
                    'averageTicket',
                    'currentMonthExpenses',
                    'lastMonthExpenses',
                    'monthlyExpenses',
                    'topIssuers',
                    'topProducts',
                    'spendingByCategory',
                    'paymentDistribution',
                    'budgets',
                ],
            ]);
    }

    public function test_index_does_not_leak_raw_xml(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard')
            ->assertStatus(200);

        $this->assertArrayNotHasKey('raw_xml', $response->json('data'));
    }
}
