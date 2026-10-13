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

        return response()
            ->view('sitemap', ['poems' => $poems])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
