<?php

use App\Http\Controllers\Api\PoemPlayController;
use App\Http\Controllers\Api\PoemPlayerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Player API (prefix: /api)
|--------------------------------------------------------------------------
| Consumed by the Vue Shorts player via fetch/XHR (same origin).
*/

Route::get('poems/{poem}', [PoemPlayerController::class, 'show'])
    ->name('api.poems.player');

// Listen counter: posted by the players when playback starts.
Route::post('poems/{poem}/play', PoemPlayController::class)
    ->middleware('throttle:60,1')
    ->name('api.poems.play');

Route::post('poems/{poem}/generate', [PoemPlayerController::class, 'generate'])
    ->middleware('throttle:10,1')
    ->name('api.poems.generate');
