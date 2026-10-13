<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves files from the "public" disk through Laravel.
 *
 * Normally `php artisan storage:link` creates public/storage and Apache serves
 * those files directly. On shared hosting with a symlinked document root (or
 * when symlinks are restricted) that link may be missing or blocked, which is
 * why audio files fail with 403/404/500. This route is the fallback: it keeps
 * the very same /storage/... URLs working.
 */
class MediaController extends Controller
{
    public function __invoke(string $path): StreamedResponse
    {
        // Never allow path traversal (Flysystem would throw as well, but we
        // prefer a clean 404 over a 500).
        abort_if(str_contains($path, '..'), 404);

        $disk = Storage::disk('public');

        abort_unless($disk->exists($path), 404);

        return $disk->response($path);
    }
}
