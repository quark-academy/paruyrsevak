<?php

namespace App\Http\Controllers;

use App\Models\Poem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $poems = Poem::query()->latest('updated_at')->get();

        // The XML declaration is added here (not in the Blade view) on purpose:
        // a literal "<?xml" inside a Blade template breaks compilation when the
        // host has short_open_tag=On (Blade uses token_get_all()).
        $content = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .view('sitemap', ['poems' => $poems])->render();

        return response($content)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
