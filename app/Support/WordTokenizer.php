<?php

namespace App\Support;

class WordTokenizer
{
    /**
     * Split a poem text into displayable word tokens using whitespace rules.
     *
     * Line breaks and repeated spaces are normalised so that the tokens match
     * the character alignment returned by the ElevenLabs API as closely as
     * possible.
     *
     * @return array<int, string>
     */
    public static function split(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim((string) preg_replace('/[ \t]+/u', ' ', $text));

        if ($text === '') {
            return [];
        }

        $tokens = preg_split('/\s+/u', $text) ?: [];

        return array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
    }

    /**
     * Count the words of a text.
     */
    public static function count(string $text): int
    {
        return count(self::split($text));
    }

    /**
     * Detect whether a character is a whitespace break inside an alignment.
     */
    public static function isWhitespace(string $character): bool
    {
        return preg_match('/^\s$/u', $character) === 1;
    }
}
