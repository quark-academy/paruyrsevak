<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PoemAudioController;
use App\Http\Controllers\Admin\PoemController;
use App\Http\Controllers\Admin\PoemWordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin panel routes (prefix: /admin, name prefix: admin.)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::middleware(['auth', 'admin'])->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::resource('poems', PoemController::class);

    // Audio management
    Route::post('poems/{poem}/audio', [PoemAudioController::class, 'store'])->name('poems.audio.store');
    Route::post('poems/{poem}/audio/generate', [PoemAudioController::class, 'generate'])->name('poems.audio.generate');
    Route::post('poems/{poem}/audio/align', [PoemAudioController::class, 'align'])->name('poems.audio.align');
    Route::delete('poems/{poem}/audio', [PoemAudioController::class, 'destroy'])->name('poems.audio.destroy');

    // Karaoke word timings
    Route::get('poems/{poem}/timings', [PoemWordController::class, 'edit'])->name('poems.timings');
    Route::post('poems/{poem}/timings', [PoemWordController::class, 'update'])->name('poems.timings.update');
    Route::post('poems/{poem}/timings/sync', [PoemWordController::class, 'sync'])->name('poems.timings.sync');
});
