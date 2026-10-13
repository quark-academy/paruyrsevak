<?php

namespace Tests\Feature;

use App\Models\Poem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * poems:secure-audio moves recordings off the publicly linked disk.
 */
class SecureAudioFilesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('audio');
    }

    public function test_it_moves_recordings_out_of_the_public_disk(): void
    {
        Storage::disk('public')->put('original_audio/archive.mp3', 'ARCHIVE-BYTES');
        Storage::disk('public')->put('ai_audio/generated.mp3', 'AI-BYTES');
        Storage::disk('public')->put('images/cover.jpg', 'IMG-BYTES');

        $archive = Poem::factory()->create([
            'slug' => 'archive-poem',
            'audio_path' => 'original_audio/archive.mp3',
            'is_original' => true,
        ]);
        $generated = Poem::factory()->create([
            'slug' => 'generated-poem',
            'audio_path' => 'ai_audio/generated.mp3',
        ]);

        $this->artisan('poems:secure-audio')->assertSuccessful();

        Storage::disk('audio')->assertExists('original_audio/archive.mp3');
        Storage::disk('audio')->assertExists('ai_audio/generated.mp3');
        Storage::disk('public')->assertMissing('original_audio/archive.mp3');
        Storage::disk('public')->assertMissing('ai_audio/generated.mp3');

        // Unrelated public files (e.g. covers) are not touched.
        Storage::disk('public')->assertExists('images/cover.jpg');

        // Both legacy folders are blocked for the web server.
        foreach (['original_audio', 'ai_audio'] as $directory) {
            $this->assertStringContainsString(
                'Require all denied',
                (string) Storage::disk('public')->get($directory.'/.htaccess'),
            );
        }

        // Playback keeps working: the files are found on the private disk.
        $this->assertTrue($archive->fresh()->hasAudio());
        $this->assertTrue($generated->fresh()->hasAudio());
        $this->assertSame('ARCHIVE-BYTES', Storage::disk('audio')->get('original_audio/archive.mp3'));
    }

    public function test_it_is_idempotent(): void
    {
        Storage::disk('audio')->put('original_audio/archive.mp3', 'ARCHIVE-BYTES');

        Poem::factory()->create(['slug' => 'archive-poem', 'audio_path' => 'original_audio/archive.mp3']);

        $this->artisan('poems:secure-audio')->assertSuccessful();

        Storage::disk('audio')->assertExists('original_audio/archive.mp3');
        Storage::disk('public')->assertMissing('original_audio/archive.mp3');
    }

    public function test_it_removes_duplicates_left_in_the_public_disk(): void
    {
        Storage::disk('audio')->put('original_audio/archive.mp3', 'SAME-BYTES');
        Storage::disk('public')->put('original_audio/archive.mp3', 'SAME-BYTES');

        Poem::factory()->create(['slug' => 'archive-poem', 'audio_path' => 'original_audio/archive.mp3']);

        $this->artisan('poems:secure-audio')->assertSuccessful();

        Storage::disk('public')->assertMissing('original_audio/archive.mp3');
        Storage::disk('audio')->assertExists('original_audio/archive.mp3');
    }

    public function test_different_copies_follow_the_publicly_linked_file(): void
    {
        Storage::disk('audio')->put('original_audio/archive.mp3', 'PRIVATE-BYTES');
        Storage::disk('public')->put('original_audio/archive.mp3', 'PUBLIC-BYTES-DIFFERENT');

        Poem::factory()->create(['slug' => 'archive-poem', 'audio_path' => 'original_audio/archive.mp3']);

        $this->artisan('poems:secure-audio')->assertSuccessful();

        // The file the site was playing wins, the public folder is emptied.
        Storage::disk('audio')->assertExists('original_audio/archive.mp3');
        $this->assertSame('PUBLIC-BYTES-DIFFERENT', Storage::disk('audio')->get('original_audio/archive.mp3'));
        Storage::disk('public')->assertMissing('original_audio/archive.mp3');
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        Storage::disk('public')->put('original_audio/archive.mp3', 'ARCHIVE-BYTES');

        Poem::factory()->create(['slug' => 'archive-poem', 'audio_path' => 'original_audio/archive.mp3']);

        $this->artisan('poems:secure-audio --dry-run')->assertSuccessful();

        Storage::disk('public')->assertExists('original_audio/archive.mp3');
        Storage::disk('audio')->assertMissing('original_audio/archive.mp3');
        Storage::disk('public')->assertMissing('original_audio/.htaccess');
    }

    public function test_prune_removes_orphaned_recordings(): void
    {
        Storage::disk('public')->put('original_audio/archive.mp3', 'ARCHIVE-BYTES');
        Storage::disk('public')->put('original_audio/orphan.mp3', 'ORPHAN-BYTES');

        Poem::factory()->create(['slug' => 'archive-poem', 'audio_path' => 'original_audio/archive.mp3']);

        $this->artisan('poems:secure-audio --prune')->assertSuccessful();

        Storage::disk('audio')->assertExists('original_audio/archive.mp3');
        Storage::disk('public')->assertMissing('original_audio/orphan.mp3');
        Storage::disk('public')->assertMissing('original_audio/archive.mp3');
    }
}
