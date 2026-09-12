<?php

namespace App\Support;

use Illuminate\Support\Str;

class ProductNameNormalizer
{
    public static function normalize(string $text): string
    {
        $text = mb_strtoupper(trim($text));
        $text = self::stripAccents($text);
        $text = preg_replace('/[^A-Z0-9 ]+/', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    /**
     * @return list<string>
     */
    public static function tokenize(string $text): array
    {
        $normalized = self::normalize($text);

        if ($normalized === '') {
            return [];
        }

        return explode(' ', $normalized);
    }

    /**
     * Usa Str::ascii() (tabela de transliteração do próprio Laravel) em vez de
     * iconv(..., 'ASCII//TRANSLIT//IGNORE', ...): a implementação de TRANSLIT
     * do iconv depende da libc do sistema e diverge entre glibc e musl (Alpine
     * — usado pela imagem `php:8.4-fpm-alpine` deste projeto), onde caracteres
     * como "Ã"/"Ç" viram sequências com til (ex.: "AÇÃO" -> "AC~AO") em vez de
     * simplesmente cair o acento, quebrando comparações de texto normalizado.
     */
    private static function stripAccents(string $text): string
    {
        return Str::ascii($text);
    }
}
