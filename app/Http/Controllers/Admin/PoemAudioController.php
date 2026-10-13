<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PoemAudioRequest;
use App\Models\Poem;
use App\Services\ElevenLabsService;
use App\Services\PoemWordSynchronizer;
use App\Support\PoemText;
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

        Storage::disk($poem->audioDisk())->putFileAs(
            'original_audio',
            $file,
            basename($relativePath),
            ['visibility' => 'private']
        );

        if ($poem->audio_path !== $relativePath) {
            $poem->deleteAudioFile();
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
     *
     * An archive recording is never replaced implicitly — the editor has to
     * tick "replace_original" for that.
     */
    public function generate(Request $request, Poem $poem): RedirectResponse
    {
        if (! PoemText::looksReal($poem->content)) {
            return redirect()
                ->route('admin.poems.show', $poem)
                ->withErrors(['audio' => 'Տեքստ չկա (կամ դեռ ներմուծված չէ)․ ԱԻ ձայնը չգեներացվեց։ Սկզբում լրացրու բանաստեղծության տեքստը։']);
        }

        if ($this->keepsOriginal($request, $poem)) {
            return redirect()
                ->route('admin.poems.show', $poem)
                ->withErrors(['audio' => 'Սա արխիվային ձայնագրություն է․ ԱԻ ձայնը չհրապարակվեց, որ չփոխարինի բնօրինակը։ Ցանկության դեպքում նշիր «Փոխարինել բնօրինակը ԱԻ-ով»։']);
        }

        $force = $request->boolean('force');
        $this->allowLongRequest();

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
        $poem->deleteAudioFile();

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
        if (! PoemText::looksReal($poem->content)) {
            return redirect()
                ->route('admin.poems.timings', $poem)
                ->withErrors(['aligned' => 'Տեքստ չկա․ ԱԻ ձայնը չգեներացվեց։ Օգտագործիր «Համաժամեցնել ձայնագրությունից» կոճակը՝ ձայնագրությունից բառեր ստանալու համար։']);
        }

        if ($this->keepsOriginal($request, $poem)) {
            return redirect()
                ->route('admin.poems.timings', $poem)
                ->withErrors(['aligned' => 'Սա արխիվային ձայնագրություն է․ ԱԻ ձայնը չգեներացվեց, որ չփոխարինի բնօրինակը։ Օգտագործիր «Համաժամեցնել ձայնագրությունից»։']);
        }

        $this->allowLongRequest();

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

    /**
     * Build the karaoke word list from the poem's own recording.
     *
     * Works for archive recordings as well as for AI renders:
     *  - poem text present → forced alignment against the recording;
     *  - no text yet       → transcription with word timestamps, the text is
     *    then filled automatically.
     */
    public function transcribe(Request $request, Poem $poem): RedirectResponse
    {
        if (! $poem->hasAudio()) {
            return back()->withErrors(['align' => 'Աուդիո ֆայլ չկա։ Սկզբում վերբեռնիր բնօրինակը կամ գեներացրու ԱԻ ձայն։']);
        }

        $this->allowLongRequest();

        try {
            $result = $this->elevenLabs->alignFromPoemAudio($poem);
        } catch (Throwable $exception) {
            return back()->withErrors(['align' => 'Համաժամեցումը ձախողվեց․ '.$exception->getMessage()]);
        }

        if ($result['words'] === []) {
            return back()->withErrors(['align' => 'Բառեր չստացվեցին։ Ստուգիր աուդիոն և (առկայության դեպքում) տեքստը։']);
        }

        $count = $this->wordSynchronizer->persist($poem, $result['words']);

        $message = $result['method'] === 'transcription'
            ? "Ձայնագրությունը ներմուծվեց բառերով․ {$count} բառ։"
            : "Բառերը համաժամեցվեցին ձայնագրության հետ․ {$count} բառ։";

        // A transcript fills the text only when the poem has none yet.
        if (filled($result['text']) && ! $poem->hasRealText()) {
            $poem->update(['content' => $result['text']]);
            $message .= ' Տեքստը լրացվեց ավտոմատ (ստուգիր ուղղագրությունը)։';
        }

        if ($result['loss'] !== null && $result['loss'] > 0.5) {
            $message .= sprintf(' Ուշադրություն․ համաժամեցման շեղումը բարձր է (%.2f)՝ տեքստը կարող է չհամապատասխանել ձայնագրությանը։', $result['loss']);
        }

        return back()->with('status', $message);
    }

    /**
     * True when publishing AI audio would silently replace an archive recording.
     */
    protected function keepsOriginal(Request $request, Poem $poem): bool
    {
        return $poem->is_original && $poem->hasAudio() && ! $request->boolean('replace_original');
    }

    /**
     * ElevenLabs calls are slow; shared hosting kills requests by default.
     */
    protected function allowLongRequest(): void
    {
        @set_time_limit(max(180, (int) config('services.elevenlabs.timeout', 180) + 60));
        @ignore_user_abort(true);
    }
}
