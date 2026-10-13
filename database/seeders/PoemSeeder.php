<?php

namespace Database\Seeders;

use App\Models\Poem;
use App\Services\PoemWordSynchronizer;
use Illuminate\Database\Seeder;

class PoemSeeder extends Seeder
{
    public function run(): void
    {
        $content = <<<'AM'
        Աշուն է, տերևները թափվում են դանդաղ,
        Կարոտը մնում է սրտում անմար։
        Խոսքերդ հիշում եմ լուռ ու անձայն,
        Եվ սերը դարձյալ արթնանում է բացահայտ։
        AM;

        $poem = Poem::query()->firstOrCreate(
            ['slug' => 'demo-ashun'],
            [
                'title' => 'Աշուն (դեմո)',
                'content' => $content,
                'is_original' => false,
                'meta_title' => 'Աշուն (դեմո) | Պարույր Սևակ',
                'meta_description' => 'Դեմո բանաստեղծություն կարաոկե ցուցադրման համար։',
            ],
        );

        if ($poem->words()->count() === 0) {
            app(PoemWordSynchronizer::class)->syncFromContent($poem);
        }

        $this->command?->info("Demo poem ready: {$poem->slug}");
    }
}
