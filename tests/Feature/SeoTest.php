<?php

namespace Tests\Feature;

use App\Models\Poem;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_base_is_configurable_and_normalised(): void
    {
        config()->set('poems.canonical_base', 'https://subdomain.domain.am/');

        $this->assertSame('https://subdomain.domain.am', Seo::base());
        $this->assertSame('https://subdomain.domain.am/', Seo::canonical());
        $this->assertSame('https://subdomain.domain.am/some-slug', Seo::canonical('/some-slug'));
    }

    public function test_canonical_for_poem_uses_the_slug(): void
    {
        config()->set('poems.canonical_base', 'https://subdomain.domain.am');

        $poem = Poem::factory()->create(['slug' => 'my-poem']);

        $this->assertSame('https://subdomain.domain.am/my-poem', Seo::forPoem($poem));
    }

    public function test_poem_url_points_to_the_short_route(): void
    {
        $poem = Poem::factory()->create(['slug' => 'short-link']);

        $this->assertSame('/short-link', Seo::poemUrl($poem));
    }

    public function test_title_and_description_fall_back_to_poem_fields(): void
    {
        $poem = Poem::factory()->create([
            'title' => 'Վերնագիր',
            'meta_title' => null,
            'meta_description' => null,
            'content' => 'Տող մեկ ու երկու',
        ]);

        $this->assertSame('Վերնագիր', Seo::titleForPoem($poem));
        $this->assertStringContainsString('Տող մեկ', Seo::descriptionForPoem($poem));
    }

    public function test_home_page_exposes_a_canonical_link(): void
    {
        config()->set('poems.canonical_base', 'https://subdomain.domain.am');

        $this->get('/')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://subdomain.domain.am/"', false);
    }

    public function test_catalogue_route_canonicalises_to_the_home_page(): void
    {
        config()->set('poems.canonical_base', 'https://subdomain.domain.am');

        $this->get('/poems')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://subdomain.domain.am/"', false);
    }
}
