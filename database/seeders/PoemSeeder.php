<?php

namespace Database\Seeders;

use App\Models\Poem;
use App\Services\PoemWordSynchronizer;
use Illuminate\Database\Seeder;

class PoemSeeder extends Seeder
{
    public function run(): void
    {
        $poems = [
            [
                'slug' => 'demo-ashun',
                'title' => 'Աշուն (դեմո)',
                'meta_title' => 'Աշուն (դեմո) | Պարույր Սևակ',
                'meta_description' => 'Դեմո բանաստեղծություն կարաոկե ցուցադրման համար։',
                'content' => <<<'AM'
                Աշուն է, տերևները թափվում են դանդաղ,
                Կարոտը մնում է սրտում անմար։
                Խոսքերդ հիշում եմ լուռ ու անձայն,
                Եվ սերը դարձյալ արթնանում է բացահայտ։
                AM,
            ],
            [
                'slug' => 'demo-garun',
                'title' => 'Գարուն (դեմո)',
                'meta_title' => 'Գարուն (դեմո) | Պարույր Սևակ',
                'meta_description' => 'Երկրորդ դեմո բանաստեղծությունը՝ ուղղահայաց նավիգացիան ցուցադրելու համար։',
                'content' => <<<'AM'
                Գարունը գալիս է լուռ քայլերով,
                Ծաղիկներն արթնանում են ցողի տակ։
                Սիրտը բացվում է նոր հույսերով,
                Եվ երգը դառնում է թեթև ու պարզ։
                AM,
            ],
            [
                'slug' => 'demo-gisher',
                'title' => 'Գիշեր (դեմո)',
                'meta_title' => 'Գիշեր (դեմո) | Պարույր Սևակ',
                'meta_description' => 'Երրորդ դեմո բանաստեղծությունը՝ Shorts-ոճի ընթերցման համար։',
                'content' => <<<'AM'
                Գիշերն իջնում է աստղերի հետ,
                Լռությունը խոսում է մեղմ ձայնով։
                Հիշողություններս մնում են հետ,
                Եվ լույսը ծնվում է խավարից։
                AM,
            ],
        ];

        $synchronizer = app(PoemWordSynchronizer::class);

        foreach ($poems as $data) {
            $poem = Poem::query()->firstOrCreate(
                ['slug' => $data['slug']],
                [
                    'title' => $data['title'],
                    'content' => $data['content'],
                    'is_original' => false,
                    'meta_title' => $data['meta_title'],
                    'meta_description' => $data['meta_description'],
                ],
            );

            if ($poem->words()->count() === 0) {
                $synchronizer->syncFromContent($poem);
            }

            $this->command?->info("Demo poem ready: {$poem->slug}");
        }
    }
}
