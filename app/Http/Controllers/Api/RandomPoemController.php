<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Poem;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Picks a random poem for the player's "shuffle" mode.
 *
 * Kept as a small JSON endpoint (instead of rendering a random slug together
 * with the page) so a fresh random poem is chosen every time the visitor
 * actually asks for the next one.
 */
class RandomPoemController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $exclude = trim((string) $request->query('exclude', ''));

        $poem = Poem::query()
            ->when($exclude !== '', fn ($query) => $query->where('slug', '!=', $exclude))
            ->inRandomOrder()
            ->first();

        if (! $poem) {
            return response()->json([
                'url' => null,
                'message' => 'Այլ բանաստեղծություն դեռ չկա։',
            ], 404);
        }

        return response()->json([
            'slug' => (string) $poem->slug,
            'title' => (string) $poem->title,
            'url' => Seo::poemUrl($poem),
        ]);
    }
}
