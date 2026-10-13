<?php

namespace Tests\Feature;

use App\Models\Poem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_admin_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_non_admin_user_is_forbidden(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_admin_can_login(): void
    {
        $admin = User::factory()->create([
            'email' => 'chief@paruyrsevak.am',
            'password' => 'secret-password',
            'is_admin' => true,
        ]);

        $this->post(route('admin.login.attempt'), [
            'email' => 'chief@paruyrsevak.am',
            'password' => 'secret-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_can_create_a_poem(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post(route('admin.poems.store'), [
            'title' => 'Աշուն',
            'content' => "Տող մեկ\nՏող երկու",
            'is_original' => '0',
        ]);

        $poem = Poem::query()->firstOrFail();

        $response->assertRedirect(route('admin.poems.show', $poem));
        $this->assertDatabaseHas('poems', ['title' => 'Աշուն']);
    }

    public function test_admin_pages_render_without_errors(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $poem = Poem::factory()->create(['content' => 'Մեկ երկու երեք']);
        $poem->words()->create(['word' => 'Մեկ', 'start_time' => 0, 'end_time' => 1, 'word_order' => 0]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.poems.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.poems.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.poems.show', $poem))->assertOk();
        $this->actingAs($admin)->get(route('admin.poems.edit', $poem))->assertOk();
        $this->actingAs($admin)->get(route('admin.poems.timings', $poem))->assertOk();
    }

    public function test_public_pages_render_without_errors(): void
    {
        $poem = Poem::factory()->create(['content' => 'Մեկ երկու երեք']);
        $poem->words()->create(['word' => 'Մեկ', 'start_time' => 0, 'end_time' => 1, 'word_order' => 0]);

        $this->get('/')->assertOk();
        $this->get(route('poems.index'))->assertOk();
        $this->get(route('poems.show', $poem))->assertOk();
        $this->get(route('poems.short', $poem))->assertOk();
        $this->get(route('sitemap'))->assertOk();
        $this->getJson(route('api.poems.player', $poem))->assertOk();
    }

    public function test_admin_can_store_word_timings(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $poem = Poem::factory()->create(['content' => 'Մեկ երկու']);

        $response = $this->actingAs($admin)->post(route('admin.poems.timings.update', $poem), [
            'words' => [
                ['word' => 'Մեկ', 'start_time' => '0', 'end_time' => '0.50', 'word_order' => 0],
                ['word' => 'երկու', 'start_time' => '0.50', 'end_time' => '1.20', 'word_order' => 1],
            ],
        ]);

        $response->assertRedirect();
        $this->assertSame(2, $poem->words()->count());
        $this->assertDatabaseHas('poem_words', ['poem_id' => $poem->id, 'word' => 'Մեկ']);
    }

    public function test_the_timings_editor_flags_words_that_differ_from_the_text(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $poem = Poem::factory()->create(['content' => 'Մեկ երկու']);
        $poem->words()->create(['word' => 'Մէկ', 'start_time' => 0, 'end_time' => 1, 'word_order' => 0]);

        $this->actingAs($admin)
            ->get(route('admin.poems.timings', $poem))
            ->assertOk()
            ->assertSee('1 բառ տարբերվում է տեքստից', false)
            // One click fixes the spelling: the editor offers the text's token.
            ->assertSee('↺ դարձնել «Մեկ»', false)
            ->assertSee('data-word-input', false);
    }

    public function test_a_corrected_spelling_is_persisted(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $poem = Poem::factory()->create(['content' => 'Մեկ երկու']);
        $poem->words()->create(['word' => 'Մէկ', 'start_time' => 0, 'end_time' => 1, 'word_order' => 0]);

        $this->actingAs($admin)->post(route('admin.poems.timings.update', $poem), [
            'words' => [
                ['word' => 'Մեկ', 'start_time' => '0', 'end_time' => '1', 'word_order' => 0],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('poem_words', ['poem_id' => $poem->id, 'word' => 'Մեկ']);
        $this->assertDatabaseMissing('poem_words', ['poem_id' => $poem->id, 'word' => 'Մէկ']);
    }

    public function test_a_word_list_that_matches_the_text_is_reported_as_clean(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $poem = Poem::factory()->create(['content' => 'Մեկ երկու']);
        $poem->words()->create(['word' => 'Մեկ', 'start_time' => 0, 'end_time' => 1, 'word_order' => 0]);

        $this->actingAs($admin)
            ->get(route('admin.poems.timings', $poem))
            ->assertOk()
            ->assertSee('բոլորը համընկնում են տեքստի հետ', false)
            // No per-word "restore" button means nothing had to be fixed.
            ->assertDontSee('data-token', false);
    }

    public function test_admin_can_regenerate_words_from_content(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $poem = Poem::factory()->create(['content' => 'Մեկ երկու երեք']);

        $this->actingAs($admin)
            ->post(route('admin.poems.timings.sync', $poem))
            ->assertRedirect();

        $this->assertSame(3, $poem->words()->count());
    }
}
