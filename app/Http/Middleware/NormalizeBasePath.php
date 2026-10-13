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
     * Order of trust: the explicit POEMS_BASE_PATH setting, the path in
     * APP_URL, the location the web server reports for the front controller and
     * finally the project folder name (the folder that holds the application is
     * normally the folder in the URL).
     */
    protected function basePath(Request $request): string
    {
        $configured = rtrim((string) config('poems.base_path', ''), '/');

        if ($configured !== '') {
            return $configured;
        }

        $fromUrl = rtrim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');

        if ($fromUrl !== '') {
            return $fromUrl;
        }

        $detected = $this->detectBasePath($request);

        return $detected !== '' ? $detected : $this->detectFromProjectFolder($request);
    }

    /**
     * Last resort: the URL prefix equals the application folder name
     * (e.g. ~/lab.quark.am/sevak served as https://host/sevak).
     */
    protected function detectFromProjectFolder(Request $request): string
    {
        $folder = basename(str_replace('\\', '/', base_path()));

        if ($folder === '' || str_contains($folder, '.')) {
            return '';
        }

        $uri = '/'.trim((string) strtok((string) $request->server->get('REQUEST_URI', ''), '?'), '/');

        return ($uri === '/'.$folder || str_starts_with($uri, '/'.$folder.'/')) ? '/'.$folder : '';
    }

    /**
     * Derive the base path from the script location reported by the web server.
     */
    protected function detectBasePath(Request $request): string
    {
        $uri = '/'.trim((string) strtok((string) $request->server->get('REQUEST_URI', ''), '?'), '/');

        foreach (['SCRIPT_NAME', 'PHP_SELF', 'ORIG_SCRIPT_NAME'] as $key) {
            $directory = $this->directoryOf((string) $request->server->get($key, ''));

            if ($directory !== null && ($uri === $directory || str_starts_with($uri, $directory.'/'))) {
                return $directory;
            }
        }

        return $this->basePathFromFileLocation($request, $uri);
    }

    /**
     * Base path from the on-disk location of the front controller.
     *
     * Shared hosting layout: DOCUMENT_ROOT=/home/user/lab.quark.am/public_html,
     * the project in public_html/sevak (a symlink to its public directory) and
     * the front controller at public_html/sevak/index.php. PHP-FPM reports
     * SCRIPT_NAME=/index.php there, so the prefix cannot be read from the script
     * name — but the first segment of the script path relative to the document
     * root ("sevak") is exactly the URL prefix.
     */
    protected function basePathFromFileLocation(Request $request, string $uri): string
    {
        $documentRoot = rtrim(str_replace('\\', '/', (string) $request->server->get('DOCUMENT_ROOT', '')), '/');
        $script = str_replace('\\', '/', (string) $request->server->get('SCRIPT_FILENAME', ''));

        if ($documentRoot === '' || $script === '' || ! str_starts_with($script, $documentRoot.'/')) {
            return '';
        }

        $relative = trim(substr($script, strlen($documentRoot)), '/');
        $directory = $this->directoryOf($relative);

        if ($directory === null || $directory === '/public') {
            return '';
        }

        return ($uri === $directory || str_starts_with($uri, $directory.'/')) ? $directory : '';
    }

    /**
     * Normalise a script (or app-relative) path into "/segment" — the first
     * directory above the front controller, or null when there is none.
     */
    protected function directoryOf(string $script): ?string
    {
        if ($script === '') {
            return null;
        }

        $directory = rtrim(str_replace('\\', '/', dirname($script)), '/');

        // ".../sevak/public/index.php" (docroot symlinked to public/).
        if (str_ends_with($directory, '/public')) {
            $directory = substr($directory, 0, -7);
        }

        if ($directory === '' || $directory === '.' || $directory === '/' || str_contains($directory, '.')) {
            return null;
        }

        return str_starts_with($directory, '/') ? $directory : '/'.$directory;
    }
}
