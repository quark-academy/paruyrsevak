<?php

namespace App\Services;

use App\Models\Poem;
use App\Support\WordTokenizer;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the poem_words table (karaoke timings) in sync with a poem text and
 * with the alignment returned by the ElevenLabs API.
 */
class PoemWordSynchronizer
{
    /**
     * Replace all word rows of a poem with the given list.
     *
     * @param  array<int, array{word: string, start?: float, end?: float}>  $words
     * @return int number of persisted rows
     */
    public function persist(Poem $poem, array $words): int
    {
        return DB::transaction(function () use ($poem, $words): int {
            $poem->words()->delete();

            $rows = [];
            foreach (array_values($words) as $position => $word) {
                $rows[] = [
                    'poem_id' => $poem->getKey(),
                    'word' => (string) $word['word'],
                    'start_time' => round((float) ($word['start'] ?? 0), 2),
                    'end_time' => round((float) ($word['end'] ?? 0), 2),
                    'word_order' => $position,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($rows !== []) {
                $poem->words()->insert($rows);
            }

            return count($rows);
        });
    }

    /**
     * Split the poem text into words, preserving any existing timings that
     * still match by position.
     *
     * @return int number of persisted rows
     */
    public function syncFromContent(Poem $poem): int
    {
        $existing = $poem->words()->get()->keyBy('word_order');
        $payload = [];

        foreach (WordTokenizer::split((string) $poem->content) as $position => $word) {
            $previous = $existing->get($position);

            $payload[] = [
                'word' => $word,
                'start' => $previous?->start_time ?? 0,
                'end' => $previous?->end_time ?? 0,
            ];
        }

        return $this->persist($poem, $payload);
    }
}
