<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

trait ParsesKeywords
{
    public const MAX_KEYWORDS = 50;

    public const MAX_KEYWORD_LENGTH = 40;

    /**
     * Aceita lista ou texto separado por vírgula; remove vazios e repetidas (sem diferenciar maiúsculas).
     *
     * @return list<string>
     */
    public function parsedKeywords(): array
    {
        $raw = $this->input('keywords');

        if (! $raw) {
            return [];
        }

        return collect(is_array($raw) ? $raw : explode(',', (string) $raw))
            ->map(fn ($keyword) => trim((string) $keyword))
            ->filter()
            ->unique(fn (string $keyword) => mb_strtoupper($keyword))
            ->values()
            ->all();
    }

    /** Para uso dentro de `after()`: já roda depois das regras, então só confere e registra os erros. */
    protected function validateKeywordLimits(Validator $validator): void
    {
        $keywords = $this->parsedKeywords();

        if (count($keywords) > self::MAX_KEYWORDS) {
            $validator->errors()->add('keywords', 'Use no máximo '.self::MAX_KEYWORDS.' palavras-chave.');
        } elseif (collect($keywords)->contains(fn (string $keyword) => mb_strlen($keyword) > self::MAX_KEYWORD_LENGTH)) {
            $validator->errors()->add('keywords', 'Cada palavra-chave pode ter no máximo '.self::MAX_KEYWORD_LENGTH.' caracteres.');
        }
    }
}
