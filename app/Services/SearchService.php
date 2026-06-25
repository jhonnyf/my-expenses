<?php

namespace App\Services;

use App\Search\Strategies\InvoiceSearchStrategy;
use App\Search\Strategies\IssuerSearchStrategy;
use App\Search\Strategies\ProductSearchStrategy;

class SearchService
{
    public function __construct(
        private readonly IssuerSearchStrategy $issuerStrategy,
        private readonly InvoiceSearchStrategy $invoiceStrategy,
        private readonly ProductSearchStrategy $productStrategy,
    ) {}

    public function search(string $query, int $userId): array
    {
        return [
            'emissores'    => $this->issuerStrategy->search($query, $userId),
            'notas_fiscais' => $this->invoiceStrategy->search($query, $userId),
            'produtos'     => $this->productStrategy->search($query, $userId),
        ];
    }
}
