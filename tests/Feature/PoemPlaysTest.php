<?php

namespace Tests\Feature;

use App\Models\Poem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Listen counter: registered by the players, displayed in the catalogue.
 */
class PoemPlaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_listen_is_counted_once_per_visitor(): void
    {
        $poem = $this->poemWithAudio();

        $first = $this->postJson(route('api.poems.play', $poem));
        $first->assertOk()->assertJson(['counted' => true, 'plays' => 1, 'label' => '1']);

        // Same visitor again (e.g. pause + resume or a reload) is not counted.
        $this->postJson(route('api.poems.play', $poem))
            ->assertOk()
            ->assertJson(['counted' => false, 'plays' => 1]);

        $this->assertSame(1, $poem->fresh()->plays_count);
    }

    public function test_another_visitor_is_counted_again(): void
    {
        $poem = $this->poemWithAudio();

        $this->postJson(route('api.poems.play', $poem), [], ['REMOTE_ADDR' => '10.0.0.1'])->assertOk();
        $this->postJson(route('api.poems.play', $poem), [], ['REMOTE_ADDR' => '10.0.0.2'])->assertOk();

        $this->assertSame(2, $poem->fresh()->plays_count);
    }

    public function test_listens_are_not_counted_for_a_poem_without_audio(): void
    {
        $poem = Poem::factory()->create(['slug' => 'silent-poem', 'audio_path' => null]);

        $this->postJson(route('api.poems.play', $poem))
            ->assertOk()
            ->assertJson(['counted' => false, 'plays' => 0]);

        $this->assertSame(0, $poem->fresh()->plays_count);
    }

    public function test_the_catalogue_and_the_payload_expose_the_counter(): void
    {
        $poem = Poem::factory()->create([
            'slug' => 'popular-poem',
            'plays_count' => 1234,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('1 234', false)
            ->assertSee('լսում', false);

        $this->getJson(route('api.poems.player', $poem))
            ->assertOk()
            ->assertJson([
                'plays' => 1234,
                'playsLabel' => '1 234',
                'playsUrl' => route('api.poems.play', $poem),
            ]);
    }

    public function test_the_admin_list_shows_the_counter(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Poem::factory()->create(['title' => 'Հայտնի բանաստեղծություն', 'plays_count' => 42]);

        $this->actingAs($admin)
            ->get(route('admin.poems.index'))
            ->assertOk()
            ->assertSee('Լսումներ', false)
            ->assertSee('42', false);
    }

    private function poemWithAudio(): Poem
    {
        Storage::disk('public')->put('original_audio/counter.mp3', 'MP3-DATA');

        return Poem::factory()->create([
            'slug' => 'counter-poem',
            'content' => 'Մի փոքրիկ տղա գնում էր տուն, և ճանապարհին տեսավ մի ծերունու։',
            'audio_path' => 'original_audio/counter.mp3',
            'is_original' => true,
        ]);
    }
}
