<?php

namespace App\Http\Controllers;

use App\Support\Seo;
use Illuminate\Http\Response;

/**
 * robots.txt is generated as well, so the sitemap URL always matches the
 * domain (and the subdirectory) the site is actually served from.
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            // The player and the search endpoints are not content of their own.
            'Disallow: /api/',
            'Disallow: /admin',
            'Disallow: /__diagnostics',
            '',
            'Sitemap: '.Seo::canonical('sitemap.xml'),
            '',
        ];

        return response(implode("\n", $lines))
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
