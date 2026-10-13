<?php

use App\Http\Controllers\DiagnosticsController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PoemAudioStreamController;
use App\Http\Controllers\PoemController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PoemController::class, 'index'])->name('home');

Route::get('/poems', [PoemController::class, 'index'])->name('poems.index');
Route::get('/poems/{poem}', [PoemController::class, 'show'])->name('poems.show');

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
