<?php

namespace App\Contracts;

use App\DTOs\ImportPayload;
use Illuminate\Http\Request;

interface ImportStrategyInterface
{
    public function getErrorField(): string;

    public function resolve(Request $request): ImportPayload;
}
