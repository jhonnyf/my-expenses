<?php

namespace App\Support;

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

    private static function stripAccents(string $text): string
    {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return $transliterated !== false ? $transliterated : $text;
    }
}
