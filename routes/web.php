<?php

use App\Http\Controllers\DiagnosticsController;
use App\Http\Controllers\FavoritesController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PoemAudioStreamController;
use App\Http\Controllers\PoemController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PoemController::class, 'index'])->name('home');

/*
| When the site is served from a subdirectory (https://host/sevak) the folder
| URL itself must answer with the catalogue: the catch-all "/{poem}" of the
| short URLs would otherwise be free to treat "sevak" as a poem slug.
| Registered here (before the short routes) on purpose.
*/
$basePath = rtrim((string) config('poems.base_path', ''), '/');

if ($basePath === '') {
    $basePath = rtrim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');
}

if ($basePath !== '') {
    Route::get($basePath, [PoemController::class, 'index'])->name('home.base');
}

Route::get('/poems', [PoemController::class, 'index'])->name('poems.index');
Route::get('/poems/{poem}', [PoemController::class, 'show'])->name('poems.show');

// The visitor's own selection (localStorage) and the terms/data page. They sit
// before the short "/{slug}" catch-all so their names are never poem slugs.
Route::get('/favorites', [FavoritesController::class, 'index'])->name('favorites.index');
Route::get('/terms', [LegalController::class, 'terms'])->name('terms');

// Recordings: signed + expiring, the files themselves are outside the web root.
Route::get('/audio/{poem}', PoemAudioStreamController::class)
    ->middleware('throttle:240,1')
    ->name('poems.audio');

// Deployment diagnostics (APP_DEBUG only): shows how the base path was derived.
Route::get('/__diagnostics', DiagnosticsController::class)->name('diagnostics');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// Fallback for "public" disk files (/storage/...). Apache serves them directly
// when public/storage symlink exists; otherwise Laravel streams the file.
Route::get('/storage/{path}', MediaController::class)
    ->where('path', '.*')
    ->name('media');
