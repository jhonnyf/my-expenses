<?php

namespace App\DTOs;

readonly class CategoryKeywordsAiSuggestion
{
    public function __construct(public array $keywords) {}

    public static function empty(): self
    {
        return new self([]);
    }

    public static function fromGeminiPayload(array $data): self
    {
        $rawKeywords = is_array($data['keywords'] ?? null) ? $data['keywords'] : [];

        $keywords = array_values(array_filter(
            array_map(fn ($k) => trim((string) $k), $rawKeywords),
            fn (string $k) => $k !== ''
        ));

        return new self($keywords);
    }

    public function toArray(): array
    {
        return [
            'keywords' => $this->keywords,
        ];
    }
}
