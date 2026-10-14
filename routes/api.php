<?php

use App\Http\Controllers\Api\PoemPlayController;
use App\Http\Controllers\Api\PoemPlayerController;
use App\Http\Controllers\Api\PoemSearchController;
use App\Http\Controllers\Api\RandomPoemController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Player API (prefix: /api)
|--------------------------------------------------------------------------
| Consumed by the Vue Shorts player via fetch/XHR (same origin).
*/

// Shuffle: a random poem, optionally excluding the one being listened to.
// Declared before "poems/{poem}" so "random" is never treated as a slug.
Route::get('poems/random', RandomPoemController::class)
    ->middleware('throttle:120,1')
    ->name('api.poems.random');

// Search: what the header icon's modal asks for.
Route::get('search', PoemSearchController::class)
    ->middleware('throttle:120,1')
    ->name('api.search');

Route::get('poems/{poem}', [PoemPlayerController::class, 'show'])
    ->name('api.poems.player');

// Listen counter: posted by the players when playback starts.
Route::post('poems/{poem}/play', PoemPlayController::class)
    ->middleware('throttle:60,1')
    ->name('api.poems.play');

Route::post('poems/{poem}/generate', [PoemPlayerController::class, 'generate'])
    ->middleware('throttle:10,1')
    ->name('api.poems.generate');
