<?php

namespace App\Services;

use App\Models\Poem;
use App\Support\Seo;

/**
 * Builds the serialisable payload consumed by the Vue Shorts player.
 *
 * The payload is shared by the server rendered page (embedded as JSON) and
 * by the JSON API endpoint so the frontend contract stays in one place.
 */
class PoemPlayerPayload
{
    /**
     * @return array<string, mixed>
     */
    public function build(Poem $poem): array
    {
        $poem->loadMissing('words');

        $words = $poem->words
            ->values()
            ->map(fn ($word): array => [
                'w' => (string) $word->word,
                's' => round((float) $word->start_time, 2),
                'e' => round((float) $word->end_time, 2),
            ])
            ->all();

        $hasRouteKey = filled($poem->getRouteKey());

        return [
            'slug' => (string) $poem->slug,
            'title' => $poem->title,
            'author' => 'Պարույր Սևակ',
            // The author's mark travels with the payload: the player draws it in
            // its own top bar, and asset() keeps it correct behind a sub path.
            // The author's mark travels with the payload: the player draws it in
            // its own top bar, and asset() keeps it correct behind a sub path.
            'logoUrl' => asset('images/sevak-logo.png'),
            // The portrait is the player's icon now; the drawn silhouette stays
            // as the fallback when the file is missing.
            'portraitUrl' => asset('images/sevak-portrait.png'),
            'content' => (string) $poem->content,
            'audioUrl' => $poem->hasAudio() ? $poem->audioUrl() : null,
            'isOriginal' => (bool) $poem->is_original,
            'plays' => (int) $poem->plays_count,
            'playsLabel' => $poem->playsLabel(),
            'canonical' => Seo::forPoem($poem),
            'lines' => $this->buildLines($poem, $words),
            'homeUrl' => route('home'),
            // A poem without a route key (empty slug) cannot be linked to:
            // never let URL generation throw on the front-end.
            'readingUrl' => $hasRouteKey
                ? route('poems.show', ['poem' => $poem])
                : route('home'),
            'playerUrl' => $hasRouteKey
                ? route('api.poems.player', ['poem' => $poem])
                : null,
            'playsUrl' => $hasRouteKey
                ? route('api.poems.play', ['poem' => $poem])
                : null,
            'generateUrl' => ($hasRouteKey && config('poems.public_generation'))
                ? route('api.poems.generate', ['poem' => $poem])
                : null,
            'randomUrl' => route('api.poems.random'),
            // Player preferences: defaults from config, the visitor's own
            // choice lives in the browser (localStorage) and wins on load.
            'prefs' => [
                'autoplay' => (bool) config('poems.player.autoplay'),
                'mode' => 'sequential',
            ],
            'autoplay' => (bool) config('poems.player.autoplay'),
            'background' => (string) config('poems.player.background'),
            'gold' => (string) config('poems.player.gold'),
            'prev' => $this->neighbour($poem, previous: true),
            'next' => $this->neighbour($poem, previous: false),
        ];
    }

    /** Words per line when a transcript came back without any line breaks. */
    protected const TRANSCRIPT_LINE_WORDS = 8;

    /**
     * Group the flat word list into display lines that follow the original line
     * breaks of the poem text.
     *
     * The timings arrive from two very different places: the character
     * alignment of the text we sent (exact) and forced alignment or
     * transcription of a recording, where the API drops punctuation-only
     * tokens, merges a word or hears a different one. The line breaks therefore
     * have to survive a word count that does not match exactly — otherwise the
     * karaoke collapses into one endless block on the front-end.
     *
     * @param  array<int, array{w: string, s: float, e: float}>  $words
     * @return array<int, array<int, array{w: string, s: float, e: float}>>
     */
    protected function buildLines(Poem $poem, array $words): array
    {
        if ($words === []) {
            return [];
        }

        $lengths = $this->lineLengths((string) $poem->content);

        if ($lengths === []) {
            return [$words];
        }

        // A text that came back as one long paragraph (a transcript written
        // from the recording) has no breaks to follow: use its sentence
        // endings instead, so the karaoke stays readable.
        if (count($lengths) === 1 && $lengths[0] > self::TRANSCRIPT_LINE_WORDS) {
            return $this->splitBySentence($words);
        }

        // The timings were built from this very text.
        if (array_sum($lengths) === count($words)) {
            return $this->sliceByCounts($words, $lengths);
        }

        // Forced alignment drops punctuation-only tokens (a lone dash, a
        // colon): count again without them before giving up on the breaks.
        $trimmed = $this->lineLengths((string) $poem->content, dropPunctuation: true);

        if ($trimmed !== $lengths && array_sum($trimmed) === count($words)) {
            return $this->sliceByCounts($words, $trimmed);
        }

        // The words still do not add up (a merge, a split, a mis-heard word):
        // keep the lines of the text and share the recording between them by
        // proportion — close enough for a highlight that follows the voice.
        return count($lengths) > 1
            ? $this->shareProportionally($words, $lengths)
            : [$words];
    }

    /**
     * Number of words on every non-empty line of the text.
     *
     * @return array<int, int>
     */
    protected function lineLengths(string $content, bool $dropPunctuation = false): array
    {
        $lengths = [];

        foreach (preg_split('/\R/u', $content) ?: [] as $line) {
            $tokens = preg_split('/\s+/u', trim($line), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if ($dropPunctuation) {
                $tokens = array_filter(
                    $tokens,
                    fn (string $token): bool => ! $this->isPunctuationOnly($token),
                );
            }

            if ($tokens !== []) {
                $lengths[] = count($tokens);
            }
        }

        return array_values($lengths);
    }

    /** A token that carries no word at all: a dash, a colon, an ellipsis. */
    protected function isPunctuationOnly(string $token): bool
    {
        return preg_match('/^[\p{P}\p{S}]+$/u', $token) === 1;
    }

    /**
     * Cut the word list at the given line lengths.
     *
     * @param  array<int, array{w: string, s: float, e: float}>  $words
     * @param  array<int, int>  $lengths
     * @return array<int, array<int, array{w: string, s: float, e: float}>>
     */
    protected function sliceByCounts(array $words, array $lengths): array
    {
        $lines = [];
        $offset = 0;

        foreach ($lengths as $length) {
            $lines[] = array_slice($words, $offset, $length);
            $offset += $length;
        }

        return $lines;
    }

    /**
     * Spread the timings over the lines of the text in proportion to how many
     * words each of them has, so every line still lights up in its turn.
     *
     * @param  array<int, array{w: string, s: float, e: float}>  $words
     * @param  array<int, int>  $lengths
     * @return array<int, array<int, array{w: string, s: float, e: float}>>
     */
    protected function shareProportionally(array $words, array $lengths): array
    {
        $textWords = array_sum($lengths);
        $count = count($words);
        $lines = [];
        $offset = 0;
        $consumed = 0;

        foreach ($lengths as $length) {
            $consumed += $length;

            $boundary = (int) round($count * $consumed / $textWords);
            $slice = array_slice($words, $offset, max(0, $boundary - $offset));

            if ($slice !== []) {
                $lines[] = $slice;
            }

            $offset = $boundary;
        }

        // Rounding can leave a word or two behind: the last line takes them.
        if ($offset < $count && $lines !== []) {
            $last = count($lines) - 1;
            $lines[$last] = array_merge($lines[$last], array_slice($words, $offset));
        }

        return $lines !== [] ? $lines : [$words];
    }

    /**
     * Lines of a text without breaks: end a line where the voice ends a
     * sentence, and never let one grow past a comfortable reading length.
     *
     * @param  array<int, array{w: string, s: float, e: float}>  $words
     * @return array<int, array<int, array{w: string, s: float, e: float}>>
     */
    protected function splitBySentence(array $words): array
    {
        $lines = [];
        $current = [];

        foreach ($words as $word) {
            $current[] = $word;

            $endsSentence = preg_match('/[։.!?…]$/u', (string) ($word['w'] ?? '')) === 1;

            if ($endsSentence || count($current) >= self::TRANSCRIPT_LINE_WORDS) {
                $lines[] = $current;
                $current = [];
            }
        }

        if ($current !== []) {
            $lines[] = $current;
        }

        return $lines;
    }

    /**
     * Previous or next poem for the vertical swipe navigation.
     *
     * @return array{slug: string, title: string, url: string}|null
     */
    protected function neighbour(Poem $poem, bool $previous): ?array
    {
        $key = $poem->getKey();

        // A poem that is not stored yet (e.g. a form preview) has no
        // neighbours: comparing an operator against a null value throws
        // "Illegal operator and value combination" in the query builder.
        if ($key === null) {
            return null;
        }

        $query = Poem::query()
            ->select(['id', 'slug', 'title'])
            ->where('id', $previous ? '<' : '>', $key)
            ->orderBy('id', $previous ? 'desc' : 'asc');

        $neighbour = $query->first();

        if (! $neighbour) {
            return null;
        }

        return [
            'slug' => $neighbour->slug,
            'title' => $neighbour->title,
            'url' => Seo::poemUrl($neighbour),
            // The player swaps poems inside the same document (so the browser
            // keeps the visitor's gesture and audio autoplay keeps working):
            // the neighbour therefore carries its own payload URL.
            'playerUrl' => route('api.poems.player', ['poem' => $neighbour]),
        ];
    }
}
