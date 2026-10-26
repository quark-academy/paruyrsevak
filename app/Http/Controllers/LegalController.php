<?php

namespace App\Http\Controllers;

use App\Support\Seo;
use Illuminate\View\View;

/**
 * The single public page with the usage terms and the data the site collects.
 */
class LegalController extends Controller
{
    public function terms(): View
    {
        $canonical = Seo::canonical('terms');

        return view('legal.terms', [
            'seo' => Seo::page([
                'title' => 'Օգտագործման պայմաններ և հավաքվող տվյալներ',
                'description' => 'Կայքի օգտագործման պայմանները, հեղինակային իրավունքը և այն տվյալները, որոնք հավաքվում են կայքից օգտվելիս։',
                'canonical' => $canonical,
                'keywords' => [Seo::POET, 'օգտագործման պայմաններ', 'գաղտնիություն', 'տվյալներ'],
                'schema' => [
                    [
                        '@type' => 'WebPage',
                        '@id' => $canonical.'#page',
                        'url' => $canonical,
                        'name' => 'Օգտագործման պայմաններ և հավաքվող տվյալներ',
                        'inLanguage' => Seo::LANGUAGE,
                        'isPartOf' => ['@id' => Seo::base().'/#website'],
                        'dateModified' => now()->toAtomString(),
                    ],
                ],
            ]),
        ]);
    }
}
