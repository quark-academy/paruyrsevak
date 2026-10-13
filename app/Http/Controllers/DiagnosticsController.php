<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Deployment diagnostics for subdirectory installations.
 *
 * Answers "which base path did the application work out?" together with the
 * values it had to work with, so a hosting quirk can be pinned down without
 * shell access. Available only while APP_DEBUG is on.
 */
class DiagnosticsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(config('app.debug'), 404);

        return response()->json([
            'base_path' => rtrim((string) config('poems.base_path', ''), '/') ?: null,
            'app_url' => (string) config('app.url'),
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
        ], 200, [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
