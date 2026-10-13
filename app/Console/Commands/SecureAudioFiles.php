<?php

namespace App\Console\Commands;

use App\Models\Poem;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * Moves the recordings out of the publicly linked "public" disk into the
 * private "audio" disk.
 *
 * Until this command runs, a recording can still be fetched as a plain file
 * (/storage/original_audio/....mp3). Afterwards the only way in is the signed,
 * expiring "poems.audio" route, and the legacy folders are blocked for the web
 * server as an extra safety net.
 */
class SecureAudioFiles extends Command
{
    protected $signature = 'poems:secure-audio
                            {--dry-run : Only report what would be moved}
                            {--prune : Delete files in the public disk that no poem references}';

    protected $description = 'Move poem recordings to the private disk so they cannot be downloaded directly';

    /** Folders that were publicly reachable before the hardening. */
    protected const LEGACY_DIRECTORIES = ['original_audio', 'ai_audio'];

    public function handle(): int
    {
        $source = Storage::disk('public');
        $target = Storage::disk((string) config('poems.audio.disk', 'audio'));
        $dryRun = (bool) $this->option('dry-run');

        $moved = 0;
        $duplicates = 0;
        $alreadyPrivate = 0;
        $missing = 0;
        $overwritten = 0;
        $referenced = [];

        $poems = Poem::query()
            ->whereNotNull('audio_path')
            ->where('audio_path', '!=', '')
            ->orderBy('id')
            ->cursor();

        foreach ($poems as $poem) {
            $path = (string) $poem->audio_path;
            $referenced[] = $path;

            if (! $source->exists($path)) {
                // Nothing publicly linked: the file is either already private
                // or gone for good.
                $target->exists($path) ? $alreadyPrivate++ : $missing++;

                continue;
            }

            $publicSize = $source->size($path);
            $privateSize = $target->exists($path) ? $target->size($path) : null;

            if ($privateSize === null) {
                $moved++;
            } elseif ($privateSize === $publicSize) {
                // Identical copy already on the private disk: drop the public one.
                $duplicates++;
            } else {
                // Different content under the same name: the publicly linked
                // file is the one the site has been playing, so it wins.
                $overwritten++;
            }

            if ($dryRun) {
                $this->line('→ '.$path);

                continue;
            }

            if ($privateSize === null || $privateSize !== $publicSize) {
                $stream = $source->readStream($path);
                $target->writeStream($path, $stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $source->delete($path);
        }

        [$blocked, $leftovers] = $this->handleLegacyFolders($source, $referenced, $dryRun);

        $this->newLine();
        $this->table(
            ['Перемещено', 'Дубликатов удалено', 'Перезаписано', 'Уже приватно', 'Не найдено', 'Папок закрыто', 'Осталось в public'],
            [[$moved, $duplicates, $overwritten, $alreadyPrivate, $missing, $blocked, count($leftovers)]],
        );

        foreach ($leftovers as $leftover) {
            $this->warn('Ավելորդ աուդիո հանրային պանակում՝ '.$leftover);
        }

        if ($leftovers !== [] && ! $this->option('prune')) {
            $this->line('Դատարկելու համար՝ php artisan poems:secure-audio --prune');
        }

        if ($dryRun) {
            $this->info('Չոր փորձ (--dry-run)․ ոչինչ չի փոխվել։');

            return self::SUCCESS;
        }

        $this->info($moved + $duplicates + $overwritten > 0
            ? sprintf(
                'Ձայնագրությունները հանվեցին հանրային պանակից (տեղափոխվեց %d, ջնջվեց կրկնօրինակ %d, թարմացվեց %d)։',
                $moved,
                $duplicates,
                $overwritten,
            )
            : 'Հանրային պանակում ձայնագրություններ չեն մնացել։');

        return self::SUCCESS;
    }

    /**
     * Block the web server from serving the legacy folders and report files
     * that no poem points to any more.
     *
     * @param  array<int, string>  $referenced
     * @return array{0: int, 1: array<int, string>}
     */
    protected function handleLegacyFolders(FilesystemAdapter $source, array $referenced, bool $dryRun): array
    {
        $blocked = 0;
        $leftovers = [];

        foreach (self::LEGACY_DIRECTORIES as $directory) {
            if (! $source->directoryExists($directory)) {
                continue;
            }

            foreach ($source->files($directory) as $file) {
                if (basename($file) === '.htaccess') {
                    continue;
                }

                if (in_array($file, $referenced, true)) {
                    continue;
                }

                $leftovers[] = $file;
            }

            if ($dryRun) {
                continue;
            }

            // Apache 2.4 + 2.2 syntax: the folder must never be downloadable.
            $source->put($directory.'/.htaccess', implode("\n", [
                '# Զույգ վահանակ. ձայնագրությունները հայտնի չեն որպես ֆայլ։',
                '<IfModule mod_authz_core.c>',
                '    Require all denied',
                '</IfModule>',
                '<IfModule !mod_authz_core.c>',
                '    Order allow,deny',
                '    Deny from all',
                '</IfModule>',
                '',
            ]));

            $blocked++;
        }

        if ($this->option('prune')) {
            foreach ($leftovers as $leftover) {
                $source->delete($leftover);
            }

            if ($leftovers !== []) {
                $this->info('Ջնջվեց '.count($leftovers).' ավելորդ ֆայլ հանրային պանակից։');
            }

            $leftovers = [];
        }

        return [$blocked, $leftovers];
    }
}
