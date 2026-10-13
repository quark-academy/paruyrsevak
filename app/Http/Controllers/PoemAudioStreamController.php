<?php

namespace App\Http\Controllers;

use App\Models\Poem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams a poem recording.
 *
 * The files live outside the web root, so this signed route is the only way to
 * reach them (see config/filesystems.php, disk "audio"):
 *
 *  - the URL carries a signature and expires (poems.audio.url_ttl);
 *  - it is tied to the browser that rendered the page (poems.audio.bind_user_agent),
 *    so a copied link stops working elsewhere;
 *  - streamed from another site is refused (poems.audio.check_origin);
 *  - the response is served inline (no download prompt) with range support, so
 *    seeking works while "save as" has nothing to grab.
 *
 * Note: a determined visitor can still record the stream from the browser —
 * that is impossible to prevent — but the file itself is never downloadable.
 */
class PoemAudioStreamController extends Controller
{
    public function __invoke(Request $request, Poem $poem): BinaryFileResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'Ձայնի հղումը ժամկետանց է կամ սխալ է։');

        $this->guardVisitor($request);

        $path = $poem->audioFilePath();

        abort_if($path === null, 404);

        return response()->file($path, [
            'Content-Type' => $this->mimeType($path),
            'Content-Disposition' => 'inline; filename="'.(Str::slug((string) $poem->title) ?: 'poem').'.mp3"',
            'Cache-Control' => 'private, max-age=600',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }

    /**
     * Reject links used somewhere else than the site they were made for.
     */
    protected function guardVisitor(Request $request): void
    {
        if (config('poems.audio.bind_user_agent', true)) {
            $expected = (string) $request->query('u', '');

            abort_unless(
                $expected !== '' && hash_equals(Poem::visitorHash(), $expected),
                403,
                'Այս հղումը կապված է այլ դիտարկիչի հետ։',
            );
        }

        if (! config('poems.audio.check_origin', true)) {
            return;
        }

        // Referer/Origin is missing when the URL is opened directly — the
        // signature is the only guard then, which is fine (it still expires).
        $origin = (string) ($request->headers->get('Origin') ?: $request->headers->get('Referer') ?: '');

        if ($origin === '') {
            return;
        }

        abort_unless(
            parse_url($origin, PHP_URL_HOST) === $request->getHost(),
            403,
            'Ձայնը հասանելի է միայն կայքի ներսից։',
        );
    }

    /**
     * Content type of the recording (audio files only).
     */
    protected function mimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return [
            'mp3' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'aac' => 'audio/aac',
            'ogg' => 'audio/ogg',
            'opus' => 'audio/ogg',
            'wav' => 'audio/wav',
            'flac' => 'audio/flac',
        ][$extension] ?? 'audio/mpeg';
    }
}
