<?php

namespace Tests\Feature;

use App\Models\Poem;
use App\Services\OriginalTracksImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OriginalTracksImporterTest extends TestCase
{
    use RefreshDatabase;

    private string $tracks;

    private string $lyrics;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->tracks = storage_path('framework/testing/tracks');
        $this->lyrics = storage_path('framework/testing/lyrics');

        File::deleteDirectory($this->tracks);
        File::deleteDirectory($this->lyrics);
        File::ensureDirectoryExists($this->tracks);
        File::ensureDirectoryExists($this->lyrics);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tracks);
        File::deleteDirectory($this->lyrics);

        parent::tearDown();
    }

    public function test_it_creates_poems_from_file_names_and_copies_audio(): void
    {
        File::put($this->tracks.'/Խոստանում եմ.MP3', 'MP3-DATA');
        File::put($this->tracks.'/Անակնկալ հայտնագործություն .MP3', 'MP3-DATA-2');

        $report = app(OriginalTracksImporter::class)->import($this->tracks, $this->lyrics);

        $this->assertTrue($report['ok']);
        $this->assertSame(2, $report['created']);

        $poem = Poem::query()->where('title', 'Խոստանում եմ')->firstOrFail();
        $this->assertTrue($poem->is_original);
        $this->assertStringStartsWith('original_audio/', (string) $poem->audio_path);
        $this->assertStringEndsWith('.mp3', (string) $poem->audio_path);
        Storage::disk('public')->assertExists($poem->audio_path);

        // Trailing space in the file name is trimmed.
        $this->assertDatabaseHas('poems', ['title' => 'Անակնկալ հայտնագործություն']);
    }

    public function test_it_uses_lyrics_file_content_when_available(): void
    {
        File::put($this->tracks.'/Զղջում եմ.MP3', 'MP3');

        $report = app(OriginalTracksImporter::class)->import($this->tracks, $this->lyrics);
        $slug = $report['items'][0]['slug'];

        File::put($this->lyrics.'/'.$slug.'.txt', "Տող մեկ\nՏող երկու");

        app(OriginalTracksImporter::class)->import($this->tracks, $this->lyrics);

        $poem = Poem::query()->where('slug', $slug)->firstOrFail();
        $this->assertSame("Տող մեկ\nՏող երկու", $poem->content);
    }

    public function test_it_never_overwrites_a_manually_edited_text(): void
    {
        File::put($this->tracks.'/Ապրել, ապրել.MP3', 'MP3');

        $report = app(OriginalTracksImporter::class)->import($this->tracks, $this->lyrics);
        $slug = $report['items'][0]['slug'];

        // A placeholder (created above) is replaced as soon as real lyrics exist.
        File::put($this->lyrics.'/'.$slug.'.txt', "Տող մեկ\nՏող երկու");

        app(OriginalTracksImporter::class)->import($this->tracks, $this->lyrics);

        $poem = Poem::query()->firstOrFail();
        $this->assertSame("Տող մեկ\nՏող երկու", $poem->content);

        // A manual edit is preserved on further runs.
        File::put($this->lyrics.'/'.$slug.'.txt', 'ԱՅԼ ՏԵՔՍՏ');
        $poem->update(['content' => 'ՁԵՌՔՈՎ ԽՄԲԱԳՐՎԱԾ']);

        app(OriginalTracksImporter::class)->import($this->tracks, $this->lyrics);

        $this->assertSame('ՁԵՌՔՈՎ ԽՄԲԱԳՐՎԱԾ', $poem->fresh()->content);
        $this->assertSame(1, Poem::query()->count());
    }

    public function test_title_overrides_are_applied(): void
    {
        File::put($this->tracks.'/Տարօրինակ.MP3', 'MP3');

        app(OriginalTracksImporter::class)->import(
            source: $this->tracks,
            lyricsPath: $this->lyrics,
            titleOverrides: ['Տարօրինակ.MP3' => 'Լրիվ վերնագիրը'],
        );

        $this->assertDatabaseHas('poems', ['title' => 'Լրիվ վերնագիրը']);
    }

    public function test_it_reports_a_missing_folder(): void
    {
        $report = app(OriginalTracksImporter::class)->import('/nope/does-not-exist');

        $this->assertFalse($report['ok']);
        $this->assertSame(0, $report['created']);
    }

    public function test_real_archive_file_names_produce_sane_titles_and_unique_slugs(): void
    {
        $importer = app(OriginalTracksImporter::class);

        $files = [
            'Խոստանում եմ.MP3' => 'Խոստանում եմ',
            'Անակնկալ հայտնագործություն .MP3' => 'Անակնկալ հայտնագործություն',
            'Ապրել, ապրել.MP3' => 'Ապրել, ապրել',
            'Մարդ կա, մարդ էլ.MP3' => 'Մարդ կա, մարդ էլ',
        ];

        $slugs = [];

        foreach ($files as $fileName => $title) {
            $this->assertSame($title, $importer->titleFor($fileName));

            $slug = Poem::makeUniqueSlug($title);

            // Slugs must be non-empty ASCII so they are safe in URLs and file names.
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);

            Poem::query()->create([
                'title' => $title,
                'slug' => $slug,
                'content' => 'ստուգող տեքստ',
                'is_original' => true,
            ]);

            $slugs[] = $slug;
        }

        $this->assertSame($slugs, array_values(array_unique($slugs)));
    }
}
