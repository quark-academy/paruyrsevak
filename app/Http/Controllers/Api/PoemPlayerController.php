<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Poem;
use App\Services\ElevenLabsService;
use App\Services\PoemPlayerPayload;
use App\Services\PoemWordSynchronizer;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * JSON contract consumed by the Vue Shorts player.
 */
class PoemPlayerController extends Controller
{
    /**
     * Current player state: audio URL, karaoke lines and navigation.
     */
    public function show(Poem $poem, PoemPlayerPayload $payload): JsonResponse
    {
        return response()->json($payload->build($poem) + [
            'status' => $poem->hasAudio() ? 'ready' : 'missing',
        ]);
    }

    /**
     * Lazy generation triggered by the player while it shows the pulsing
     * silhouette. Guarded by the poems.public_generation config flag.
     */
    public function generate(
        Poem $poem,
        ElevenLabsService $elevenLabs,
        PoemPlayerPayload $payload,
        PoemWordSynchronizer $synchronizer,
    ): JsonResponse {
        if ($poem->hasAudio()) {
            return response()->json($payload->build($poem) + ['status' => 'ready', 'cached' => true]);
        }

        if (! config('poems.public_generation')) {
            return response()->json([
                'status' => 'disabled',
                'message' => 'ԱԻ գեներացիան հասանելի չէ։ Ստեղծիր աուդիոն վահանակից։',
            ], 403);
        }

        if (! $elevenLabs->isConfigured()) {
            return response()->json([
                'status' => 'unconfigured',
                'message' => 'ElevenLabs-ը կարգավորված չէ։',
            ], 422);
        }

        @set_time_limit(max(120, (int) config('services.elevenlabs.timeout', 180) + 30));

        try {
            $result = $elevenLabs->generateAudioForPoem($poem);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'Գեներացիան ձախողվեց․ '.$exception->getMessage(),
            ], 500);
        }

        $poem->update([
            'audio_path' => $result['path'],
            'is_original' => false,
        ]);

        if ($result['words'] !== []) {
            $synchronizer->persist($poem, $result['words']);
            $poem->load('words');
        }

        return response()->json($payload->build($poem) + [
            'status' => 'ready',
            'cached' => (bool) $result['cached'],
        ]);
    }
}
