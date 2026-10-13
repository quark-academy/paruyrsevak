<?php

use App\Http\Controllers\PoemController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PoemController::class, 'index'])->name('home');

Route::get('/poems', [PoemController::class, 'index'])->name('poems.index');
Route::get('/poems/{poem}', [PoemController::class, 'show'])->name('poems.show');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
