<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_redirects_unauthenticated_user(): void
    {
        $this->get('/reports')->assertRedirect('/login');
    }

    public function test_index_returns_200_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/reports')
            ->assertStatus(200);
    }

    public function test_generate_returns_200_for_last_month_filter(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()->startOfMonth()->subMonth()->addDays(5)]);
        InvoiceItem::factory()->for($invoice)->create();

        $this->actingAs($user)
            ->post('/reports/generate', [
                'start_date' => now()->startOfMonth()->subMonth()->format('Y-m-d'),
                'end_date' => now()->startOfMonth()->subDay()->format('Y-m-d'),
            ])
            ->assertStatus(200);
    }

    // Regressão: report/pdf.blade.php lia $filters['start_date'], mas o service
    // devolvia a chave em camelCase (startDate) — Carbon::parse(undefined) virava
    // ErrorException (500) sob o handler de erro real do Laravel, só nesse fluxo
    // (um teste batendo só no Service não pega isso, porque o array em si não
    // "quebra", só tem a chave errada).
    public function test_export_pdf_returns_200_for_last_month_filter(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $category = Category::factory()->for($user)->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()->startOfMonth()->subMonth()->addDays(5)]);
        InvoiceItem::factory()->for($invoice)->create(['category_id' => $category->id]);

        $response = $this->actingAs($user)
            ->post('/reports/pdf', [
                'start_date' => now()->startOfMonth()->subMonth()->format('Y-m-d'),
                'end_date' => now()->startOfMonth()->subDay()->format('Y-m-d'),
            ]);

        $response->assertStatus(200);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'relatorio_'.now()->format('Y-m-d').'.pdf',
            $response->headers->get('Content-Disposition')
        );
    }

    public function test_export_csv_returns_200_for_last_month_filter(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()->startOfMonth()->subMonth()->addDays(5)]);
        InvoiceItem::factory()->for($invoice)->create();

        $response = $this->actingAs($user)
            ->post('/reports/csv', [
                'start_date' => now()->startOfMonth()->subMonth()->format('Y-m-d'),
                'end_date' => now()->startOfMonth()->subDay()->format('Y-m-d'),
            ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'relatorio_'.now()->format('Y-m-d').'.csv',
            $response->headers->get('Content-Disposition')
        );
    }

    public function test_generate_preserves_selected_issuer_and_category_in_view(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $category = Category::factory()->for($user)->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()]);
        InvoiceItem::factory()->for($invoice)->create(['category_id' => $category->id]);

        $this->actingAs($user)
            ->post('/reports/generate', [
                'start_date' => now()->startOfMonth()->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
                'issuer_id' => $issuer->id,
                'category_id' => $category->id,
            ])
            ->assertStatus(200)
            ->assertViewHas('filters', fn ($filters) => (int) $filters['issuer_id'] === $issuer->id
                && (int) $filters['category_id'] === $category->id);
    }
}
