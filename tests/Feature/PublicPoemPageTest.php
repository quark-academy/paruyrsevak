<?php

namespace Tests\Feature;

use App\Models\Poem;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
