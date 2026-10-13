<?php

namespace Tests\Feature;

use App\Models\Poem;
use App\Services\ElevenLabsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ElevenLabsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lazy_generation_caches_audio_and_maps_word_timings(): void
    {
        config()->set('services.elevenlabs.key', 'test-key');
        config()->set('services.elevenlabs.voice_id', 'voice-123');
        config()->set('services.elevenlabs.with_timestamps', true);

        Storage::fake('public');

        $content = 'Հելո Աշխարհ';
        $audio = 'FAKE-MP3-BYTES';

        $characters = preg_split('//u', $content, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $starts = [];
        $ends = [];
        $cursor = 0.0;

        foreach ($characters as $character) {
            $starts[] = round($cursor, 2);
            $cursor += 0.1;
            $ends[] = round($cursor, 2);
        }

        Http::fake([
            'api.elevenlabs.io/*' => Http::response([
                'audio_base64' => base64_encode($audio),
                'alignment' => [
                    'characters' => $characters,
                    'character_start_times_seconds' => $starts,
                    'character_end_times_seconds' => $ends,
                ],
            ], 200),
        ]);

        $poem = Poem::factory()->create([
            'content' => $content,
            'slug' => 'helo-ashkharh',
        ]);

        $service = app(ElevenLabsService::class);

        $first = $service->generateAudioForPoem($poem);

        $this->assertFalse($first['cached']);
        $this->assertCount(2, $first['words']);
        $this->assertSame('Հելո', $first['words'][0]['word']);
        $this->assertSame('Աշխարհ', $first['words'][1]['word']);
        Storage::disk('public')->assertExists($first['path']);
        $this->assertSame($audio, Storage::disk('public')->get($first['path']));
        Http::assertSentCount(1);

        $second = $service->generateAudioForPoem($poem);

        $this->assertTrue($second['cached']);
        $this->assertCount(2, $second['words']);
        Http::assertSentCount(1);
    }

    public function test_it_distributes_words_proportionally_when_no_alignment(): void
    {
        $service = app(ElevenLabsService::class);

        $words = $service->distributeWords('շատ երկար բառ', 3.0);

        $this->assertCount(3, $words);
        $this->assertSame('շատ', $words[0]['word']);
        $this->assertSame(0.0, $words[0]['start']);
        $this->assertEqualsWithDelta(3.0, $words[2]['end'], 0.001);
    }

    public function test_language_code_is_not_sent_for_the_multilingual_model(): void
    {
        config()->set('services.elevenlabs.key', 'test-key');
        config()->set('services.elevenlabs.voice_id', 'voice-1');
        config()->set('services.elevenlabs.model', 'eleven_multilingual_v2');
        config()->set('services.elevenlabs.language', 'hy');

        Http::fake(['api.elevenlabs.io/*' => Http::response(['audio_base64' => base64_encode('audio')], 200)]);

        app(ElevenLabsService::class)->synthesize('Սևակ');

        Http::assertSent(fn ($request): bool => $request['model_id'] === 'eleven_multilingual_v2'
            && ! array_key_exists('language_code', $request->data()));
    }

    public function test_language_code_is_sent_for_turbo_models(): void
    {
        config()->set('services.elevenlabs.key', 'test-key');
        config()->set('services.elevenlabs.voice_id', 'voice-1');
        config()->set('services.elevenlabs.model', 'eleven_turbo_v2_5');
        config()->set('services.elevenlabs.language', 'hy');

        Http::fake(['api.elevenlabs.io/*' => Http::response(['audio_base64' => base64_encode('audio')], 200)]);

        app(ElevenLabsService::class)->synthesize('Սևակ');

        Http::assertSent(fn ($request): bool => ($request->data()['language_code'] ?? null) === 'hy');
    }

    public function test_it_is_not_configured_without_credentials(): void
    {
        config()->set('services.elevenlabs.key', null);
        config()->set('services.elevenlabs.voice_id', null);

        $this->assertFalse((new ElevenLabsService(null, null, null))->isConfigured());
    }
}
