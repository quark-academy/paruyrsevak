<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Signed URLs that survive a proxy in front of the site.
 *
 * A signed URL is a signature over the whole address of the request, so it
 * breaks the moment the address differs between the page and the origin:
 *
 *   page:   https://պոեզիա.հայ/audio/hayastan?expires=…&signature=…
 *   origin: https://lab.quark.am/sevak/audio/hayastan?expires=…&signature=…
 *
 * Laravel compares the signature with the URL it sees, so behind a Cloudflare
 * Worker every recording answered 403. The signature is therefore verified
 * once more against the address the visitor actually used (APP_URL + path),
 * which is exactly what was signed. Everything else is unchanged: the URL
 * expires and stays bound to the browser that rendered the page.
 */
class SignedUrl
{
    /**
     * Build a temporary signed URL for a route, exactly as Laravel does.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function temporary(string $route, DateTimeInterface $expiration, array $parameters = []): string
    {
        return URL::temporarySignedRoute($route, $expiration, $parameters);
    }

    /**
     * Whether the request carries a valid, unexpired signature.
     *
     * The plain check comes first (no proxy, or a proxy that keeps the host and
     * the path); the public-URL check covers a Worker that rewrites both.
     */
    public static function isValid(Request $request): bool
    {
        if (! URL::signatureHasNotExpired($request)) {
            return false;
        }

        return URL::hasValidSignature($request)
            || URL::hasValidSignature($request, false)
            || self::matchesPublicUrl($request);
    }

    /**
     * Compare the signature with the address the visitor opened.
     */
    protected static function matchesPublicUrl(Request $request): bool
    {
        $signature = $request->query('signature');

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        $base = rtrim((string) config('app.url'), '/');

        if ($base === '') {
            return false;
        }

        $path = '/'.ltrim($request->path(), '/');
        $original = rtrim($base.$path.'?'.self::queryWithoutSignature($request), '?');

        return hash_equals(
            hash_hmac('sha256', $original, (string) config('app.key')),
            $signature,
        );
    }

    /**
     * The query string as sent, without the signature parameter itself.
     */
    protected static function queryWithoutSignature(Request $request): string
    {
        $query = (string) $request->server->get('QUERY_STRING', '');
        $parts = [];

        foreach (explode('&', $query) as $part) {
            if ($part === '' || str_starts_with($part, 'signature=')) {
                continue;
            }

            $parts[] = $part;
        }

        return implode('&', $parts);
    }
}
