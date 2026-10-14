<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Poem;
use App\Support\Query;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Search behind the header icon: a handful of matches plus the URL of the full
 * catalogue search, so the modal never needs a page of its own.
 *
 * The matching is exactly what the catalogue does (title or text, the newest or
 * the most listened first), which keeps the modal and the catalogue agreeing.
 */
class PoemSearchController extends Controller
{
    /** How many matches the modal shows at once. */
    public const LIMIT = 8;

    public function __invoke(Request $request): JsonResponse
    {
        $term = Query::string($request, 'q');

        if (mb_strlen($term) < 2) {
            return response()->json([
                'query' => $term,
                'results' => [],
                'allUrl' => route('poems.index'),
                'message' => 'Գրիր առնվազն երկու տառ։',
            ]);
        }

        $results = Poem::query()
            ->where(function ($query) use ($term): void {
                $query->where('title', 'like', "%{$term}%")
                    ->orWhere('content', 'like', "%{$term}%");
            })
            ->orderByDesc('plays_count')
            ->orderBy('id')
            ->take(self::LIMIT)
            ->get(['id', 'slug', 'title', 'is_original']);

        return response()->json([
            'query' => $term,
            'allUrl' => route('poems.index', ['q' => $term]),
            'results' => $results
                ->map(fn (Poem $poem): array => [
                    'slug' => (string) $poem->slug,
                    'title' => (string) $poem->title,
                    'label' => $poem->voiceSourceLabel(),
                    'url' => Seo::poemUrl($poem),
                    // The player swaps poems inside its own document, so it
                    // needs the payload URL of the match as well.
                    'playerUrl' => route('api.poems.player', ['poem' => $poem]),
                ])
                ->all(),
        ]);
    }
}
