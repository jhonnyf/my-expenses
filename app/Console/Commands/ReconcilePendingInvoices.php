<?php

namespace App\Console\Commands;

use App\Actions\ImportInvoiceAction;
use App\Enums\InvoiceStatus;
use App\Events\InvoiceImported;
use App\Models\Invoice;
use App\Services\NFCeService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

class ReconcilePendingInvoices extends Command
{
    protected $signature = 'invoices:reconcile-pending';

    protected $description = 'Confirma notas em contingência pendentes de autorização e expira as que não foram autorizadas a tempo';

    public function handle(NFCeService $nfceService, ImportInvoiceAction $importAction): int
    {
        $expired = Invoice::pending()
            ->where('created_at', '<', now()->subDays(InvoiceStatus::PENDING_EXPIRATION_DAYS))
            ->update(['status' => InvoiceStatus::Expired->value]);

        $confirmed = 0;

        // ponytail: sequencial, uma consulta ao portal por nota pendente. Se o volume crescer, agrupar por
        // access_key (uma consulta serve todos os usuários) e/ou um job por nota com RateLimited (exige worker).
        Invoice::pending()->chunkById(100, function ($invoices) use ($nfceService, $importAction, &$confirmed) {
            foreach ($invoices as $invoice) {
                if ($this->promoteIfAuthorized($invoice, $nfceService, $importAction)) {
                    $confirmed++;
                }
            }
        });

        $this->info("{$confirmed} nota(s) confirmada(s), {$expired} expirada(s).");

        return self::SUCCESS;
    }

    /**
     * Uma falha isolada (portal fora do ar, timeout) não pode derrubar o lote: a nota segue pendente
     * e é tentada na próxima execução.
     */
    private function promoteIfAuthorized(Invoice $invoice, NFCeService $nfceService, ImportInvoiceAction $importAction): bool
    {
        try {
            $resultado = $nfceService->consultarPorQRCode($invoice->qrcode_url);
        } catch (\RuntimeException|\InvalidArgumentException|ConnectionException $e) {
            // Sem a mensagem da exceção: ela pode carregar a URL do QR (chave de acesso).
            Log::warning('Falha ao reconciliar nota pendente', ['invoice_id' => $invoice->id, 'error' => $e::class]);

            return false;
        }

        if (empty($resultado['dados']['itens'])) {
            return false;
        }

        $confirmed = $importAction->execute(
            $nfceService->normalizarDadosPortal($resultado['dados'], $invoice->access_key),
            $resultado['html'],
            $invoice->user_id,
        );

        InvoiceImported::dispatch($confirmed);

        return true;
    }
}
