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
                'loop' => false,
                'shuffle' => false,
            ],
            'autoplay' => (bool) config('poems.player.autoplay'),
            'background' => (string) config('poems.player.background'),
            'gold' => (string) config('poems.player.gold'),
            'prev' => $this->neighbour($poem, previous: true),
            'next' => $this->neighbour($poem, previous: false),
        ];
    }

    /**
     * Group the flat word list into display lines that follow the original
     * line breaks of the poem text. Falls back to one flowing block when the
     * word count does not match the text (e.g. manually edited timings).
     *
     * @param  array<int, array{w: string, s: float, e: float}>  $words
     * @return array<int, array<int, array{w: string, s: float, e: float}>>
     */
    protected function buildLines(Poem $poem, array $words): array
    {
        if ($words === []) {
            return [];
        }

        $lengths = [];

        foreach (preg_split('/\R/u', (string) $poem->content) ?: [] as $line) {
            $tokens = preg_split('/\s+/u', trim($line), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($tokens !== []) {
                $lengths[] = count($tokens);
            }
        }

        if (array_sum($lengths) !== count($words)) {
            return [$words];
        }

        $lines = [];
        $offset = 0;

        foreach ($lengths as $length) {
            $lines[] = array_slice($words, $offset, $length);
            $offset += $length;
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
        ];
    }
}
