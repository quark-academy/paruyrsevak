<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Subdirectory deployments with a symlinked document root.
 *
 * Typical shared-hosting layout: the project lives in
 * ~/lab.quark.am/public_html/sevak and the site is served from /sevak, where
 * "public_html/sevak" is a symlink to the real "public" directory. PHP-FPM then
 * reports SCRIPT_NAME as "/sevak/public/index.php" and Symfony cannot derive the
 * base path, so Laravel assumes the app sits at the domain root:
 *
 *   - routing breaks ("/sevak" becomes a route segment -> catch-all "/{poem}");
 *   - generated URLs lose the "/sevak" prefix (redirects land on /admin).
 *
 * Fix: tell Symfony the front controller lives directly in the base path taken
 * from APP_URL. This must run before anything reads the request path, hence the
 * global "prepend" registration in bootstrap/app.php.
 *
 * With no path in APP_URL (root deployments, local dev, tests) nothing changes.
 */
class NormalizeBasePath
{
    public function handle(Request $request, Closure $next): Response
    {
        $basePath = rtrim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');

        if ($basePath !== '') {
            $request->server->set('SCRIPT_NAME', $basePath.'/index.php');
            $request->server->set('PHP_SELF', $basePath.'/index.php');
        }

        return $next($request);
    }
}
