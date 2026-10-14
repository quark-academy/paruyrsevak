<?php

namespace Tests\Feature;

use App\Models\Poem;
use App\Services\PoemPlayerPayload;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shuffle mode of the player: GET /api/poems/random.
 */
class RandomPoemApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('poems.canonical_base', 'https://subdomain.domain.am');
    }

    public function test_a_random_poem_is_returned(): void
    {
        $poem = Poem::factory()->create(['slug' => 'only-poem']);

        $this->getJson('/api/poems/random')
            ->assertOk()
            ->assertJsonPath('slug', 'only-poem')
            ->assertJsonPath('url', Seo::poemUrl($poem))
            // Shuffle also swaps in place, so it needs the payload URL too.
            ->assertJsonPath('playerUrl', route('api.poems.player', ['poem' => $poem]));
    }

    public function test_the_poem_being_listened_to_is_excluded(): void
    {
        Poem::factory()->create(['slug' => 'current-poem']);
        $other = Poem::factory()->create(['slug' => 'other-poem']);

        $this->getJson('/api/poems/random?exclude=current-poem')
            ->assertOk()
            ->assertJsonPath('slug', $other->slug);
    }

    public function test_there_is_nothing_to_shuffle_to_in_a_single_poem_catalogue(): void
    {
        Poem::factory()->create(['slug' => 'lonely-poem']);

        $this->getJson('/api/poems/random?exclude=lonely-poem')
            ->assertNotFound()
            ->assertJsonPath('url', null);
    }

    public function test_the_player_is_told_where_to_ask_for_a_random_poem(): void
    {
        Poem::factory()->create(['slug' => 'shuffle-poem']);

        $this->getJson('/api/poems/shuffle-poem')
            ->assertOk()
            ->assertJsonPath('randomUrl', route('api.poems.random'))
            ->assertJsonPath('prefs.autoplay', (bool) config('poems.player.autoplay'));
    }

    public function test_a_malformed_exclude_parameter_does_not_break_shuffling(): void
    {
        $poem = Poem::factory()->create(['slug' => 'stable-poem']);

        // "?exclude[]=x" must not blow up with an array-to-string error.
        $this->getJson('/api/poems/random?exclude[]=x')
            ->assertOk()
            ->assertJsonPath('slug', $poem->slug);
    }

    public function test_the_payload_is_safe_for_a_poem_that_is_not_stored_yet(): void
    {
        $poem = new Poem(['title' => 'Նոր գործ', 'content' => 'Մեկ երկու']);

        $payload = app(PoemPlayerPayload::class)->build($poem);

        // Comparing an operator with a null key used to throw
        // "Illegal operator and value combination".
        $this->assertNull($payload['prev']);
        $this->assertNull($payload['next']);
        $this->assertNull($payload['playerUrl']);
        $this->assertNull($payload['playsUrl']);
        $this->assertSame(route('home'), $payload['readingUrl']);
    }
}
