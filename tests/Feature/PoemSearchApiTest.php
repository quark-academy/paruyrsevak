<?php

namespace Tests\Feature;

use App\Models\Poem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The search behind the header icon: a few matches, the URL of the same search
 * in the catalogue, and nothing else to render.
 */
class PoemSearchApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('poems.canonical_base', 'https://subdomain.domain.am');
    }

    public function test_it_answers_the_modal_with_matches_and_the_catalogue_url(): void
    {
        $match = Poem::factory()->create(['title' => 'Ձմեռային գիշեր', 'slug' => 'winter-poem']);
        Poem::factory()->create(['title' => 'Գարնանային', 'slug' => 'spring-poem']);

        $response = $this->getJson('/api/search?q='.urlencode('Ձմեռ'))->assertOk();

        $response->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.slug', 'winter-poem')
            ->assertJsonPath('results.0.title', 'Ձմեռային գիշեր')
            ->assertJsonPath('results.0.playerUrl', route('api.poems.player', ['poem' => $match]))
            ->assertJsonPath('allUrl', route('poems.index', ['q' => 'Ձմեռ']));

        // The modal links straight to the poem (the short URL when it is on).
        $this->assertStringContainsString('winter-poem', (string) $response->json('results.0.url'));
    }

    public function test_it_finds_a_poem_by_its_text_as_well(): void
    {
        Poem::factory()->create([
            'title' => 'Անուն',
            'slug' => 'text-poem',
            'content' => 'Նորից չեն սիրում',
        ]);

        $this->getJson('/api/search?q='.urlencode('չեն սիրում'))
            ->assertOk()
            ->assertJsonPath('results.0.slug', 'text-poem');
    }

    public function test_a_too_short_query_asks_for_more_letters(): void
    {
        Poem::factory()->create(['title' => 'Անուն', 'slug' => 'short-poem']);

        $this->getJson('/api/search?q=Ա')
            ->assertOk()
            ->assertJsonCount(0, 'results')
            ->assertJsonPath('allUrl', route('poems.index'))
            ->assertJsonPath('message', 'Գրիր առնվազն երկու տառ։');
    }
}
