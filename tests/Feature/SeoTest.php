<?php

namespace Tests\Feature;

use App\Models\Poem;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
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

        $this->assertSame(route('poems.short', $poem), Seo::poemUrl($poem));
    }

    public function test_poem_url_can_be_rendered_relative(): void
    {
        $poem = Poem::factory()->create(['slug' => 'relative-link']);

        $this->assertSame('/relative-link', Seo::poemUrl($poem, absolute: false));
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

    public function test_subdirectory_deployment_generates_prefixed_urls(): void
    {
        config()->set('poems.canonical_base', 'https://lab.quark.am/sevak');

        $poem = Poem::factory()->create(['slug' => 'sub-link']);

        // Mirrors what AppServiceProvider does when APP_FORCE_ROOT_URL=true.
        URL::forceRootUrl('https://lab.quark.am/sevak');
        URL::forceScheme('https');
        // Mirrors the ASSET_URL binding (config('app.asset_url')).
        URL::useAssetOrigin('https://lab.quark.am/sevak');

        $this->assertSame('https://lab.quark.am/sevak/sub-link', Seo::poemUrl($poem));
        $this->assertSame('https://lab.quark.am/sevak/sub-link', Seo::forPoem($poem));
        $this->assertSame('https://lab.quark.am/sevak/api/poems/sub-link', route('api.poems.player', $poem));
        $this->assertSame('https://lab.quark.am/sevak/build/app.css', asset('build/app.css'));
    }

    public function test_subdirectory_config_keys_are_available(): void
    {
        $this->assertArrayHasKey('asset_url', config('app'));
        $this->assertArrayHasKey('force_root_url', config('app'));
    }
}
