<?php

namespace Tests\Feature;

use App\Models\Poem;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEO is generated, not typed: every page gets its title, description, social
 * tags and structured data from the poem and the page itself. The promise of
 * the site ("Կարդում է հեղինակը") lives in the title while it fits and moves
 * to the description of a title that is already at its limit.
 */
class SeoAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('poems.canonical_base', 'https://subdomain.domain.am');
        config()->set('poems.seo.title_limit', 60);
        config()->set('poems.seo.description_limit', 160);
    }

    /** The <title> of a rendered page. */
    protected function titleOf(string $url): string
    {
        $html = (string) $this->get($url)->assertOk()->getContent();

        preg_match('/<title>(.*?)<\/title>/u', $html, $matches);

        return html_entity_decode($matches[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** @return array<string, mixed> */
    protected function structuredDataOf(string $url): array
    {
        $html = (string) $this->get($url)->assertOk()->getContent();

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/su', $html, $matches);

        $data = json_decode($matches[1] ?? '', true);

        $this->assertIsArray($data, 'The structured data must be valid JSON.');

        return $data;
    }

    public function test_a_poem_title_carries_the_reading_phrase(): void
    {
        $poem = Poem::factory()->create(['title' => 'Հայաստան', 'meta_title' => null, 'slug' => 'hayastan']);

        $title = $this->titleOf(route('poems.show', $poem));

        $this->assertSame('Պարույր Սևակ «Հայաստան» — Կարդում է հեղինակը', $title);
        $this->assertLessThanOrEqual(Seo::titleLimit(), mb_strlen($title));
    }

    public function test_the_reading_page_and_the_player_share_one_title_and_one_canonical(): void
    {
        $poem = Poem::factory()->create(['title' => 'Դու', 'slug' => 'du', 'meta_title' => null]);

        $reading = $this->get(route('poems.show', $poem))->assertOk();
        $player = $this->get(route('poems.short', $poem))->assertOk();

        foreach ([$reading, $player] as $response) {
            $response->assertSee('<link rel="canonical" href="https://subdomain.domain.am/du"', false);
            $response->assertSee('Կարդում է հեղինակը', false);
        }

        $this->assertSame($this->titleOf(route('poems.show', $poem)), $this->titleOf(route('poems.short', $poem)));
    }

    public function test_a_title_that_cannot_hold_the_phrase_moves_it_to_the_description(): void
    {
        $poem = Poem::factory()->create([
            'title' => str_repeat('Երկար բանաստեղծություն ', 4),
            'meta_title' => null,
            'slug' => 'long-one',
        ]);

        $html = (string) $this->get(route('poems.show', $poem))->assertOk()->getContent();
        $title = $this->titleOf(route('poems.show', $poem));

        $this->assertStringNotContainsString(Seo::READING_PHRASE, $title);
        $this->assertLessThanOrEqual(Seo::titleLimit(), mb_strlen($title));
        // The overflow rule: the promise is not lost, it moves down.
        $this->assertStringContainsString('name="description" content="'.Seo::READING_PHRASE.'։', $html);
    }

    public function test_a_long_poem_name_is_shortened_inside_the_quotes(): void
    {
        $poem = Poem::factory()->create([
            'title' => 'Առաջադրանք համայն աշխարհի հաշվիչ մեքենաներին և ճշգրիտ սարքերին',
            'meta_title' => null,
            'slug' => 'long-name',
        ]);

        $title = $this->titleOf(route('poems.show', $poem));

        $this->assertStringStartsWith('Պարույր Սևակ «', $title);
        $this->assertStringEndsWith('…»', $title);
        $this->assertLessThanOrEqual(Seo::titleLimit(), mb_strlen($title));
    }

    public function test_the_description_is_written_when_the_editor_did_not_write_one(): void
    {
        $poem = Poem::factory()->create([
            'title' => 'Հայաստան',
            'meta_description' => null,
            'themes' => ['homeland', 'longing'],
            'content' => 'Դու՝ Զվարթնոց - ավեր տաճար, Կոմիտասյան Ծիրանի ծառ։',
        ]);

        $html = (string) $this->get(route('poems.show', $poem))->assertOk()->getContent();

        $this->assertStringContainsString('Լսիր Պարույր Սևակի «Հայաստան» բանաստեղծությունը', $html);
        $this->assertStringContainsString('Թեմաներ՝ հայրենիք, կարոտ։', $html);
        $this->assertStringContainsString('Զվարթնոց', $html);
        $this->assertLessThanOrEqual(
            Seo::descriptionLimit(),
            mb_strlen($this->attributeOf($html, 'name="description" content="')),
        );
    }

    public function test_structured_data_describes_the_poem_the_site_and_the_trail(): void
    {
        $poem = Poem::factory()->create(['title' => 'Մոր ձեռքերը', 'slug' => 'mor-dzerqery']);

        $data = $this->structuredDataOf(route('poems.show', $poem));

        $this->assertSame('https://schema.org', $data['@context']);

        $types = array_map(
            static fn (array $node) => is_array($node['@type']) ? implode('+', $node['@type']) : $node['@type'],
            $data['@graph'],
        );

        $this->assertContains('WebSite', $types);
        $this->assertContains('Person', $types);
        $this->assertContains('Article+CreativeWork', $types);
        $this->assertContains('BreadcrumbList', $types);

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->assertStringContainsString('https://subdomain.domain.am/mor-dzerqery#poem', $json);
        $this->assertStringContainsString('"inLanguage":"hy"', $json);
        $this->assertStringContainsString('"name":"Պարույր Սևակ"', $json);
        $this->assertStringContainsString('"image"', $json);
    }

    public function test_the_catalogue_lists_its_poems_for_crawlers(): void
    {
        Poem::factory()->create(['title' => 'Ցանկի գործ', 'slug' => 'list-item']);

        $data = $this->structuredDataOf('/');
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->assertStringContainsString('"@type":"ItemList"', $json);
        $this->assertStringContainsString('https://subdomain.domain.am/list-item', $json);
        $this->assertStringContainsString('"@type":"SearchAction"', $json);
        $this->assertStringContainsString('"@type":"CollectionPage"', $json);
    }

    public function test_a_theme_page_has_its_own_title_and_canonical(): void
    {
        config()->set('poems.seo.title_limit', 60);
        Poem::factory()->create(['title' => 'Սիրային գործ', 'themes' => ['love']]);

        $response = $this->get('/?theme=love')->assertOk();

        $response->assertSee('<link rel="canonical" href="https://subdomain.domain.am/?theme=love"', false);
        $response->assertSee('Սիրո բանաստեղծություններ', false);
        $response->assertSee('index, follow', false);
    }

    public function test_search_results_and_deep_slices_are_not_indexed(): void
    {
        Poem::factory()->create(['title' => 'Փնտրվող գործ']);

        $this->get('/?q='.urlencode('Փնտրվող'))
            ->assertOk()
            ->assertSee('name="robots" content="noindex, follow', false);

        $this->get('/?page=2')
            ->assertOk()
            ->assertSee('name="robots" content="noindex, follow', false);
    }

    public function test_the_personal_list_is_not_indexed(): void
    {
        $this->get(route('favorites.index'))
            ->assertOk()
            ->assertSee('name="robots" content="noindex, follow"', false);
    }

    public function test_every_public_page_ships_valid_structured_data(): void
    {
        $poem = Poem::factory()->create(['slug' => 'every-page']);

        foreach (['/', route('poems.index'), route('poems.show', $poem), route('poems.short', $poem), route('terms'), route('favorites.index')] as $url) {
            $data = $this->structuredDataOf($url);

            $this->assertArrayHasKey('@graph', $data, 'Missing JSON-LD graph on '.$url);
        }
    }

    public function test_robots_points_to_the_generated_sitemap(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Sitemap: https://subdomain.domain.am/sitemap.xml')
            ->assertSee('Disallow: /admin');
    }

    public function test_the_sitemap_lists_poems_themes_and_the_terms_page(): void
    {
        Poem::factory()->create(['slug' => 'sitemap-entry']);

        $response = $this->get('/sitemap.xml')->assertOk();
        $content = (string) $response->getContent();

        $this->assertStringContainsString('https://subdomain.domain.am/sitemap-entry', $content);
        $this->assertStringContainsString('https://subdomain.domain.am/?theme=love', $content);
        $this->assertStringContainsString('https://subdomain.domain.am/terms', $content);
        $this->assertNotFalse(@simplexml_load_string($content));
    }

    public function test_a_manual_title_is_kept_and_the_phrase_is_added_when_it_fits(): void
    {
        $poem = Poem::factory()->create([
            'title' => 'Անուն',
            'meta_title' => 'Պարույր Սևակ «Անուն» — Բանաստեղծություն',
            'slug' => 'manual-one',
        ]);

        $title = $this->titleOf(route('poems.short', $poem));

        // The legacy "— Բանաստեղծություն" tail is dropped, the phrase is added.
        $this->assertSame('Պարույր Սևակ «Անուն» — Կարդում է հեղինակը', $title);
    }

    public function test_a_legacy_title_that_ends_with_the_author_is_rebuilt(): void
    {
        $poem = Poem::factory()->create([
            'title' => 'Լեռնային գիշեր',
            'meta_title' => '«Լեռնային գիշեր» — Պարույր Սևակ',
            'slug' => 'legacy-title',
        ]);

        $title = $this->titleOf(route('poems.short', $poem));

        $this->assertSame('Պարույր Սևակ «Լեռնային գիշեր» — Կարդում է հեղինակը', $title);
        // The last letter must survive: trimming quotes by byte would cut it.
        $this->assertTrue(mb_check_encoding($title, 'UTF-8'));
        $this->assertStringEndsWith('հեղինակը', $title);
    }

    /** The value of the first attribute that starts with the given prefix. */
    protected function attributeOf(string $html, string $prefix): string
    {
        $position = mb_strpos($html, $prefix);

        if ($position === false) {
            return '';
        }

        $start = $position + mb_strlen($prefix);
        $end = mb_strpos($html, '"', $start);

        return html_entity_decode(mb_substr($html, $start, $end - $start), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
