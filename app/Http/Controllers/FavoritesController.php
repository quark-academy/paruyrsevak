<?php

namespace App\Http\Controllers;

use App\Support\Seo;
use Illuminate\View\View;

/**
 * "Իմ ընտրանին": the list itself is built in the browser from localStorage,
 * so the page only renders its shell and the empty state.
 */
class FavoritesController extends Controller
{
    public function index(): View
    {
        $canonical = Seo::canonical('favorites');

        return view('favorites.index', [
            'seo' => Seo::page([
                'title' => 'Իմ ընտրանին',
                'description' => 'Ձեր ընտրած Պարույր Սևակի բանաստեղծությունները՝ պահված միայն այս դիտարկիչում։',
                'canonical' => $canonical,
                // A personal list has nothing to offer a search result page.
                'robots' => 'noindex, follow',
                'keywords' => [Seo::POET, 'ընտրանին', 'բանաստեղծություններ'],
                'schema' => [
                    [
                        '@type' => 'WebPage',
                        '@id' => $canonical.'#page',
                        'url' => $canonical,
                        'name' => 'Իմ ընտրանին',
                        'inLanguage' => Seo::LANGUAGE,
                        'isPartOf' => ['@id' => Seo::base().'/#website'],
                    ],
                ],
            ]),
        ]);
    }
}
