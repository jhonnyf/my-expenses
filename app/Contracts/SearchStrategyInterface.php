<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface SearchStrategyInterface
{
    public function search(string $query, int $userId): Collection;
}
