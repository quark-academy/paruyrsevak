<?php

namespace App\Services;

use App\Models\Poem;
use Illuminate\Support\Facades\Cache;

/**
 * Counts listens of the poem recordings.
 *
 * A listen is registered once per visitor inside a configurable window, so a
 * page refresh, a pause/resume or a looped track does not inflate the number.
 * The visitor is only used as a cache key (never stored, see the controller).
 */
class PlayCounter
{
    /**
     * Register a listen of the poem audio.
     *
     * @param  string|null  $visitor  stable, non-reversible visitor key (e.g. a hashed IP)
     * @return bool whether the counter was incremented
     */
    public function record(Poem $poem, ?string $visitor = null): bool
    {
        if (! $poem->hasAudio()) {
            return false;
        }

        $seconds = max(0, (int) config('poems.plays.dedupe_seconds', 1800));
        $key = 'poem-plays:'.$poem->getKey().':'.($visitor ?: 'anonymous');

        if ($seconds > 0 && ! Cache::add($key, 1, now()->addSeconds($seconds))) {
            return false;
        }

        $poem->increment('plays_count');

        return true;
    }
}
