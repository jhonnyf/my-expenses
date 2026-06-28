<?php

namespace Tests\Feature\Api\V1;

use App\Models\Invoice;
use App\Models\Issuer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/search?q=test')->assertStatus(401);
    }

    public function test_search_returns_empty_for_query_shorter_than_2_chars(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/search?q=a')
            ->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_search_returns_results_structure(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/search?q=supermercado')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'emissores',
                    'notas_fiscais',
                    'produtos',
                ],
            ]);
    }

    public function test_search_finds_issuer_by_name(): void
    {
        $user   = User::factory()->create();
        $issuer = Issuer::factory()->create(['name' => 'SUPERMERCADO TUDO BOM']);
        Invoice::factory()->for($user)->for($issuer)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/search?q=SUPERMERCADO')
            ->assertStatus(200);

        $emissores = $response->json('data.emissores');
        $this->assertNotEmpty($emissores);
    }
}
