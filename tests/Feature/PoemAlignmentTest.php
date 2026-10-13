<?php

namespace Tests\Feature;

use App\Models\Poem;
use App\Models\User;
use App\Support\PoemText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Karaoke words built from the poem's own recording + protection of archive
 * recordings against implicit replacement by AI renders.
 */
class PoemAlignmentTest extends TestCase
{
    use RefreshDatabase;

    /** A self written sentence used as a "real poem text" in assertions. */
    private const REAL_TEXT = 'Մի փոքրիկ տղա գնում էր տուն, և ճանապարհին տեսավ մի ծերունու։ Այդ ծերունին նրան պատմեց հին պատմություններ։';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        config()->set('services.elevenlabs.key', 'test-key');
        config()->set('services.elevenlabs.voice_id', 'voice-123');
        config()->set('services.elevenlabs.base_url', 'https://api.elevenlabs.io/v1');
    }

    public function test_it_aligns_words_from_the_recording_using_the_poem_text(): void
    {
        Http::fake([
            '*/forced-alignment' => Http::response([
                'words' => [
                    ['text' => 'Մի', 'start' => 0.0, 'end' => 0.4, 'loss' => 0.02],
                    ['text' => 'փոքրիկ', 'start' => 0.4, 'end' => 1.1, 'loss' => 0.05],
                    ['text' => 'տղա', 'start' => 1.1, 'end' => 1.6, 'loss' => 0.03],
                ],
                'loss' => 0.03,
            ], 200),
        ]);

        $poem = $this->archivePoem();

        $this->actingAs($this->admin())
            ->post(route('admin.poems.audio.transcribe', $poem))
            ->assertRedirect()
            ->assertSessionHas('status');

        $words = $poem->words()->get();

        $this->assertCount(3, $words);
        $this->assertSame('փոքրիկ', $words[1]->word);
        $this->assertSame(1.1, (float) $words[2]->start_time);

        // The known text is never overwritten by an alignment.
        $this->assertSame(self::REAL_TEXT, $poem->fresh()->content);

        // Contract check: multipart with the audio file + the poem text.
        Http::assertSent(function ($request): bool {
            $fields = collect($request->data())->pluck('contents', 'name')->all();

            return str_contains($request->url(), '/forced-alignment')
                && $request->hasFile('file', null, 'test-poem.mp3')
                && ($fields['text'] ?? null) === self::REAL_TEXT;
        });
    }

    public function test_it_transcribes_the_recording_when_the_poem_has_no_text(): void
    {
        Http::fake([
            '*/speech-to-text' => Http::response([
                'text' => 'Բարի լույս, այսօր արևոտ օր է',
                'language_code' => 'hy',
                'words' => [
                    ['text' => 'Բարի', 'start' => 0.0, 'end' => 0.5, 'type' => 'word'],
                    ['text' => ' ', 'start' => 0.5, 'end' => 0.6, 'type' => 'spacing'],
                    ['text' => 'լույս', 'start' => 0.6, 'end' => 1.1, 'type' => 'word'],
                    ['text' => '(ծիծաղ)', 'start' => 1.1, 'end' => 1.4, 'type' => 'audio_event'],
                ],
            ], 200),
        ]);

        $poem = $this->archivePoem(placeholder: true);

        $this->actingAs($this->admin())
            ->post(route('admin.poems.audio.transcribe', $poem))
            ->assertRedirect()
            ->assertSessionHas('status');

        // Spacing / audio_event entries are dropped, words keep their timing.
        $words = $poem->words()->get();
        $this->assertSame(['Բարի', 'լույս'], $words->pluck('word')->all());
        $this->assertSame(1.1, (float) $words[1]->end_time);

        // The transcript fills the empty text automatically.
        $this->assertSame('Բարի լույս, այսօր արևոտ օր է', $poem->fresh()->content);

        // Contract check: word level timestamps are requested.
        Http::assertSent(function ($request): bool {
            $fields = collect($request->data())->pluck('contents', 'name')->all();

            return str_contains($request->url(), '/speech-to-text')
                && $request->hasFile('file', null, 'test-poem.mp3')
                && ($fields['timestamps_granularity'] ?? null) === 'word';
        });
    }

    public function test_ai_generation_does_not_replace_the_archive_recording(): void
    {
        Http::fake();

        $poem = $this->archivePoem();

        $this->actingAs($this->admin())
            ->post(route('admin.poems.audio.generate', $poem))
            ->assertRedirect(route('admin.poems.show', $poem))
            ->assertSessionHasErrors('audio');

        $fresh = $poem->fresh();

        $this->assertTrue($fresh->is_original);
        $this->assertSame('original_audio/test-poem.mp3', $fresh->audio_path);

        // No quota was spent: the API was not called at all.
        Http::assertNothingSent();
    }

    public function test_ai_generation_replaces_the_recording_when_explicitly_asked(): void
    {
        Http::fake([
            '*/text-to-speech/*' => Http::response([
                'audio_base64' => base64_encode('FAKE-MP3-BYTES'),
                'alignment' => [
                    'characters' => ['Մ', 'ի'],
                    'character_start_times_seconds' => [0.0, 0.2],
                    'character_end_times_seconds' => [0.2, 0.5],
                ],
            ], 200),
        ]);

        $poem = $this->archivePoem();

        $this->actingAs($this->admin())
            ->post(route('admin.poems.audio.generate', $poem), ['replace_original' => '1'])
            ->assertRedirect(route('admin.poems.show', $poem))
            ->assertSessionHasNoErrors();

        $fresh = $poem->fresh();

        $this->assertFalse($fresh->is_original);
        $this->assertStringStartsWith('ai_audio/', (string) $fresh->audio_path);
        Storage::disk('public')->assertExists($fresh->audio_path);
    }

    public function test_ai_generation_requires_a_real_text(): void
    {
        Http::fake();

        $poem = $this->archivePoem(placeholder: true);

        $this->actingAs($this->admin())
            ->post(route('admin.poems.audio.generate', $poem), ['replace_original' => '1'])
            ->assertSessionHasErrors('audio');

        Http::assertNothingSent();
    }

    public function test_alignment_from_recording_requires_audio(): void
    {
        Http::fake();

        $poem = Poem::factory()->create([
            'slug' => 'no-audio',
            'content' => self::REAL_TEXT,
            'audio_path' => null,
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.poems.audio.transcribe', $poem))
            ->assertSessionHasErrors('align');

        Http::assertNothingSent();
        $this->assertSame(0, $poem->words()->count());
    }

    public function test_the_command_builds_words_for_every_recording(): void
    {
        Http::fake([
            '*/forced-alignment' => Http::response([
                'words' => [
                    ['text' => 'Մի', 'start' => 0.0, 'end' => 0.4],
                    ['text' => 'փոքրիկ', 'start' => 0.4, 'end' => 1.1],
                ],
                'loss' => 0.04,
            ], 200),
        ]);

        $poem = $this->archivePoem();

        $this->artisan('poems:align-audio --all')->assertSuccessful();

        $this->assertSame(2, $poem->words()->count());
    }

    public function test_the_command_requires_a_slug_or_the_all_flag(): void
    {
        $this->artisan('poems:align-audio')->assertFailed();
    }

    public function test_the_admin_pages_render_for_an_archive_recording(): void
    {
        $admin = $this->admin();
        $poem = $this->archivePoem();

        $this->actingAs($admin)
            ->get(route('admin.poems.show', $poem))
            ->assertOk()
            ->assertSee('Սա արխիվային ձայնագրություն է', false)
            ->assertSee('Փոխարինել բնօրինակ', false)
            ->assertSee('Համաժամեցնել ձայնագրությունից', false);

        $this->actingAs($admin)
            ->get(route('admin.poems.timings', $poem))
            ->assertOk()
            ->assertSee('Համաժամեցնել ձայնագրությունից', false);
    }

    public function test_a_placeholder_text_does_not_create_words(): void
    {
        $poem = $this->archivePoem(placeholder: true);

        // Opening the timings editor used to split the placeholder into words.
        $this->actingAs($this->admin())
            ->get(route('admin.poems.timings', $poem))
            ->assertOk();

        $this->assertSame(0, $poem->words()->count());
    }

    public function test_force_and_sync_words_do_not_touch_an_archive_recording(): void
    {
        Http::fake();

        $poem = $this->archivePoem();
        $poem->words()->create(['word' => 'Մի', 'start_time' => 0.0, 'end_time' => 1.0, 'word_order' => 0]);

        // Both AI checkboxes ticked, but the archive recording is not allowed
        // to be replaced: the request must be refused as a whole.
        $this->actingAs($this->admin())
            ->post(route('admin.poems.audio.generate', $poem), ['force' => '1', 'sync_words' => '1'])
            ->assertRedirect(route('admin.poems.show', $poem))
            ->assertSessionHasErrors('audio');

        Http::assertNothingSent();

        $fresh = $poem->fresh();
        $this->assertTrue($fresh->is_original);
        $this->assertSame('original_audio/test-poem.mp3', $fresh->audio_path);
        $this->assertSame(1, $fresh->words()->count());
        $this->assertSame('Մի', $fresh->words()->first()->word);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /**
     * Poem with an archive recording; the text is either the real one or the
     * importer's placeholder.
     */
    private function archivePoem(bool $placeholder = false): Poem
    {
        Storage::disk('public')->put('original_audio/test-poem.mp3', 'MP3-DATA');

        return Poem::factory()->create([
            'title' => 'Թեստային բանաստեղծություն',
            'slug' => 'test-poem',
            'content' => $placeholder
                ? 'Թեստային բանաստեղծություն'."\n\n".PoemText::PLACEHOLDER_MARKER.'։'
                : self::REAL_TEXT,
            'audio_path' => 'original_audio/test-poem.mp3',
            'is_original' => true,
        ]);
    }
}
