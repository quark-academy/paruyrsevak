<?php

namespace App\Http\Controllers;

use App\Models\Poem;
use App\Services\PoemPlayerPayload;
use Illuminate\View\View;

/**
 * Public immersive "Shorts" page for a single poem (canonical "/{slug}").
 */
class PoemShortController extends Controller
{
    public function __invoke(Poem $poem, PoemPlayerPayload $payload): View
    {
        return view('poems.short', [
            'poem' => $poem,
            'payload' => $payload->build($poem),
            'seo' => \App\Support\Seo::poemPage($poem),
        ]);
    }
}
