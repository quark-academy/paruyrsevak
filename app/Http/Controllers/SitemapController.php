<?php

namespace App\Http\Controllers;

use App\Models\Poem;
use App\Support\PoemThemes;
use App\Support\Seo;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * The sitemap is generated, never written by hand: every poem, the theme
 * landing pages and the legal page are listed with their last modification.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $poems = Poem::query()->latest('updated_at')->get();

        // The XML declaration is added here (not in the Blade view) on purpose:
        // a literal "<?xml" inside a Blade template breaks compilation when the
        // host has short_open_tag=On (Blade uses token_get_all()).
        $content = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .view('sitemap', [
                'poems' => $poems,
                'themes' => PoemThemes::LABELS,
                'lastModified' => $poems->max('updated_at')
                    ? Carbon::parse($poems->max('updated_at'))->toAtomString()
                    : null,
                'termsUrl' => Seo::canonical('terms'),
            ])->render();

        return response($content)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
