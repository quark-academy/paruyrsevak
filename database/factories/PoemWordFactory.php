<?php

namespace Database\Factories;

use App\Models\Poem;
use App\Models\PoemWord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PoemWord>
 */
class PoemWordFactory extends Factory
{
    protected $model = PoemWord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->randomFloat(2, 0, 60);

        return [
            'poem_id' => Poem::factory(),
            'word' => fake()->word(),
            'start_time' => $start,
            'end_time' => $start + fake()->randomFloat(2, 0.2, 1.5),
            'word_order' => fake()->numberBetween(0, 50),
        ];
    }
}
