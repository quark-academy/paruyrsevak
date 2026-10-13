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
 * from APP_URL (or, when APP_URL has no path, from the script name the server
 * reports). A bare base path request ("/sevak", which the standard
 * "public/.htaccess" produces by stripping the trailing slash) is normalised to
 * the directory request ("/sevak/"). This must run before anything reads the
 * request path, hence the global "prepend" registration in bootstrap/app.php.
 *
 * Root deployments (APP_URL without a path and an "/index.php" script) are left
 * untouched.
 */
class NormalizeBasePath
{
    public function handle(Request $request, Closure $next): Response
    {
        $basePath = $this->basePath($request);

        if ($basePath === '') {
            return $next($request);
        }

        $request->server->set('SCRIPT_NAME', $basePath.'/index.php');
        $request->server->set('PHP_SELF', $basePath.'/index.php');

        $this->normalizeBareBasePath($request, $basePath);

        return $next($request);
    }

    /**
     * A request for the bare base path ("/sevak", no trailing slash) cannot be
     * matched against the script directory, so Symfony drops the base URL and
     * the router sees "/sevak" as a route segment — the catch-all "/{poem}"
     * then answered the home page with the poem whose slug is "sevak".
     *
     * The standard Laravel "public/.htaccess" redirects "/sevak/" to "/sevak"
     * (301), so this is exactly what a visitor typing the site address hits.
     * Treating it as the directory request ("/sevak/") restores the home page.
     */
    protected function normalizeBareBasePath(Request $request, string $basePath): void
    {
        $uri = (string) $request->server->get('REQUEST_URI', '');

        [$path, $query] = array_pad(explode('?', $uri, 2), 2, '');

        if (rtrim($path, '/') !== $basePath) {
            return;
        }

        $request->server->set('REQUEST_URI', $basePath.'/'.($query !== '' ? '?'.$query : ''));
    }

    /**
     * Subdirectory the application is served from.
     *
     * APP_URL wins ("https://host/sevak" -> "/sevak"). When it carries no path
     * (a forgotten suffix, or a config cache built before the URL was fixed)
     * the front controller location reported by the web server is used as a
     * fallback, so a wrong APP_URL degrades to working routing instead of a
     * home page that opens some poem.
     */
    protected function basePath(Request $request): string
    {
        $configured = rtrim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');

        return $configured !== '' ? $configured : $this->detectBasePath($request);
    }

    /**
     * Derive the base path from the script location reported by the web server.
     */
    protected function detectBasePath(Request $request): string
    {
        $uri = (string) strtok((string) $request->server->get('REQUEST_URI', ''), '?');

        foreach (['SCRIPT_NAME', 'PHP_SELF', 'ORIG_SCRIPT_NAME'] as $key) {
            $script = (string) $request->server->get($key, '');

            if ($script === '') {
                continue;
            }

            $directory = rtrim(str_replace('\\', '/', dirname($script)), '/');

            // ".../sevak/public/index.php" (docroot symlinked to public/).
            if (str_ends_with($directory, '/public')) {
                $directory = substr($directory, 0, -7);
            }

            if ($directory === '' || $directory === '.') {
                continue;
            }

            // Trust only a path that the request itself starts with.
            if ($uri === $directory || str_starts_with($uri, $directory.'/')) {
                return $directory;
            }
        }

        return '';
    }
}
