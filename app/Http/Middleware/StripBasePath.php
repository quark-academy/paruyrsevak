<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared hosting: the document root is usually a symlink to the "public"
 * directory, so the application is reachable at https://domain/sevak while PHP
 * reports SCRIPT_NAME as "/sevak/public/index.php". Symfony then cannot derive
 * the base path, Laravel assumes the app sits at the domain root and treats
 * "/sevak" as part of the route path — the catch-all route "/{poem}" starts
 * matching, the home page breaks with "404" / "Missing required parameter".
 *
 * This middleware runs first (global, prepended) and strips the configured base
 * path (taken from APP_URL) from REQUEST_URI, so routing sees the usual "/" and
 * "/demo-ashun". With no path in APP_URL (root deployments, local dev, tests)
 * nothing changes.
 */
class StripBasePath
{
    public function handle(Request $request, Closure $next): Response
    {
        $basePath = rtrim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');

        if ($basePath !== '') {
            $uri = $request->server->get('REQUEST_URI');

            if (is_string($uri) && str_starts_with($uri, $basePath)) {
                $rest = substr($uri, strlen($basePath));

                // Keep the leading slash (and the query string untouched).
                if ($rest === '' || $rest[0] !== '/') {
                    $rest = '/'.$rest;
                }

                $request->server->set('REQUEST_URI', $rest);
            }
        }

        return $next($request);
    }
}
