<?php

namespace Tests\Feature;

use App\Models\Poem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Recordings are streamed through a signed, expiring route: the files live
 * outside the web root and must not be downloadable as plain files.
 */
class PoemAudioStreamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('audio');
        Storage::fake('public');
    }

    private function poemWithAudio(string $path = 'original_audio/test-poem.mp3'): Poem
    {
        Storage::disk('audio')->put($path, 'MP3-BYTES');

        return Poem::factory()->create([
            'slug' => 'audio-poem',
            'audio_path' => $path,
            'is_original' => true,
        ]);
    }

    private function contentOf(TestResponse $response): string
    {
        return (string) file_get_contents($response->baseResponse->getFile()->getPathname());
    }

    public function test_the_recording_is_streamed_through_a_signed_url(): void
    {
        $poem = $this->poemWithAudio();

        $url = (string) $poem->audioUrl();

        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('u=', $url);

        $response = $this->get($url)->assertOk();

        $response->assertHeader('Content-Type', 'audio/mpeg');
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('MP3-BYTES', $this->contentOf($response));
    }

    public function test_seeking_works_through_range_requests(): void
    {
        $poem = $this->poemWithAudio();

        $response = $this->get((string) $poem->audioUrl(), ['Range' => 'bytes=0-3']);

        $response->assertStatus(206);
        $response->assertHeader('Content-Range', 'bytes 0-3/9');
        $response->assertHeader('Content-Length', '4');
        $response->assertHeader('Accept-Ranges', 'bytes');
    }

    public function test_a_url_without_a_signature_is_refused(): void
    {
        $poem = $this->poemWithAudio();

        $this->get('/audio/'.$poem->slug)->assertForbidden();
    }

    public function test_an_expired_url_is_refused(): void
    {
        $poem = $this->poemWithAudio();

        $expired = URL::temporarySignedRoute(
            'poems.audio',
            now()->subMinute(),
            ['poem' => $poem->slug, 'u' => Poem::visitorHash()],
        );

        $this->get($expired)->assertForbidden();
    }

    public function test_a_url_bound_to_another_browser_is_refused(): void
    {
        $poem = $this->poemWithAudio();

        $stolen = URL::temporarySignedRoute(
            'poems.audio',
            now()->addMinutes(30),
            ['poem' => $poem->slug, 'u' => 'deadbeefdeadbeef'],
        );

        $this->get($stolen)->assertForbidden();
    }

    public function test_hotlinking_from_another_site_is_refused(): void
    {
        $poem = $this->poemWithAudio();

        $this->get((string) $poem->audioUrl(), ['Referer' => 'https://evil.example/poem'])
            ->assertForbidden();
    }

    public function test_the_same_origin_referer_is_allowed(): void
    {
        $poem = $this->poemWithAudio();

        $this->get((string) $poem->audioUrl(), ['Referer' => config('app.url').'/'.$poem->slug])
            ->assertOk();
    }

    public function test_the_public_disk_never_serves_a_recording(): void
    {
        $poem = $this->poemWithAudio();
        Storage::disk('public')->put('original_audio/legacy.mp3', 'MP3-BYTES');
        Storage::disk('public')->put('ai_audio/generated.mp3', 'MP3-BYTES');

        $this->get('/storage/original_audio/legacy.mp3')->assertNotFound();
        $this->get('/storage/ai_audio/generated.mp3')->assertNotFound();
        $this->get('/storage/original_audio/'.$poem->slug.'.mp3')->assertNotFound();
    }

    public function test_the_player_payload_carries_a_signed_url(): void
    {
        $poem = $this->poemWithAudio();

        $this->getJson('/api/poems/'.$poem->slug)
            ->assertOk()
            ->assertJsonPath('status', 'ready');

        $this->assertStringContainsString('signature=', (string) $poem->fresh()->audioUrl());
    }

    public function test_a_legacy_file_on_the_public_disk_still_plays(): void
    {
        Storage::disk('public')->put('original_audio/legacy.mp3', 'LEGACY-BYTES');

        $poem = Poem::factory()->create([
            'slug' => 'legacy-poem',
            'audio_path' => 'original_audio/legacy.mp3',
        ]);

        $response = $this->get((string) $poem->fresh()->audioUrl())->assertOk();

        $this->assertSame('LEGACY-BYTES', $this->contentOf($response));
    }
}
