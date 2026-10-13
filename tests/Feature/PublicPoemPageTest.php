<?php

namespace Tests\Feature;

use App\Models\Poem;
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
}
