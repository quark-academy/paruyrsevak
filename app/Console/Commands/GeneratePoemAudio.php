<?php

namespace App\Console\Commands;

use App\Models\Poem;
use App\Services\ElevenLabsService;
use App\Services\PoemWordSynchronizer;
use Illuminate\Console\Command;
use Throwable;

class GeneratePoemAudio extends Command
{
    protected $signature = 'poem:generate-audio
                            {poem : Poem id or slug}
                            {--force : Regenerate even if a cached .mp3 exists}
                            {--no-words : Skip importing word timings}';

    protected $description = 'Generate AI voice (.mp3) for a poem via ElevenLabs using lazy generation';

    public function handle(ElevenLabsService $elevenLabs, PoemWordSynchronizer $synchronizer): int
    {
        $key = (string) $this->argument('poem');

        $poem = Poem::query()
            ->where('id', $key)
            ->orWhere('slug', $key)
            ->first();

        if (! $poem) {
            $this->error("Poem [{$key}] not found.");

            return self::FAILURE;
        }

        if (! $elevenLabs->isConfigured()) {
            $this->error('ElevenLabs is not configured. Set ELEVENLABS_API_KEY and ELEVENLABS_VOICE_ID.');

            return self::FAILURE;
        }

        $this->info("Poem: {$poem->title}");

        try {
            $result = $elevenLabs->generateAudioForPoem($poem, (bool) $this->option('force'));
        } catch (Throwable $exception) {
            $this->error('Generation failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $poem->update([
            'audio_path' => $result['path'],
            'is_original' => false,
        ]);

        $this->info($result['cached'] ? 'Cached audio reused.' : 'New audio generated.');
        $this->line('Storage path: '.$result['path']);

        if (! $this->option('no-words') && $result['words'] !== []) {
            $count = $synchronizer->persist($poem, $result['words']);
            $this->info("Word timings saved: {$count}");
        }

        return self::SUCCESS;
    }
}
