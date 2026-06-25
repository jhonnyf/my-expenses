<?php

namespace App\DTOs;

readonly class ImportPayload
{
    public function __construct(
        public readonly array $parsed,
        public readonly string $rawContent,
    ) {}
}
