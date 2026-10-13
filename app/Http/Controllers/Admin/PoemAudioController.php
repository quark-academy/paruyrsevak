<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PoemAudioRequest;
use App\Models\Poem;
use App\Services\ElevenLabsService;
use App\Services\PoemWordSynchronizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PoemAudioController extends Controller
{
    public function __construct(
        protected ElevenLabsService $elevenLabs,
        protected PoemWordSynchronizer $wordSynchronizer,
    ) {}

    /**
     * Upload an archive (.mp3) recording that overrides the AI generation.
     */
    public function store(PoemAudioRequest $request, Poem $poem): RedirectResponse
    {
        $file = $request->file('audio');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'mp3');
        $relativePath = 'original_audio/'.$poem->slug.'-'.now()->format('YmdHis').'.'.$extension;

        Storage::disk('public')->putFileAs(
            'original_audio',
            $file,
            basename($relativePath),
            ['visibility' => 'public']
        );

        if (filled($poem->audio_path) && Storage::disk('public')->exists((string) $poem->audio_path)) {
            Storage::disk('public')->delete((string) $poem->audio_path);
        }

        $poem->update([
            'audio_path' => $relativePath,
            'is_original' => true,
        ]);

        return redirect()
            ->route('admin.poems.show', $poem)
            ->with('status', 'Օրիգինալ աուդիոն վերբեռնվեց։');
    }

    /**
     * Lazy AI generation: reuse the cached .mp3 or render a new one.
     */
    public function generate(Request $request, Poem $poem): RedirectResponse
    {
        $force = $request->boolean('force');

        try {
            $result = $this->elevenLabs->generateAudioForPoem($poem, $force);
        } catch (Throwable $exception) {
            return redirect()
                ->route('admin.poems.show', $poem)
                ->withErrors(['audio' => 'ԱԻ գեներացիան ձախողվեց․ '.$exception->getMessage()]);
        }

        $poem->update([
            'audio_path' => $result['path'],
            'is_original' => false,
        ]);

        if ($request->boolean('sync_words') && $result['words'] !== []) {
            $this->wordSynchronizer->persist($poem, $result['words']);
        }

        $message = $result['cached']
            ? 'ԱԻ աուդիոն վերցվեց քեշից։'
            : 'ԱԻ աուդիոն գեներացվեց ElevenLabs-ով։';

        return redirect()
            ->route('admin.poems.show', $poem)
            ->with('status', $message);
    }

    /**
     * Remove the currently selected audio track.
     */
    public function destroy(Poem $poem): RedirectResponse
    {
        if (filled($poem->audio_path) && Storage::disk('public')->exists((string) $poem->audio_path)) {
            Storage::disk('public')->delete((string) $poem->audio_path);
        }

        $poem->update(['audio_path' => null, 'is_original' => false]);

        return redirect()
            ->route('admin.poems.show', $poem)
            ->with('status', 'Աուդիոն հեռացվեց։');
    }

    /**
     * Generate (or reuse) the AI audio and immediately import the word
     * alignment into the karaoke timings table.
     */
    public function align(Request $request, Poem $poem): RedirectResponse
    {
        try {
            $result = $this->elevenLabs->generateAudioForPoem($poem, $request->boolean('force'));
        } catch (Throwable $exception) {
            return redirect()
                ->route('admin.poems.timings', $poem)
                ->withErrors(['aligned' => 'Համաժամեցումը ձախողվեց․ '.$exception->getMessage()]);
        }

        $poem->update([
            'audio_path' => $result['path'],
            'is_original' => false,
        ]);

        $count = $result['words'] !== []
            ? $this->wordSynchronizer->persist($poem, $result['words'])
            : 0;

        return redirect()
            ->route('admin.poems.timings', $poem)
            ->with('status', "Համաժամեցվեց ElevenLabs-ից․ {$count} բառ։");
    }
}
