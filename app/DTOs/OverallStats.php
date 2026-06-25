<?php

namespace App\DTOs;

readonly class OverallStats
{
    public function __construct(
        public float $totalExpenses,
        public float $totalTaxes,
        public int $totalPurchases,
    ) {}

    public static function fromQueryResult(object $result): self
    {
        return new self(
            totalExpenses: (float) $result->totalExpenses,
            totalTaxes: (float) $result->totalTaxes,
            totalPurchases: (int) $result->totalPurchases,
        );
    }
}
