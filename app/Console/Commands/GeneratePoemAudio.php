<?php

namespace App\Console\Commands;

use App\Models\Poem;
use App\Services\ElevenLabsService;
use App\Services\PoemWordSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

class GeneratePoemAudio extends Command
{
    protected $signature = 'poem:generate-audio
                            {poem? : Poem id or slug (omit it together with --all)}
                            {--all : Every poem that has no recording yet}
                            {--force : Regenerate even if a cached .mp3 exists}
                            {--no-words : Skip importing word timings}';

    protected $description = 'Generate the AI voice (.mp3) with its word timings via ElevenLabs';

    public function handle(ElevenLabsService $elevenLabs, PoemWordSynchronizer $synchronizer): int
    {
        $key = (string) $this->argument('poem');

        if ($key === '' && ! $this->option('all')) {
            $this->error('Նշիր id/slug կամ օգտագործիր --all։ Pass a poem or use --all.');

            return self::FAILURE;
        }

        if (! $elevenLabs->isConfigured()) {
            $this->error('ElevenLabs is not configured. Set ELEVENLABS_API_KEY and ELEVENLABS_VOICE_ID.');

            return self::FAILURE;
        }

        $poems = $this->poems($key);

        if ($poems->isEmpty()) {
            if ($key !== '') {
                $this->error("Poem [{$key}] not found.");

                return self::FAILURE;
            }

            $this->warn('No poem is waiting for a recording.');

            return self::SUCCESS;
        }

        $generated = 0;
        $failed = 0;

        foreach ($poems as $poem) {
            $this->info("Poem: {$poem->title}");

            try {
                $result = $elevenLabs->generateAudioForPoem($poem, (bool) $this->option('force'));
            } catch (Throwable $exception) {
                $this->error('  Generation failed: '.$exception->getMessage());
                $failed++;

                continue;
            }

            $poem->update([
                'audio_path' => $result['path'],
                'is_original' => false,
            ]);

            $this->line('  '.($result['cached'] ? 'Cached audio reused.' : 'New audio generated.').' '.$result['path']);

            if (! $this->option('no-words') && $result['words'] !== []) {
                $count = $synchronizer->persist($poem, $result['words']);
                $this->line("  Word timings saved: {$count}");
            }

            $generated++;
        }

        if ($poems->count() > 1) {
            $this->info("Done: {$generated} generated, {$failed} failed.");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * The poem(s) to work on: one by id/slug, or every poem without a
     * recording when --all is used.
     *
     * @return Collection<int, Poem>
     */
    protected function poems(string $key): Collection
    {
        if ($key !== '') {
            return Poem::query()
                ->where('id', $key)
                ->orWhere('slug', $key)
                ->get();
        }

        return Poem::query()
            ->when(
                ! $this->option('force'),
                fn ($query) => $query->where(fn ($nested) => $nested
                    ->whereNull('audio_path')
                    ->orWhere('audio_path', '')),
            )
            ->orderBy('id')
            ->get();
    }
}
