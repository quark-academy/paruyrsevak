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
        Poem::factory()->create();

        $response = $this->get('/sitemap.xml')->assertOk();

        $this->assertStringContainsString('<urlset', $response->getContent());
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
