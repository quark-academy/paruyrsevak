<?php

use App\Http\Controllers\PoemShortController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Short URLs (Shorts style): "/{slug}"
|--------------------------------------------------------------------------
| Registered AFTER the admin routes (see bootstrap/app.php "then" callback)
| so that reserved prefixes such as /admin or /poems keep working. The
| negative lookahead below is a second safety net. Toggle with
| POEMS_SHORT_URLS=false if you only want the classic /poems/{slug} URLs.
*/

if (config('poems.short_urls')) {
    Route::get('/{poem}', PoemShortController::class)
        ->where('poem', '(?!admin$|up$|api$|storage$|build$|poems$|sitemap\.xml$|favicon\.ico$|robots\.txt$)[A-Za-z0-9\-]+')
        ->name('poems.short');
}
