<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Makes subdirectory deployments work (e.g. https://example.am/sevak).
 *
 * When the document root is a symlink to the "public" directory (typical for
 * shared hosting where the project lives inside public_html), PHP-FPM reports
 * SCRIPT_NAME as "/sevak/public/index.php". Symfony then cannot derive the base
 * path, so Laravel assumes the application sits at the domain root and treats
 * "/sevak" as part of the route path — the catch-all route "/{poem}" starts
 * matching, the home page breaks, etc.
 *
 * The real base path is taken from APP_URL, so the fix is configuration driven:
 *   APP_URL=https://lab.quark.am/sevak  ->  base path "/sevak"
 *
 * With no path in APP_URL (root deployments, local dev, tests) nothing changes.
 */
class RequestBasePath
{
    public static function apply(Request $request, ?string $appUrl = null): Request
    {
        $basePath = rtrim((string) parse_url((string) ($appUrl ?? config('app.url')), PHP_URL_PATH), '/');

        if ($basePath === '') {
            return $request;
        }

        // Symfony computes the base path from SCRIPT_NAME / PHP_SELF, so we
        // present the front controller as if it lived right in the base path.
        $request->server->set('SCRIPT_NAME', $basePath.'/index.php');
        $request->server->set('PHP_SELF', $basePath.'/index.php');
        $request->server->set('SCRIPT_FILENAME', public_path('index.php'));

        return $request;
    }
}
