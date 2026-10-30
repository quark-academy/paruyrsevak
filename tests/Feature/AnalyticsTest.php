<?php

namespace Tests\Feature;

use App\Models\Poem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The tag has to be present in both public documents: the shared Blade layout
 * (catalogue, reading page, favourites, terms) and the standalone Vue player,
 * which renders its own <head>.
 */
class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_blade_pages_render_the_gtag_snippet(): void
    {
        Poem::factory()->create();

        $this->get('/poems')
            ->assertOk()
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-QJFES4FMPK', false)
            ->assertSee('gtag(\'config\', "G-QJFES4FMPK")', false);
    }

    public function test_player_page_renders_the_gtag_snippet(): void
    {
        $poem = Poem::factory()->create(['slug' => 'analytics-player']);

        $this->get('/analytics-player')
            ->assertOk()
            ->assertSee($poem->title)
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-QJFES4FMPK', false);
    }

    public function test_measurement_id_comes_from_configuration(): void
    {
        config()->set('analytics.google_analytics_id', 'G-TEST123456');
        Poem::factory()->create();

        $this->get('/poems')
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TEST123456', false)
            ->assertDontSee('G-QJFES4FMPK', false);
    }

    public function test_empty_id_renders_nothing(): void
    {
        config()->set('analytics.google_analytics_id', null);
        Poem::factory()->create();

        $this->get('/poems')->assertOk()->assertDontSee('googletagmanager.com', false);
    }

    public function test_disabled_flag_renders_nothing(): void
    {
        config()->set('analytics.enabled', false);
        Poem::factory()->create();

        $this->get('/poems')->assertOk()->assertDontSee('googletagmanager.com', false);
    }

    public function test_admin_panel_is_not_tracked(): void
    {
        $this->get('/admin/login')->assertOk()->assertDontSee('googletagmanager.com', false);
    }
}
