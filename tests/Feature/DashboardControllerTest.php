<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Issuer;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_redirects_unauthenticated_user(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_index_returns_200_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertStatus(200);
    }

    public function test_index_passes_profile_city_state_to_view(): void
    {
        $user = User::factory()->create();
        UserProfile::factory()->for($user)->create(['cidade' => 'Curitiba', 'estado' => 'PR']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertStatus(200)
            ->assertViewHas('profileCity', 'Curitiba')
            ->assertViewHas('profileState', 'PR')
            ->assertSee('Curitiba/PR');
    }

    public function test_index_shows_no_location_message_without_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertStatus(200)
            ->assertViewHas('profileCity', null);
    }

    public function test_index_filters_by_start_and_end_date(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();

        Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => '2026-01-15', 'total_amount' => 30.00]);
        Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => '2026-02-15', 'total_amount' => 70.00]);

        $response = $this->actingAs($user)
            ->get('/dashboard?start_date=2026-01-01&end_date=2026-01-31')
            ->assertStatus(200);

        $response->assertViewHas('totalExpenses', 30.00);
        $response->assertViewHas('filters', ['start_date' => '2026-01-01', 'end_date' => '2026-01-31']);
    }
}
