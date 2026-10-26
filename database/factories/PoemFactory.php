<?php

namespace Database\Factories;

use App\Models\Poem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Poem>
 */
class PoemFactory extends Factory
{
    protected $model = Poem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = 'Ստեղծագործություն '.fake()->unique()->numberBetween(1, 999999);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'content' => fake()->paragraph(),
            'themes' => ['life', 'philosophy'],
            'audio_path' => null,
            'is_original' => false,
            'meta_title' => $title,
            'meta_description' => Str::limit(fake()->sentence(), 150),
        ];
    }

    public function original(): static
    {
        return $this->state(fn (): array => ['is_original' => true]);
    }
}
