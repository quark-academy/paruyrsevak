<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * The single public page with the usage terms and the data the site collects.
 */
class LegalController extends Controller
{
    public function terms(): View
    {
        return view('legal.terms');
    }
}
