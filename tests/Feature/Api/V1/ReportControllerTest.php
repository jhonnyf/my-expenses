<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\SendReportByEmailJob;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\User;
use App\Notifications\ReportByEmail;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
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
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $recent = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()->subDays(5), 'total_amount' => 50.00]);
        $old = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()->subDays(40), 'total_amount' => 100.00]);

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

    public function test_export_csv_returns_402_for_free_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/reports/csv')
            ->assertStatus(402)
            ->assertJson(['upgrade_required' => true]);
    }

    public function test_email_report_returns_402_for_free_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/reports/email', ['format' => 'csv'])
            ->assertStatus(402)
            ->assertJson(['upgrade_required' => true]);
    }

    public function test_email_report_is_scheduled_for_pro_user(): void
    {
        Queue::fake();
        $user = User::factory()->pro()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/reports/email', ['format' => 'csv'])
            ->assertStatus(200)
            ->assertJsonPath('data.scheduled', true);

        Queue::assertPushed(SendReportByEmailJob::class);
    }

    public function test_email_report_rejects_invalid_format(): void
    {
        $this->actingAs(User::factory()->pro()->create(), 'sanctum')
            ->postJson('/api/v1/reports/email', ['format' => 'xls'])
            ->assertStatus(422);
    }

    /**
     * @dataProvider reportFormats
     */
    public function test_job_sends_report_attachment(string $format, string $mime): void
    {
        Notification::fake();
        $user = User::factory()->pro()->create();

        (new SendReportByEmailJob($user->id, $format, []))->handle(app(ReportService::class));

        Notification::assertSentTo($user, ReportByEmail::class, function ($notification) use ($user, $format, $mime) {
            $mail = $notification->toMail($user);

            return $mail->rawAttachments[0]['name'] === 'relatorio_'.now()->format('Y-m-d').'.'.$format
                && $mail->rawAttachments[0]['options']['mime'] === $mime;
        });
    }

    public static function reportFormats(): array
    {
        return [['csv', 'text/csv'], ['pdf', 'application/pdf']];
    }

    public function test_export_csv_streams_csv_content(): void
    {
        $user = User::factory()->pro()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->get('/api/v1/reports/csv');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'relatorio_'.now()->format('Y-m-d').'.csv',
            $response->headers->get('Content-Disposition')
        );
    }
}
