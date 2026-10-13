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
        Storage::disk('public')->put('ai_audio/demo.mp3', 'MP3-BYTES');

        $response = $this->get('/storage/ai_audio/demo.mp3');

        $response->assertOk();
        $this->assertSame('MP3-BYTES', $response->streamedContent());
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
