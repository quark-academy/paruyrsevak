<?php

namespace Tests\Feature;

use App\Models\Poem;
use App\Models\PoemWord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoemModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_is_generated_from_an_armenian_title(): void
    {
        $poem = Poem::create([
            'title' => 'Սևակ Պարույր',
            'content' => 'Առաջին տողը',
        ]);

        $this->assertNotEmpty($poem->slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $poem->slug);
    }

    public function test_slug_is_unique_for_duplicate_titles(): void
    {
        $first = Poem::create(['title' => 'Միևնույն վերնագիրը', 'content' => 'Ա']);
        $second = Poem::create(['title' => 'Միևնույն վերնագիրը', 'content' => 'Բ']);

        $this->assertNotSame($first->slug, $second->slug);
    }

    public function test_word_relation_is_ordered_and_cascades_on_delete(): void
    {
        $poem = Poem::factory()->create();

        PoemWord::factory()->create(['poem_id' => $poem->id, 'word' => 'երկրորդ', 'word_order' => 2]);
        PoemWord::factory()->create(['poem_id' => $poem->id, 'word' => 'առաջին', 'word_order' => 1]);

        $this->assertSame(['առաջին', 'երկրորդ'], $poem->words()->pluck('word')->all());

        $poem->delete();

        $this->assertDatabaseCount('poem_words', 0);
    }

    public function test_audio_helpers_return_false_without_file(): void
    {
        $poem = Poem::factory()->create(['audio_path' => null]);

        $this->assertFalse($poem->hasAudio());
        $this->assertNull($poem->audioUrl());
        $this->assertSame('ԱԻ ձայն', $poem->voiceSourceLabel());
    }
}
