<?php

namespace App\Console\Commands;

use App\Models\Poem;
use App\Services\ElevenLabsService;
use App\Services\PoemWordSynchronizer;
use Illuminate\Console\Command;
use Throwable;

class AlignAudioWords extends Command
{
    protected $signature = 'poems:align-audio
                            {slug? : Ստեղծագործության slug}
                            {--all : Բոլոր ստեղծագործությունները, որոնք ունեն աուդիո}
                            {--force : Վերագրանցել նաև այն ստեղծագործությունների բառերը, որոնք արդեն կան}';

    protected $description = 'Build karaoke words + timings from the poem recordings (forced alignment / transcription)';

    public function handle(ElevenLabsService $elevenLabs, PoemWordSynchronizer $synchronizer): int
    {
        $slug = $this->argument('slug');

        if (blank($slug) && ! $this->option('all')) {
            $this->error('Նշիր slug կամ օգտագործիր --all։');

            return self::FAILURE;
        }

        $query = Poem::query()->whereNotNull('audio_path')->where('audio_path', '!=', '');

        if (filled($slug)) {
            $query->where('slug', $slug);
        }

        if (! $this->option('force')) {
            $query->whereDoesntHave('words');
        }

        $poems = $query->orderBy('id')->get();

        if ($poems->isEmpty()) {
            $this->warn('Համապատասխան ստեղծագործություններ չկան։');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($poems as $poem) {
            try {
                $result = $elevenLabs->alignFromPoemAudio($poem);

                if ($result['words'] === []) {
                    $this->error(sprintf('%-34s բառեր չստացվեցին', $poem->slug));
                    $failed++;

                    continue;
                }

                $count = $synchronizer->persist($poem, $result['words']);

                if (filled($result['text']) && ! $poem->hasRealText()) {
                    $poem->update(['content' => $result['text']]);
                }

                $this->info(sprintf(
                    '%-34s %-14s %3d բառ%s',
                    $poem->slug,
                    $result['method'],
                    $count,
                    filled($result['text']) ? ' (տեքստը լրացվեց)' : '',
                ));
            } catch (Throwable $exception) {
                $this->error(sprintf('%-34s ձախողվեց․ %s', $poem->slug, $exception->getMessage()));
                $failed++;
            }
        }

        $this->line(sprintf('Մշակվեց %d, ձախողվեց %d։', $poems->count() - $failed, $failed));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
