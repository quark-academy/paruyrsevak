<?php

namespace App\Http\Controllers;

use App\Models\Poem;
use App\Support\SignedUrl;
use App\Support\UrlConfig;
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
    /**
     * User agents of native media loaders that fetch <audio> outside the
     * browser process. They are not the browser that rendered the page, so a
     * signature bound to the page's User-Agent can never match there.
     */
    protected const MEDIA_STACK_AGENTS = ['AppleCoreMedia/', 'stagefright/', 'ExoPlayer'];

    public function __invoke(Request $request, Poem $poem): BinaryFileResponse
    {
        abort_unless(SignedUrl::isValid($request), 403, 'Ձայնի հղումը ժամկետանց է կամ սխալ է։');

        $this->guardVisitor($request);

        $path = $poem->audioFilePath();

        abort_if($path === null, 404);

        return response()->file($path, [
            'Content-Type' => $this->mimeType($path),
            'Content-Disposition' => 'inline; filename="'.(Str::slug((string) $poem->title) ?: 'poem').'.mp3"',
            // A CDN in front of the site caches .mp3 by its own rules; the
            // recording belongs to the visitor whose signature it carries.
            'CDN-Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ])
            // Symfony marks a file response public by default and overrides
            // any Cache-Control header given above; the browser may keep the
            // stream for seeking, a shared cache never does.
            ->setPrivate()
            ->setMaxAge(600);
    }

    /**
     * Reject links used somewhere else than the site they were made for.
     */
    protected function guardVisitor(Request $request): void
    {
        if (config('poems.audio.bind_user_agent', true) && ! $this->isMediaStackRequest($request)) {
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

        // Media stacks often send "Origin: null" (or no Origin at all) while
        // the Referer still names the page. Check every usable value instead
        // of trusting just the first header: a literal "null" must not shadow
        // a valid Referer and lock the recording.
        $origins = array_filter([
            trim((string) $request->headers->get('Origin')),
            trim((string) $request->headers->get('Referer')),
        ], static fn (string $origin): bool => $origin !== '' && strcasecmp($origin, 'null') !== 0);

        $allowed = $this->allowedHosts($request);

        // No Origin/Referer at all: the signature is the only guard, which is
        // fine — the link still has to be valid and expires.
        foreach ($origins as $origin) {
            $host = parse_url($origin, PHP_URL_HOST);

            // A browser may send a Unicode Referer for an IDN domain while the
            // configured hosts are punycode: compare the ASCII form of both.
            abort_unless(
                is_string($host) && in_array(UrlConfig::asciiHost(strtolower($host)), $allowed, true),
                403,
                'Ձայնը հասանելի է միայն կայքի ներսից։',
            );
        }
    }

    /**
     * Hosts a recording may be requested from.
     *
     * Behind a Cloudflare Worker the visitor's Origin/Referer names the public
     * domain while the origin sees its own host, so both count as the same
     * site: the request host, the configured APP_URL host and the canonical
     * host. X-Forwarded-Host is honoured too, when the proxy sends it.
     *
     * @return list<string>
     */
    protected function allowedHosts(Request $request): array
    {
        $hosts = [UrlConfig::asciiHost(strtolower($request->getHost()))];

        foreach (['app.url', 'poems.canonical_base'] as $key) {
            $host = parse_url((string) config($key), PHP_URL_HOST);

            if (is_string($host) && $host !== '') {
                $hosts[] = UrlConfig::asciiHost(strtolower($host));
            }
        }

        foreach (['X-Forwarded-Host', 'X-Original-Host'] as $header) {
            $host = trim((string) $request->headers->get($header));

            if ($host !== '') {
                // A list may name several proxies; the first is the visitor's.
                $hosts[] = UrlConfig::asciiHost(strtolower(trim(explode(',', $host)[0])));
            }
        }

        return array_values(array_unique(array_map('strtolower', array_filter($hosts))));
    }

    /**
     * Whether the request comes from the native media loader rather than from
     * the browser itself.
     *
     * iOS Safari fetches <audio> in a separate process and identifies it as
     * "AppleCoreMedia/…". The signed URL was built from the Safari User-Agent,
     * so the hash comparison rejected every recording on iPhone (iOS 17+,
     * including iPhone 17) before the file was even opened. Such requests
     * still need a valid, unexpired signature and a same-origin referer when
     * one is present.
     */
    protected function isMediaStackRequest(Request $request): bool
    {
        $agent = (string) $request->userAgent();

        foreach (self::MEDIA_STACK_AGENTS as $needle) {
            if (stripos($agent, $needle) !== false) {
                return true;
            }
        }

        return false;
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
