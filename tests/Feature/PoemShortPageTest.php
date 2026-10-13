<?php

namespace Tests\Feature;

use App\Models\Poem;
use App\Services\PoemPlayerPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoemShortPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('poems.canonical_base', 'https://subdomain.domain.am');
    }

    public function test_poem_is_reachable_through_short_url(): void
    {
        $poem = Poem::factory()->create(['title' => 'Կարճ հղում', 'slug' => 'karch-hghum']);

        $this->get('/karch-hghum')
            ->assertOk()
            ->assertSee($poem->title);
    }

    public function test_short_page_renders_dynamic_seo_tags(): void
    {
        $poem = Poem::factory()->create([
            'title' => 'Անուն',
            'slug' => 'seo-test',
            'meta_title' => 'SEO վերնագիր',
            'meta_description' => 'SEO նկարագրություն տող',
        ]);

        $response = $this->get('/seo-test')->assertOk();

        $response->assertSee('<title>SEO վերնագիր</title>', false);
        $response->assertSee('name="description" content="SEO նկարագրություն տող"', false);
    }

    public function test_canonical_uses_the_configured_base_domain(): void
    {
        Poem::factory()->create(['slug' => 'canonical-check', 'meta_title' => null]);

        $this->get('/canonical-check')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://subdomain.domain.am/canonical-check"', false);
    }

    public function test_payload_groups_words_into_content_lines(): void
    {
        $poem = Poem::factory()->create([
            'slug' => 'lines-check',
            'content' => "Մեկ երկու երեք\nՉորս հինգ",
        ]);

        $poem->words()->createMany([
            ['word' => 'Մեկ', 'start_time' => 0, 'end_time' => 0.4, 'word_order' => 0],
            ['word' => 'երկու', 'start_time' => 0.4, 'end_time' => 0.9, 'word_order' => 1],
            ['word' => 'երեք', 'start_time' => 0.9, 'end_time' => 1.4, 'word_order' => 2],
            ['word' => 'Չորս', 'start_time' => 1.4, 'end_time' => 1.8, 'word_order' => 3],
            ['word' => 'հինգ', 'start_time' => 1.8, 'end_time' => 2.2, 'word_order' => 4],
        ]);

        $payload = app(PoemPlayerPayload::class)->build($poem->fresh());

        $this->assertCount(2, $payload['lines']);
        $this->assertCount(3, $payload['lines'][0]);
        $this->assertCount(2, $payload['lines'][1]);
        $this->assertSame('Մեկ', $payload['lines'][0][0]['w']);
        $this->assertEqualsWithDelta(0.4, $payload['lines'][0][0]['e'], 0.001);
    }

    public function test_prev_and_next_navigation_are_provided(): void
    {
        $first = Poem::factory()->create(['slug' => 'first-poem']);
        $middle = Poem::factory()->create(['slug' => 'middle-poem']);
        $last = Poem::factory()->create(['slug' => 'last-poem']);

        $payload = app(PoemPlayerPayload::class)->build($middle);

        $this->assertSame($first->slug, $payload['prev']['slug']);
        $this->assertSame($last->slug, $payload['next']['slug']);
    }

    public function test_reserved_prefixes_are_not_captured_by_the_short_route(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get('/poems')->assertOk();
        $this->get('/sitemap.xml')->assertOk();
        $this->get('/definitely-missing-slug')->assertNotFound();
    }

    public function test_reading_page_shares_the_same_canonical_as_the_short_url(): void
    {
        $poem = Poem::factory()->create(['slug' => 'shared-canonical']);

        $this->get(route('poems.show', $poem))
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://subdomain.domain.am/shared-canonical"', false);
    }
}
