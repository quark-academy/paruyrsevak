<?php

namespace App\Services;

use App\Models\Poem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Imports original (archive) recordings into the catalogue.
 *
 * Scans a folder with audio files, derives the poem title from each file name,
 * copies the file into the "public" disk (original_audio/...) and creates (or
 * links) a Poem with is_original = true.
 *
 * The lyrics are NOT hard-coded: if a text file named after the poem slug (or
 * after the audio file) exists in one of the lyrics folders, its content is
 * used as the poem text. Otherwise a short placeholder is stored and the poem
 * can be completed in the admin panel.
 */
class OriginalTracksImporter
{
    public const DISK = 'public';

    public const DIRECTORY = 'original_audio';

    /** Extensions considered audio. */
    protected const EXTENSIONS = ['mp3', 'm4a', 'aac', 'ogg', 'wav', 'opus'];

    /**
     * @param  array<string, string>  $titleOverrides  file name => custom title
     * @return array<string, mixed>
     */
    public function import(
        ?string $source = null,
        ?string $lyricsPath = null,
        bool $copyFiles = true,
        array $titleOverrides = [],
    ): array {
        $source = $this->resolveSource($source);

        if ($source === null) {
            return [
                'ok' => false,
                'message' => 'Папка с оригинальными треками не найдена. Укажите путь через --path.',
                'created' => 0,
                'linked' => 0,
                'items' => [],
            ];
        }

        $disk = Storage::disk(static::DISK);
        $disk->makeDirectory(static::DIRECTORY);

        $report = [
            'ok' => true,
            'source' => $source,
            'created' => 0,
            'linked' => 0,
            'items' => [],
        ];

        foreach ($this->audioFiles($source) as $file) {
            $title = $this->titleFor(basename($file), $titleOverrides);
            $poem = $this->findExistingPoem($title);
            $created = $poem === null;
            $slug = filled($poem?->slug) ? (string) $poem->slug : Poem::makeUniqueSlug($title);
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION)) ?: 'mp3';
            $target = static::DIRECTORY.'/'.$slug.'.'.$extension;

            if ($copyFiles) {
                $disk->put($target, File::get($file));
            }

            $lyrics = $this->lyricsFor($slug, pathinfo(basename($file), PATHINFO_FILENAME), $lyricsPath);

            $poem ??= new Poem;

            $poem->fill([
                'title' => $title,
                'slug' => $slug,
                'is_original' => true,
                'audio_path' => $target,
                'meta_title' => $poem->meta_title ?: '«'.$title.'» — Պարույր Սևակ',
                'meta_description' => $poem->meta_description ?: $this->description($title, $lyrics),
            ]);

            // Fill the text only when it is empty or still a placeholder, so
            // manual edits made in the admin panel are never overwritten.
            $existing = trim((string) $poem->content);
            $isPlaceholder = $existing === ''
                || $existing === trim($this->placeholder((string) $poem->title))
                || $existing === trim($this->placeholder($title));

            if ($isPlaceholder) {
                $poem->content = $lyrics ?? $this->placeholder($title);
            }

            $poem->save();

            if ($created) {
                $report['created']++;
            } else {
                $report['linked']++;
            }

            $report['items'][] = [
                'file' => basename($file),
                'title' => $title,
                'slug' => $slug,
                'audio_path' => $poem->audio_path,
                'lyrics' => $lyrics !== null,
                'created' => $created,
            ];
        }

        return $report;
    }

    /**
     * Existing poem for the given title, matched by title or by its base slug,
     * so that re-running the importer never creates duplicates.
     */
    protected function findExistingPoem(string $title): ?Poem
    {
        $base = Str::slug($title);

        return Poem::query()
            ->where('title', $title)
            ->when(filled($base), fn ($query) => $query->orWhere('slug', $base))
            ->orderBy('id')
            ->first();
    }

    /**
     * Folder to scan: explicit path (strict), then ./original_tracks, then storage paths.
     */
    public function resolveSource(?string $source): ?string
    {
        if (filled($source)) {
            return is_dir($source) ? rtrim($source, '/') : null;
        }

        foreach ([base_path('original_tracks'), storage_path('original_tracks'), storage_path('app/original_tracks')] as $candidate) {
            if (is_dir($candidate)) {
                return rtrim($candidate, '/');
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function audioFiles(string $directory): array
    {
        $audio = array_filter(
            File::files($directory),
            fn ($file): bool => in_array(strtolower($file->getExtension()), static::EXTENSIONS, true)
        );

        $paths = array_map(fn ($file): string => $file->getPathname(), $audio);
        sort($paths, SORT_NATURAL | SORT_FLAG_CASE);

        return $paths;
    }

    /**
     * Readable title from a file name ("  Մոր ձեռքերը .MP3" => "Մոր ձեռքերը").
     *
     * @param  array<string, string>  $overrides
     */
    public function titleFor(string $fileName, array $overrides = []): string
    {
        if (isset($overrides[$fileName])) {
            return trim($overrides[$fileName]);
        }

        $title = pathinfo($fileName, PATHINFO_FILENAME);
        $title = str_replace(['_', '–'], ' ', $title);
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);

        return $title !== '' ? $title : 'Առանց վերնագրի';
    }

    /**
     * Look for a lyrics file named after the slug or the original file name.
     *
     * Lyrics are kept as plain .txt files so no copyrighted text lives in code.
     */
    protected function lyricsFor(string $slug, string $originalName, ?string $extraPath = null): ?string
    {
        $directories = array_filter([
            $extraPath,
            storage_path('app/lyrics'),
            database_path('seeders/data/lyrics'),
        ]);

        foreach ($directories as $directory) {
            foreach ([$slug, $originalName] as $name) {
                $path = rtrim($directory, '/').'/'.$name.'.txt';

                if (is_file($path)) {
                    $content = trim((string) File::get($path));

                    if ($content !== '') {
                        return $content;
                    }
                }
            }
        }

        return null;
    }

    protected function description(string $title, ?string $lyrics): string
    {
        $excerpt = $lyrics !== null
            ? Str::limit(str_replace("\n", ' ', $lyrics), 150)
            : 'Արխիվային ձայնագրություն Պարույր Սևակի կատարմամբ՝ բառացի կարաոկե համաժամեցմամբ։';

        return '«'.$title.'» — '.$excerpt;
    }

    /**
     * Placeholder text used until a real poem text is provided.
     */
    protected function placeholder(string $title): string
    {
        return $title."\n\n".'Արխիվային ձայնագրություն Պարույր Սևակի կատարմամբ։ Տեքստը կարելի է ավելացնել ադմին վահանակում։';
    }
}
