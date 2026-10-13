<?php

namespace Database\Seeders;

use App\Services\OriginalTracksImporter;
use Illuminate\Database\Seeder;

/**
 * Creates catalogue entries for the archive recordings found in ./original_tracks.
 *
 * Titles/slugs/descriptions are generated from the file names; the poem texts
 * are taken from database/seeders/data/lyrics/<slug>.txt (or storage/app/lyrics/)
 * when such a file exists, so no copyrighted lyrics live inside the code.
 */
class OriginalTracksSeeder extends Seeder
{
    /**
     * Optional file name => title overrides (for odd/truncated file names).
     *
     * @var array<string, string>
     */
    protected array $titleOverrides = [
        // 'Անունը.MP3' => 'Լրիվ վերնագիրը',
    ];

    public function run(): void
    {
        $report = app(OriginalTracksImporter::class)->import(titleOverrides: $this->titleOverrides);

        if (! ($report['ok'] ?? false)) {
            $this->command?->warn($report['message'] ?? 'Import failed.');

            return;
        }

        $this->command?->info('Source: '.$report['source']);

        foreach ($report['items'] as $item) {
            $this->command?->line(sprintf(
                '%s %-28s %-10s %s',
                $item['created'] ? '+' : '~',
                $item['slug'],
                $item['lyrics'] ? 'with text' : 'no text',
                $item['audio_path'],
            ));
        }

        $this->command?->info("Created: {$report['created']}, updated: {$report['linked']}");
    }
}
