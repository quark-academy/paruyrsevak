<?php

namespace App\Support;

/**
 * Helpers distinguishing a real poem text from the placeholder that
 * OriginalTracksImporter stores when no lyrics file is available yet.
 */
final class PoemText
{
    /** Fragment always present in the importer's placeholder. */
    public const PLACEHOLDER_MARKER = 'Տեքստը կարելի է ավելացնել ադմին վահանակում';

    /** Shortest text we consider a real poem (used for forced alignment). */
    public const MIN_REAL_LENGTH = 40;

    /**
     * Empty text or the importer's placeholder.
     */
    public static function isPlaceholder(?string $text): bool
    {
        $text = trim((string) $text);

        return $text === '' || str_contains($text, self::PLACEHOLDER_MARKER);
    }

    /**
     * A text long enough to be aligned against a recording.
     */
    public static function looksReal(?string $text): bool
    {
        $text = trim((string) $text);

        return mb_strlen($text) >= self::MIN_REAL_LENGTH && ! self::isPlaceholder($text);
    }
}
