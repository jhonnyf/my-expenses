<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Support\NfceXmlRedactor;
use Illuminate\Console\Command;

class RedactInvoicesRawXml extends Command
{
    protected $signature = 'invoices:redact-raw-xml {--dry-run : Apenas conta quantas notas seriam alteradas, sem gravar}';

    protected $description = 'Remove CPF/CNPJ do destinatário do XML bruto (raw_xml) das notas já importadas';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $affected = 0;

        Invoice::query()
            ->whereNotNull('raw_xml')
            ->select('id', 'raw_xml')
            ->orderBy('id')
            ->cursor()
            ->each(function (Invoice $invoice) use ($dryRun, &$affected) {
                $redacted = NfceXmlRedactor::redact($invoice->raw_xml);

                if ($redacted === $invoice->raw_xml) {
                    return;
                }

                $affected++;

                if (! $dryRun) {
                    $invoice->forceFill(['raw_xml' => $redacted])->save();
                }
            });

        $action = $dryRun ? 'seriam alteradas' : 'alteradas';
        $this->info("{$affected} nota(s) {$action}.");

        return self::SUCCESS;
    }
}
