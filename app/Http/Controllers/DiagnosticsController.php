<?php

namespace App\Http\Controllers;

use App\Support\Seo;
use App\Support\UrlConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * Deployment diagnostics for subdirectory installations.
 *
 * Answers "which base path did the application work out?" together with the
 * values it had to work with, so a hosting quirk can be pinned down without
 * shell access. The URLs block is the browser-side twin of "php artisan
 * deploy:check": it shows the host names the page tells the browser to fetch,
 * whether they exist in DNS, and whether the built files are on disk.
 *
 * Available only while APP_DEBUG is on — turn it off in production.
 */
class DiagnosticsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(config('app.debug'), 404);

        $app = (string) config('app.url');
        $asset = (string) config('app.asset_url');
        $canonical = (string) config('poems.canonical_base');

        return response()->json([
            'base_path' => rtrim((string) config('poems.base_path', ''), '/') ?: null,
            'app_url' => $app,
            'request_uri' => (string) $request->server->get('REQUEST_URI', ''),
            'script_name' => (string) $request->server->get('SCRIPT_NAME', ''),
            'php_self' => (string) $request->server->get('PHP_SELF', ''),
            'script_filename' => (string) $request->server->get('SCRIPT_FILENAME', ''),
            'document_root' => (string) $request->server->get('DOCUMENT_ROOT', ''),
            'base_url' => $request->getBaseUrl(),
            'path_info' => $request->getPathInfo(),
            'route' => optional($request->route())->getName(),
            'app_path' => base_path(),
            'public_path' => public_path(),
            'urls' => [
                'app' => $app !== '' ? $app : null,
                'asset' => $asset !== '' ? $asset : null,
                'canonical' => $canonical !== '' ? $canonical : null,
                'hosts_match' => UrlConfig::host($app) === UrlConfig::host($asset),
                'dns' => $this->dns([$app, $asset]),
                'assets' => $this->assets(),
            ],
            'crawlers' => [
                'sitemap' => Seo::canonical('sitemap.xml'),
                'robots' => Seo::canonical('robots.txt'),
            ],
        ], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Hostname of each configured URL and whether it exists.
     *
     * @param  list<string>  $urls
     * @return array<string, array{host: string, unicode: string|null, resolves: bool|null}>
     */
    protected function dns(array $urls): array
    {
        $result = [];

        foreach ($urls as $url) {
            $host = UrlConfig::host($url);

            if ($host === null || array_key_exists($host, $result)) {
                continue;
            }

            $result[$host] = [
                'host' => $host,
                'unicode' => UrlConfig::unicodeHost($host),
                'resolves' => UrlConfig::resolves($host),
            ];
        }

        return $result;
    }

    /**
     * @return array{manifest: bool, files: int, missing: list<string>}
     */
    protected function assets(): array
    {
        $manifest = public_path('build/manifest.json');

        if (! File::exists($manifest)) {
            return ['manifest' => false, 'files' => 0, 'missing' => []];
        }

        $decoded = json_decode((string) File::get($manifest), true);
        $files = [];
        $missing = [];

        foreach (is_array($decoded) ? $decoded : [] as $entry) {
            foreach (['file', 'css', 'assets'] as $key) {
                foreach ((array) ($entry[$key] ?? []) as $file) {
                    if (! is_string($file) || $file === '') {
                        continue;
                    }

                    $files[] = $file;

                    if (! File::exists(public_path('build/'.$file))) {
                        $missing[] = $file;
                    }
                }
            }
        }

        return [
            'manifest' => true,
            'files' => count(array_unique($files)),
            'missing' => array_values(array_unique($missing)),
        ];
    }
}
