<?php

namespace Tests\Feature;

use App\Models\Poem;
use Database\Seeders\SevakPoemsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Poems added from the editor's list: the text, the SEO meta and the split
 * word list must be ready right after seeding, so the only thing left in the
 * admin panel is generating the voice with its timings.
 */
class SevakPoemsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_poems_with_text_meta_and_words(): void
    {
        $this->seed(SevakPoemsSeeder::class);

        $this->assertSame(7, Poem::query()->count());

        $poem = Poem::query()->where('title', 'Հայաստան')->firstOrFail();

        $this->assertFalse($poem->is_original);
        $this->assertNull($poem->audio_path);
        $this->assertSame('Պարույր Սևակ «Հայաստան» — Բանաստեղծություն', $poem->meta_title);
        $this->assertNotEmpty($poem->meta_description);

        // Line breaks are preserved: the karaoke builds its lines from them.
        $this->assertCount(9, preg_split('/\R/u', (string) $poem->content) ?: []);

        // Word rows are ready for the timings editor and the AI alignment.
        $this->assertGreaterThan(30, $poem->words()->count());

        // The slug is generated, so the poem already has a public URL.
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', (string) $poem->slug);
    }

    public function test_running_it_twice_neither_duplicates_nor_resets_a_poem(): void
    {
        $this->seed(SevakPoemsSeeder::class);

        $poem = Poem::query()->where('title', 'Դու')->firstOrFail();
        $poem->update(['audio_path' => 'ai_audio/du.mp3']);

        $this->seed(SevakPoemsSeeder::class);

        $this->assertSame(7, Poem::query()->count());
        $this->assertSame('ai_audio/du.mp3', $poem->fresh()->audio_path);
        $this->assertGreaterThan(0, $poem->words()->count());
    }
}
