<?php

namespace App\Console\Commands;

use App\Services\OriginalTracksImporter;
use Illuminate\Console\Command;

class ImportOriginalTracks extends Command
{
    protected $signature = 'poems:import-tracks
                            {--path= : Папка с оригинальными аудиофайлами (по умолчанию ./original_tracks)}
                            {--no-copy : Не копировать файлы на public-диск (только привязка в БД)}';

    protected $description = 'Import archive recordings: create/link poems by file name and copy the audio';

    public function handle(OriginalTracksImporter $importer): int
    {
        $report = $importer->import(
            source: $this->option('path') ?: null,
            copyFiles: ! $this->option('no-copy'),
        );

        if (! ($report['ok'] ?? false)) {
            $this->error($report['message'] ?? 'Import failed.');

            return self::FAILURE;
        }

        $this->info('Source: '.$report['source']);

        foreach ($report['items'] as $item) {
            $this->line(sprintf(
                '%s %-28s %-10s %s',
                $item['created'] ? '+' : '~',
                $item['slug'],
                $item['lyrics'] ? 'with text' : 'no text',
                $item['audio_path'],
            ));
        }

        $this->info("Created: {$report['created']}, updated: {$report['linked']}");
        $this->comment('Тексты: положите их в database/seeders/data/lyrics/<slug>.txt (или storage/app/lyrics/) и повторите команду.');

        return self::SUCCESS;
    }
}
