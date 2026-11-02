<?php

namespace App\Services\Seo;

use Illuminate\Support\Str;

/**
 * Comparação de palavra-chave sem acento e sem caixa: "Automação" casa com "automacao".
 */
class Keyword
{
    /**
     * @return list<string>
     */
    public static function words(string $text): array
    {
        $normalized = trim((string) preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($text))));

        return $normalized === '' ? [] : explode(' ', $normalized);
    }

    public static function normalize(string $text): string
    {
        return implode(' ', self::words($text));
    }

    public static function contains(string $text, string $keyword): bool
    {
        $keyword = self::normalize($keyword);

        return $keyword !== '' && str_contains(' '.self::normalize($text).' ', ' '.$keyword.' ');
    }

    /**
     * Todas as palavras relevantes da palavra-chave aparecem, mesmo fora de ordem.
     */
    public static function partial(string $text, string $keyword): bool
    {
        $words = array_filter(self::words($keyword), fn (string $word) => strlen($word) > 2);
        $haystack = array_flip(self::words($text));

        return $words !== [] && array_diff($words, array_keys($haystack)) === [];
    }

    public static function count(string $text, string $keyword): int
    {
        $keyword = self::normalize($keyword);

        return $keyword === '' ? 0 : substr_count(' '.self::normalize($text).' ', ' '.$keyword.' ');
    }

    /**
     * Posição relativa (0 a 1) da primeira ocorrência no texto, ou null.
     */
    public static function position(string $text, string $keyword): ?float
    {
        $haystack = self::normalize($text);
        $needle = self::normalize($keyword);

        if ($needle === '' || $haystack === '') {
            return null;
        }

        $position = strpos(' '.$haystack.' ', ' '.$needle.' ');

        return $position === false ? null : $position / max(1, strlen($haystack));
    }
}
