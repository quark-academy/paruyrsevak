<?php

namespace App\Http\Controllers;

use App\Models\Poem;
use App\Services\PoemPlayerPayload;
use App\Support\Seo;
use App\Support\UrlConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Public immersive "Shorts" page for a single poem (canonical "/{slug}").
 */
class PoemShortController extends Controller
{
    public function __invoke(
        Poem $poem,
        PoemPlayerPayload $payload,
        Request $request,
    ): View|Response {
        // A route that carries no "{poem}" at all (a catch-all written as
        // "{poem?}", or a route cache left over from an older deploy) hands
        // this controller a brand new, empty model. The player has nothing to
        // show then — the visitor used to land on a dark screen — so the site
        // root answers with the catalogue and every other path is missing.
        if (! $poem->exists || blank($poem->getRouteKey())) {
            if ($this->isSiteRoot($request)) {
                // The visitor asked for the home page: answer it here instead
                // of sending a redirect, so the address bar keeps the root.
                return app(PoemController::class)->index($request);
            }

            abort(404);
        }

        return view('poems.short', [
            'poem' => $poem,
            'payload' => $payload->build($poem),
            'seo' => Seo::poemPage($poem),
        ]);
    }

    /**
     * Whether the request asked for the site itself rather than for a poem.
     *
     * Both the normalised path ("/") and a base path the middleware failed to
     * strip ("/sevak") count as the root: the visitor wanted the home page and
     * must never be told that it does not exist.
     */
    protected function isSiteRoot(Request $request): bool
    {
        $path = trim($request->path(), '/');

        if ($path === '') {
            return true;
        }

        $roots = array_filter([
            trim(UrlConfig::basePath(), '/'),
            basename(str_replace('\\', '/', base_path())),
        ]);

        return in_array($path, $roots, true);
    }
}
