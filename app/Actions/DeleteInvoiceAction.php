<?php

namespace App\Actions;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class DeleteInvoiceAction
{
    /**
     * Itens e pagamentos somem por cascade no schema. O log de leitura de QR Code guarda a URL
     * (com a chave de acesso) e só perderia o vínculo (nullOnDelete), então é apagado junto.
     * O emissor é compartilhado entre usuários e permanece.
     */
    public function execute(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $invoice->qrCodeReads()->delete();
            $invoice->delete();
        });
    }
}
