<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Jobs\GeocodeIssuerJob;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Issuer;
use App\Support\NfceXmlRedactor;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ImportInvoiceAction
{
    public function execute(array $parsed, string $rawContent, int $userId): Invoice
    {
        return DB::transaction(fn () => $this->store($parsed, $rawContent, $userId));
    }

    private function store(array $parsed, string $rawContent, int $userId): Invoice
    {
        $issuer = $this->findOrCreateIssuer(Arr::get($parsed, 'emitente', []));

        // includingUnauthorized: uma nota pendente já existente precisa ser encontrada e atualizada, não duplicada.
        $invoice = Invoice::includingUnauthorized()->updateOrCreate(
            [
                'access_key' => Arr::get($parsed, 'chave'),
                'user_id' => $userId,
            ],
            $this->invoiceAttributes($parsed, $issuer, $rawContent, $userId)
        );

        $this->syncItems($invoice, Arr::get($parsed, 'itens', []));
        $this->syncPayments($invoice, Arr::get($parsed, 'pagamento', []));

        return $invoice;
    }

    private function findOrCreateIssuer(array $emitente): ?Issuer
    {
        $cnpj = Arr::get($emitente, 'cnpj');

        if (empty($cnpj)) {
            return null;
        }

        // Sem nome (nota pendente, só temos o CNPJ da chave) não criamos o emitente: Issuer.name é fixado
        // no 1º import e ficaria vazio para sempre. Se ele já existe, reaproveitamos.
        if (empty(Arr::get($emitente, 'nome'))) {
            return Issuer::where('cnpj', $cnpj)->first();
        }

        $issuer = Issuer::firstOrCreate(
            ['cnpj' => $cnpj],
            [
                'name' => Arr::get($emitente, 'nome', ''),
                'street' => Arr::get($emitente, 'logradouro', ''),
                'street_number' => Arr::get($emitente, 'numero', ''),
                'neighborhood' => Arr::get($emitente, 'bairro', ''),
                'city' => Arr::get($emitente, 'municipio', ''),
                'state' => Arr::get($emitente, 'uf', ''),
                'zip_code' => Arr::get($emitente, 'cep', ''),
            ]
        );

        if ($issuer->wasRecentlyCreated && $issuer->city !== '' && $issuer->state !== '') {
            GeocodeIssuerJob::dispatch($issuer->id);
        }

        return $issuer;
    }

    private function invoiceAttributes(array $parsed, ?Issuer $issuer, string $rawContent, int $userId): array
    {
        return [
            'user_id' => $userId,
            'number' => Arr::get($parsed, 'numero', ''),
            'series' => Arr::get($parsed, 'serie', ''),
            'issued_at' => Arr::get($parsed, 'emitido_em') ?: now(),
            'environment' => Arr::get($parsed, 'ambiente', 'producao') === 'producao' ? 'production' : 'staging',
            'status' => Arr::get($parsed, 'status', InvoiceStatus::Authorized),
            'qrcode_url' => Arr::get($parsed, 'qrcode_url'),
            'issuer_id' => $issuer?->id,
            'total_icms_base' => (float) Arr::get($parsed, 'total.base_calculo_icms', 0),
            'total_icms' => (float) Arr::get($parsed, 'total.valor_icms', 0),
            'total_products' => (float) Arr::get($parsed, 'total.valor_produtos', 0),
            'total_discount' => (float) Arr::get($parsed, 'total.valor_desconto', 0),
            'total_amount' => (float) Arr::get($parsed, 'total.valor_nota', 0),
            'total_taxes' => (float) Arr::get($parsed, 'total.valor_tributos', 0),
            'raw_xml' => NfceXmlRedactor::redact($rawContent),
        ];
    }

    private function syncItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $item) {
            InvoiceItem::updateOrCreate(
                [
                    'invoice_id' => $invoice->id,
                    'item_number' => (int) Arr::get($item, 'numero_item', 0),
                ],
                [
                    'code' => Arr::get($item, 'codigo', ''),
                    'description' => Arr::get($item, 'descricao', ''),
                    'ncm' => Arr::get($item, 'ncm', ''),
                    'cfop' => Arr::get($item, 'cfop', ''),
                    'unit' => Arr::get($item, 'unidade', ''),
                    'quantity' => (float) Arr::get($item, 'quantidade', 0),
                    'unit_price' => (float) Arr::get($item, 'valor_unitario', 0),
                    'total_price' => (float) Arr::get($item, 'valor_total', 0),
                ]
            );
        }
    }

    private function syncPayments(Invoice $invoice, array $payments): void
    {
        $invoice->payments()->delete();

        if (empty($payments)) {
            return;
        }

        $now = now();
        InvoicePayment::insert(
            array_map(fn (array $p) => [
                'invoice_id' => $invoice->id,
                'method' => Arr::get($p, 'forma', 'outros'),
                'amount' => (float) Arr::get($p, 'valor', 0),
                'created_at' => $now,
                'updated_at' => $now,
            ], $payments)
        );
    }
}
