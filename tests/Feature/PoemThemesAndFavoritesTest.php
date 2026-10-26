<?php

namespace Tests\Feature;

use App\Models\Poem;
use App\Support\PoemThemes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoemThemesAndFavoritesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_poem_receives_themes_from_its_title(): void
    {
        $poem = Poem::create([
            'title' => 'Հայաստան',
            'content' => 'Մի տող',
        ]);

        $this->assertSame(['homeland', 'longing'], $poem->themeKeys());
        $this->assertSame(['հայրենիք', 'կարոտ'], $poem->themeLabels());
        $this->assertTrue($poem->hasTheme('homeland'));
    }

    public function test_an_unknown_title_still_ends_up_with_at_least_one_theme(): void
    {
        $themes = PoemThemes::forTitle('Անհայտ վերնագիր');

        $this->assertNotEmpty($themes);
        $this->assertSame($themes, PoemThemes::normalize($themes));
    }

    public function test_the_catalogue_filters_by_theme(): void
    {
        $love = Poem::factory()->create(['title' => 'Սիրային գործ', 'themes' => ['love']]);
        $life = Poem::factory()->create(['title' => 'Կենսական գործ', 'themes' => ['life']]);

        $response = $this->get('/?theme=love')->assertOk();

        $response->assertSee($love->title);
        $response->assertDontSee($life->title);

        // A malformed theme is treated as "no filter", never as an error.
        $this->get('/?theme=unknown')
            ->assertOk()
            ->assertSee($love->title)
            ->assertSee($life->title);
    }

    public function test_the_reading_page_shows_the_themes_and_the_favourite_button(): void
    {
        $poem = Poem::factory()->create(['themes' => ['love', 'philosophy']]);

        $this->get(route('poems.show', $poem))
            ->assertOk()
            ->assertSee('data-favorite', false)
            ->assertSee('Իմ ընտրանին')
            ->assertSee(route('poems.index', ['theme' => 'love']), false)
            ->assertSee('փիլիսոփայություն');
    }

    public function test_the_header_and_cards_offer_the_favourites(): void
    {
        $poem = Poem::factory()->create(['title' => 'Սրտիկի գործ']);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-favorite', false)
            ->assertSee('data-favorites-count', false)
            ->assertSee(route('favorites.index'), false)
            ->assertSee($poem->title);
    }

    public function test_the_favourites_page_renders_its_shell_and_template(): void
    {
        $this->get(route('favorites.index'))
            ->assertOk()
            ->assertSee('Իմ ընտրանին')
            ->assertSee('data-favorites-page', false)
            ->assertSee('favorite-card-template', false)
            ->assertSee('Դեռ ոչինչ ընտրված չէ');
    }

    public function test_the_terms_and_data_page_is_reachable(): void
    {
        $this->get(route('terms'))
            ->assertOk()
            ->assertSee('Օգտագործման պայմաններ և հավաքվող տվյալներ')
            ->assertSee('localStorage')
            ->assertSee(route('favorites.index'), false);
    }

    public function test_the_new_pages_are_not_treated_as_poem_slugs(): void
    {
        // Short URLs would otherwise hand "/terms" to the poem catch-all.
        $this->get('/terms')->assertOk();
        $this->get('/favorites')->assertOk();
    }
}
