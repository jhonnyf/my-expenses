<?php

namespace Tests\Feature\Api\V1;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/reports')->assertStatus(401);
    }

    public function test_generate_returns_report_structure(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/reports')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'items',
                    'summary',
                    'categoryBreakdown',
                    'filters',
                    'issuers',
                    'categories',
                ],
            ]);
    }

    public function test_generate_accepts_date_filters(): void
    {
        $user    = User::factory()->create();
        $issuer  = Issuer::factory()->create();
        $recent  = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()->subDays(5)]);
        $old     = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()->subDays(40)]);

        InvoiceItem::factory()->for($recent)->create(['total_price' => 50.00]);
        InvoiceItem::factory()->for($old)->create(['total_price' => 100.00]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/reports?start_date='.now()->subDays(10)->format('Y-m-d').'&end_date='.now()->format('Y-m-d'))
            ->assertStatus(200);

        $this->assertEquals(50.00, (float) $response->json('data.summary.total_amount'));
    }

    public function test_export_csv_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/reports/csv')->assertStatus(401);
    }

    public function test_export_csv_streams_csv_content(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->get('/api/v1/reports/csv');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }
}
