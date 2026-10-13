<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fallback route that serves "public" disk files when the public/storage
 * symlink is missing or blocked by the hosting.
 */
class MediaRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_streams_an_existing_public_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('images/cover.txt', 'PLAIN-BYTES');

        $response = $this->get('/storage/images/cover.txt');

        $response->assertOk();
        $this->assertSame('PLAIN-BYTES', $response->streamedContent());
    }

    public function test_it_never_serves_recordings(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('ai_audio/demo.mp3', 'MP3-BYTES');
        Storage::disk('public')->put('original_audio/archive.mp3', 'MP3-BYTES');
        Storage::disk('public')->put('misc/clip.wav', 'WAV-BYTES');

        // Recordings only leave the server through the signed "poems.audio" route.
        $this->get('/storage/ai_audio/demo.mp3')->assertNotFound();
        $this->get('/storage/original_audio/archive.mp3')->assertNotFound();
        $this->get('/storage/misc/clip.wav')->assertNotFound();
    }

    public function test_it_returns_404_for_a_missing_file(): void
    {
        Storage::fake('public');

        $this->get('/storage/ai_audio/missing.mp3')->assertNotFound();
    }

    public function test_it_does_not_escape_the_public_disk(): void
    {
        Storage::fake('public');

        $this->get('/storage/../.env')->assertNotFound();
    }
}
