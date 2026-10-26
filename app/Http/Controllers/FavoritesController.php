<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * "Իմ ընտրանին": the list itself is built in the browser from localStorage,
 * so the page only renders its shell and the empty state.
 */
class FavoritesController extends Controller
{
    public function index(): View
    {
        return view('favorites.index');
    }
}
