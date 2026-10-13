<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Poem;
use App\Services\PlayCounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registers a listen of a poem recording (called by the players when the
 * audio actually starts playing).
 */
class PoemPlayController extends Controller
{
    public function __invoke(Request $request, Poem $poem, PlayCounter $counter): JsonResponse
    {
        // The IP is hashed before it is used as a cache key — it is never
        // stored in the database, only used to de-duplicate a visitor.
        $visitor = hash('sha256', (string) $request->ip());

        $counted = $counter->record($poem, $visitor);

        return response()->json([
            'counted' => $counted,
            'plays' => (int) $poem->plays_count,
            'label' => $poem->playsLabel(),
        ]);
    }
}
