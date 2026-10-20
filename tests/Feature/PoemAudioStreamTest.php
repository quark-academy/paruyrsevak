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

    /**
     * Signed URL built for the browser that rendered the page, while the
     * request itself can come from another User-Agent (the iOS media stack).
     */
    private function signedUrlFor(string $userAgent, Poem $poem, int $minutes = 30): string
    {
        $hash = substr(hash_hmac('sha256', $userAgent, (string) config('app.key')), 0, 16);

        return URL::temporarySignedRoute('poems.audio', now()->addMinutes($minutes), [
            'poem' => $poem->slug,
            'u' => $hash,
        ]);
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

    public function test_ios_media_stack_requests_are_accepted(): void
    {
        $poem = $this->poemWithAudio();

        // The page is rendered for Safari, but iOS fetches the recording in a
        // separate process that introduces itself as AppleCoreMedia.
        $safari = 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1';
        $appleCoreMedia = 'AppleCoreMedia/1.0.0.23A344 (iPhone; U; CPU OS 26_0 like Mac OS X; en_us)';

        $this->get($this->signedUrlFor($safari, $poem), ['User-Agent' => $appleCoreMedia])
            ->assertOk();
    }

    public function test_android_media_stack_requests_are_accepted(): void
    {
        $poem = $this->poemWithAudio();

        $this->get($this->signedUrlFor('Mozilla/5.0 (Linux; Android 16) Chrome/140.0 Mobile Safari/537.36', $poem), [
            'User-Agent' => 'stagefright/1.2 (Linux;Android 16)',
        ])->assertOk();
    }

    public function test_a_null_origin_does_not_shadow_a_same_origin_referer(): void
    {
        $poem = $this->poemWithAudio();

        $this->get((string) $poem->audioUrl(), [
            'Origin' => 'null',
            'Referer' => config('app.url').'/'.$poem->slug,
        ])->assertOk();
    }

    public function test_a_url_bound_to_safari_is_refused_for_a_desktop_browser(): void
    {
        $poem = $this->poemWithAudio();
        $safari = 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1';

        $this->get($this->signedUrlFor($safari, $poem), [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
        ])->assertForbidden();
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
