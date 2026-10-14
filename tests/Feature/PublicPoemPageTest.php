<?php

namespace Tests\Feature;

use App\Http\Controllers\PoemController;
use App\Models\Poem;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\TestCase;

class PublicPoemPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_lists_poems(): void
    {
        $poem = Poem::factory()->create(['title' => 'Իմ ստեղծագործությունը']);

        $this->get('/')
            ->assertOk()
            ->assertSee($poem->title);
    }

    public function test_poem_page_is_reachable_by_slug(): void
    {
        $poem = Poem::factory()->create();

        $this->get(route('poems.show', $poem))
            ->assertOk()
            ->assertSee($poem->title);
    }

    public function test_sitemap_is_valid_xml(): void
    {
        $poem = Poem::factory()->create(['slug' => 'sitemap-poem']);

        $response = $this->get('/sitemap.xml')->assertOk();

        $content = (string) $response->getContent();

        // The XML declaration must exist exactly once and at the very start.
        // It is added by the controller because "<?xml" inside a Blade view
        // breaks compilation when short_open_tag=On.
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $content);
        $this->assertSame(1, substr_count($content, '<?xml'));
        $this->assertStringContainsString('<urlset', $content);
        $this->assertStringContainsString('sitemap-poem', $content);

        $this->assertNotFalse(@simplexml_load_string($content));
    }

    public function test_search_filters_poems(): void
    {
        Poem::factory()->create(['title' => 'Գարուն', 'content' => 'արեւ']);
        $other = Poem::factory()->create(['title' => 'Ձմեռ Պարույր', 'content' => 'ձյուն']);

        $this->get('/?q='.urlencode('Ձմեռ'))
            ->assertOk()
            ->assertSee($other->title);
    }

    public function test_the_reading_page_asks_for_one_press_before_playing(): void
    {
        Storage::fake('audio');
        Storage::disk('audio')->put('ai_audio/press-to-play.mp3', 'MP3-BYTES');

        $poem = Poem::factory()->create([
            'slug' => 'press-to-play',
            'audio_path' => 'ai_audio/press-to-play.mp3',
        ]);

        $this->get(route('poems.show', $poem))
            ->assertOk()
            ->assertSee('id="start-hint"', false)
            ->assertSee('Սեղմիր ▶՝ լսելու համար')
            // The old hidden "tap anywhere" helper is gone for good: the page
            // now asks for one deliberate press on the play button.
            ->assertDontSee('sevakPoem', false);
    }

    public function test_a_recording_without_word_timings_still_gets_a_transport(): void
    {
        Storage::fake('audio');
        Storage::disk('audio')->put('original_audio/no-timings.mp3', 'MP3-BYTES');

        $poem = Poem::factory()->create([
            'slug' => 'no-timings',
            'audio_path' => 'original_audio/no-timings.mp3',
            'content' => 'Երկու տող',
        ]);

        $this->get(route('poems.show', $poem))
            ->assertOk()
            ->assertSee('id="play-toggle"', false)
            ->assertSee('id="seek"', false)
            // The play button and the seek bar have their listeners even when
            // the recording has no words yet (archive tracks).
            ->assertSee("document.getElementById('play-toggle')", false)
            ->assertDontSee('if (!audio || words.length === 0) return;', false);
    }

    public function test_the_catalogue_hands_out_more_cards_instead_of_a_second_page(): void
    {
        // The oldest poem lands on the second slice.
        Poem::factory()->create(['title' => 'Հին բանաստեղծություն', 'slug' => 'old-poem']);
        Poem::factory()->count(12)->create();

        $page = $this->get('/')->assertOk();

        $this->assertStringContainsString('data-load-more', (string) $page->getContent());
        // The button carries its own colour, like the hero's primary action.
        $this->assertStringContainsString('background-color: #e8c46a', (string) $page->getContent());
        $this->assertStringContainsString('page=2', (string) $page->viewData('moreUrl'));
        $this->assertStringNotContainsString('Հին բանաստեղծություն', (string) $page->getContent());

        // The button asks the same URL and receives only the cards, plus the URL
        // of the slice after this one in a header (empty: there is none).
        $fragment = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get('/?page=2')
            ->assertOk();

        $fragment->assertHeader('X-Next-Page', '');
        $this->assertStringNotContainsString('<html', (string) $fragment->getContent());
        $this->assertStringContainsString('Հին բանաստեղծություն', (string) $fragment->getContent());

        // The headers above stay with the test client: drop them, or the next
        // request would be an ajax one as well.
        $this->flushHeaders();

        // The very same slice can be asked for with the explicit flag: that is
        // what a proxy cannot strip away from the request.
        $flagged = $this->get('/?page=2&fragment=1')->assertOk();

        $this->assertStringNotContainsString('<html', (string) $flagged->getContent());
        $this->assertStringContainsString('Հին բանաստեղծություն', (string) $flagged->getContent());

        // Without either of them the page stays a full, styled document — the
        // path a visitor without JavaScript takes.
        $plain = $this->get('/?page=2')->assertOk();

        $this->assertStringContainsString('<html', (string) $plain->getContent());
        $this->assertStringContainsString('data-load-more', (string) $plain->getContent());
    }

    public function test_the_header_carries_the_search_icon_and_its_modal(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-search-open', false)
            ->assertSee('id="search-modal"', false)
            ->assertSee(route('api.search'), false);
    }

    public function test_the_hero_offers_a_play_shortcut_and_aggregate_numbers(): void
    {
        $poem = Poem::factory()->create(['slug' => 'hero-poem', 'plays_count' => 7]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Ձայնագրությունների արխիվ')
            ->assertSee('Սկսել լսելը')
            ->assertSee('data-random-poem', false)
            ->assertSee('▶ 7 լսում', false)
            ->assertSee(route('api.poems.random'), false)
            ->assertSee(Seo::poemUrl($poem), false);
    }

    public function test_an_empty_catalogue_hides_the_play_shortcut(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Ձայնագրությունների արխիվ')
            ->assertDontSee('Սկսել լսելը');
    }

    public function test_the_source_chips_filter_the_catalogue(): void
    {
        $archive = Poem::factory()->original()->create(['title' => 'Արխիվային գործ']);
        $generated = Poem::factory()->create(['title' => 'ԱԻ գործ']);

        $response = $this->get('/?source=original')->assertOk();

        $response->assertSee($archive->title);
        $response->assertDontSee($generated->title);

        $reverse = $this->get('/?source=ai')->assertOk();

        $reverse->assertSee($generated->title);
        $reverse->assertDontSee($archive->title);
    }

    public function test_the_popular_sort_leads_with_the_most_listened_poem(): void
    {
        $quiet = Poem::factory()->create(['title' => 'Հանգիստ գործ', 'plays_count' => 1]);
        $loud = Poem::factory()->create(['title' => 'Բարձր գործ', 'plays_count' => 99]);

        $response = $this->get('/?sort=popular')->assertOk();

        $response->assertSee('Ամենաշատ լսվածը');

        $content = (string) $response->getContent();

        $this->assertLessThan(
            strpos($content, $quiet->title),
            strpos($content, $loud->title),
        );
    }

    public function test_the_spotlight_stays_hidden_until_a_recording_was_listened_to(): void
    {
        Poem::factory()->create(['plays_count' => 0]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Ամենաշատ լսվածը');
    }

    public function test_malformed_query_strings_do_not_break_the_catalogue(): void
    {
        $poem = Poem::factory()->create(['title' => 'Կայուն գործ']);

        // Bots (and careless links) send array parameters: they must behave
        // like an empty filter instead of returning a 500 page.
        $this->get('/?q[]=a')->assertOk();
        $this->get('/?source[]=original&sort[]=popular')->assertOk();
        $this->get('/?page[]=2')->assertOk();
        $this->get('/?q='.urlencode('Կայուն').'&source[]=x')
            ->assertOk()
            ->assertSee($poem->title);
    }

    public function test_the_hero_statistics_degrade_instead_of_failing(): void
    {
        Poem::factory()->create();

        // Break the words table: the counter statistics must fall back to
        // zeroes (and log the failure) instead of taking the page down.
        Schema::drop('poem_words');

        $controller = new PoemController;

        $stats = (new ReflectionMethod($controller, 'stats'))->invoke($controller);
        $spotlight = (new ReflectionMethod($controller, 'spotlight'))->invoke($controller);

        $this->assertSame(['poems' => 0, 'recordings' => 0, 'plays' => 0, 'words' => 0], $stats);
        $this->assertInstanceOf(Collection::class, $spotlight);
        $this->assertTrue($spotlight->isEmpty());
    }
}
