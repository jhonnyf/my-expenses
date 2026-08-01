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
                    'filters' => ['start_date', 'end_date'],
                    'totalExpenses',
                    'totalTaxes',
                    'totalPurchases',
                    'averageTicket',
                    'periodExpenses',
                    'previousPeriodExpenses',
                    'periodVariation',
                    'monthlyExpenses',
                    'topIssuers',
                    'topProducts',
                    'spendingByCategory',
                    'paymentDistribution',
                    'budgets',
                ],
            ]);
    }

    public function test_index_filters_by_start_and_end_date(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard?start_date=2026-01-01&end_date=2026-01-31')
            ->assertStatus(200);

        $response->assertJsonPath('data.filters.start_date', '2026-01-01');
        $response->assertJsonPath('data.filters.end_date', '2026-01-31');
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
