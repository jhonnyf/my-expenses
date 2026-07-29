<?php

namespace App\DTOs;

readonly class ProductNameAiSuggestion
{
    public function __construct(
        public bool $confident,
        public ?string $suggestedName,
    ) {}

    public static function notConfident(): self
    {
        return new self(confident: false, suggestedName: null);
    }

    public static function fromGeminiPayload(array $data): self
    {
        $suggestedName = trim((string) ($data['suggested_name'] ?? ''));

        if (! ($data['confident'] ?? false) || $suggestedName === '') {
            return self::notConfident();
        }

        return new self(confident: true, suggestedName: $suggestedName);
    }

    public function toArray(): array
    {
        return [
            'confident' => $this->confident,
            'suggested_name' => $this->suggestedName,
        ];
    }
}
