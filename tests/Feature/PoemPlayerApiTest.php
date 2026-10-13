<?php

namespace Tests\Feature;

use App\Models\Poem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PoemPlayerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('poems.canonical_base', 'https://subdomain.domain.am');
        Storage::fake('public');
    }

    public function test_player_endpoint_returns_payload_with_missing_status(): void
    {
        $poem = Poem::factory()->create(['slug' => 'api-poem', 'content' => 'Մեկ երկու']);

        $this->getJson('/api/poems/api-poem')
            ->assertOk()
            ->assertJsonPath('slug', 'api-poem')
            ->assertJsonPath('status', 'missing')
            ->assertJsonPath('audioUrl', null)
            ->assertJsonPath('canonical', 'https://subdomain.domain.am/api-poem');
    }

    public function test_player_endpoint_reports_ready_when_audio_exists(): void
    {
        Storage::disk('public')->put('ai_audio/ready.mp3', 'audio-bytes');

        $poem = Poem::factory()->create([
            'slug' => 'ready-poem',
            'audio_path' => 'ai_audio/ready.mp3',
            'is_original' => false,
        ]);

        $this->getJson('/api/poems/ready-poem')
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('isOriginal', false);

        $this->assertNotNull($poem->fresh()->audioUrl());
    }

    public function test_generation_is_forbidden_when_public_generation_is_disabled(): void
    {
        config()->set('poems.public_generation', false);
        Poem::factory()->create(['slug' => 'locked-poem']);

        $this->postJson('/api/poems/locked-poem/generate')
            ->assertForbidden()
            ->assertJsonPath('status', 'disabled');
    }

    public function test_generation_returns_422_when_service_is_not_configured(): void
    {
        config()->set('poems.public_generation', true);
        config()->set('services.elevenlabs.key', null);
        config()->set('services.elevenlabs.voice_id', null);

        Poem::factory()->create(['slug' => 'unconfigured-poem', 'content' => 'Հելո']);

        $this->postJson('/api/poems/unconfigured-poem/generate')
            ->assertStatus(422)
            ->assertJsonPath('status', 'unconfigured');
    }

    public function test_generation_creates_audio_and_word_timings(): void
    {
        config()->set('poems.public_generation', true);
        config()->set('services.elevenlabs.key', 'test-key');
        config()->set('services.elevenlabs.voice_id', 'voice-123');
        config()->set('services.elevenlabs.with_timestamps', true);

        $content = 'Հելո Աշխարհ';
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
                'audio_base64' => base64_encode('FAKE-MP3'),
                'alignment' => [
                    'characters' => $characters,
                    'character_start_times_seconds' => $starts,
                    'character_end_times_seconds' => $ends,
                ],
            ], 200),
        ]);

        $poem = Poem::factory()->create(['slug' => 'generated-poem', 'content' => $content]);

        $response = $this->postJson('/api/poems/generated-poem/generate')->assertOk();

        $response->assertJsonPath('status', 'ready');
        $this->assertNotNull($poem->fresh()->audio_path);
        $this->assertSame(2, $poem->words()->count());
        $this->assertCount(1, $response->json('lines'));
    }
}
