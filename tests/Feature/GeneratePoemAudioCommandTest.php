<?php

namespace Tests\Feature;

use App\Models\Poem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Generating the recordings for the seeded poems: after
 * `php artisan db:seed --class=SevakPoemsSeeder` one command must be enough.
 */
class GeneratePoemAudioCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.elevenlabs.key', 'test-key');
        config()->set('services.elevenlabs.voice_id', 'voice-123');
        config()->set('services.elevenlabs.with_timestamps', true);

        Storage::fake('public');
    }

    public function test_it_generates_every_poem_that_has_no_recording(): void
    {
        Http::fake(['api.elevenlabs.io/*' => Http::response($this->elevenLabsAnswer('Մեկ երկու երեք'), 200)]);

        $first = Poem::factory()->create(['slug' => 'first-poem', 'content' => 'Մեկ երկու երեք']);
        $second = Poem::factory()->create(['slug' => 'second-poem', 'content' => 'Մեկ երկու երեք']);
        $already = Poem::factory()->create([
            'slug' => 'done-poem',
            'content' => 'Մեկ երկու',
            'audio_path' => 'ai_audio/done.mp3',
        ]);

        $this->artisan('poem:generate-audio', ['--all' => true])
            ->expectsOutputToContain('Done: 2 generated, 0 failed.')
            ->assertSuccessful();

        $this->assertNotNull($first->fresh()->audio_path);
        $this->assertNotNull($second->fresh()->audio_path);
        $this->assertGreaterThan(0, $first->words()->count());

        // A poem that already has a recording stays untouched.
        $this->assertSame('ai_audio/done.mp3', $already->fresh()->audio_path);
        $this->assertSame(0, $already->words()->count());
    }

    public function test_it_still_takes_a_single_poem(): void
    {
        Http::fake(['api.elevenlabs.io/*' => Http::response($this->elevenLabsAnswer('Մեկ երկու'), 200)]);

        $poem = Poem::factory()->create(['slug' => 'single-poem', 'content' => 'Մեկ երկու']);
        $other = Poem::factory()->create(['slug' => 'other-poem', 'content' => 'Մեկ երկու']);

        $this->artisan('poem:generate-audio', ['poem' => 'single-poem'])->assertSuccessful();

        $this->assertNotNull($poem->fresh()->audio_path);
        $this->assertNull($other->fresh()->audio_path);
    }

    public function test_it_asks_for_a_poem_or_the_all_option(): void
    {
        $this->artisan('poem:generate-audio')->assertFailed();
    }

    /**
     * The ElevenLabs answer: the audio plus its per character timings.
     *
     * @return array<string, mixed>
     */
    protected function elevenLabsAnswer(string $content): array
    {
        $characters = preg_split('//u', $content, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $starts = [];
        $ends = [];
        $cursor = 0.0;

        foreach ($characters as $character) {
            $starts[] = round($cursor, 2);
            $cursor += 0.1;
            $ends[] = round($cursor, 2);
        }

        return [
            'audio_base64' => base64_encode('FAKE-MP3'),
            'alignment' => [
                'characters' => $characters,
                'character_start_times_seconds' => $starts,
                'character_end_times_seconds' => $ends,
            ],
        ];
    }
}
