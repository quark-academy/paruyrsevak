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
    /**
     * Recordings (and any audio) are never served from here: they only leave
     * the server through the signed route "poems.audio".
     */
    protected const PROTECTED_EXTENSIONS = ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'opus', 'flac', 'mpga'];

    /**
     * Folders that used to be linked publicly for the players.
     */
    protected const PROTECTED_DIRECTORIES = ['original_audio', 'ai_audio'];

    public function __invoke(string $path): StreamedResponse
    {
        // Never allow path traversal (Flysystem would throw as well, but we
        // prefer a clean 404 over a 500).
        abort_if(str_contains($path, '..'), 404);

        abort_if($this->isProtected($path), 404, 'Աուդիոն հասանելի է միայն նվագարկիչի միջոցով։');

        $disk = Storage::disk('public');

        abort_unless($disk->exists($path), 404);

        return $disk->response($path);
    }

    /**
     * Whether the requested path looks like a recording.
     */
    protected function isProtected(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, self::PROTECTED_EXTENSIONS, true)) {
            return true;
        }

        $segments = array_map('strtolower', explode('/', trim($path, '/')));

        return array_intersect($segments, self::PROTECTED_DIRECTORIES) !== [];
    }
}
