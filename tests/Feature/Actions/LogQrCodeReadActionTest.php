<?php

namespace Tests\Feature\Actions;

use App\Actions\LogQrCodeReadAction;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogQrCodeReadActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_logs_successful_read_with_invoice(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->for($user)->create();
        $action = app(LogQrCodeReadAction::class);

        $read = $action->execute($user->id, 'https://www.nfce.fazenda.sp.gov.br/qrcode', success: true, invoiceId: $invoice->id);

        $this->assertDatabaseHas('qrcode_reads', [
            'id' => $read->id,
            'user_id' => $user->id,
            'qrcode_url' => 'https://www.nfce.fazenda.sp.gov.br/qrcode',
            'status' => 'success',
            'error_message' => null,
            'invoice_id' => $invoice->id,
        ]);
        $this->assertTrue($read->invoice->is($invoice));
        $this->assertTrue($invoice->qrCodeReads->contains($read));
    }

    public function test_execute_logs_failed_read_with_error_message(): void
    {
        $user = User::factory()->create();
        $action = app(LogQrCodeReadAction::class);

        $read = $action->execute($user->id, 'https://www.nfce.fazenda.sp.gov.br/invalido', success: false, errorMessage: 'Não foi possível extrair a chave de acesso da URL.');

        $this->assertDatabaseHas('qrcode_reads', [
            'id' => $read->id,
            'user_id' => $user->id,
            'status' => 'error',
            'error_message' => 'Não foi possível extrair a chave de acesso da URL.',
            'invoice_id' => null,
        ]);
    }
}
