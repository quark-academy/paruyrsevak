<?php

namespace App\Support;

/**
 * URL configuration of a deployment.
 *
 * A site served from a subdirectory behind a proxy (Cloudflare Worker) has
 * three URLs that must agree with each other:
 *
 *   APP_URL               public root, e.g. https://պոեզիա.հայ
 *   ASSET_URL             where "public/" is reachable, e.g. https://պոեզիա.հայ/sevak
 *   POEMS_CANONICAL_BASE  what crawlers see, normally the same as APP_URL
 *
 * A single wrong host there is enough to lose all styles and scripts: the
 * browser then asks a hostname that does not exist. Two rules of this class
 * keep such a deployment alive:
 *
 *   1. A Unicode domain pasted into the configuration ("պոեզիա.հայ") is
 *      converted to its punycode form, because that is what URLs must carry.
 *   2. ASSET_URL is derived from APP_URL and the base path when it is missing.
 */
class UrlConfig
{
    /**
     * Convert a Unicode hostname inside a URL to punycode.
     *
     * "https://պոեզիա.հայ/sevak" becomes
     * "https://xn--y9ahdu6dl.xn--y9a3aq/sevak". ASCII URLs are returned as is.
     */
    public static function normalize(string $url): string
    {
        $url = trim($url);

        if ($url === '' || preg_match('/[\x80-\xFF]/', $url) !== 1) {
            return $url;
        }

        // Scheme, then everything up to the path: "https://պոեզիա.հայ/sevak".
        // A regex is used instead of parse_url(), which rejects a Unicode host
        // and would let the unencoded name reach the URL generator.
        if (preg_match('#^([a-z][a-z0-9+.\-]*://)([^/?\#]*)(.*)$#i', $url, $matches) !== 1) {
            return $url;
        }

        [, $scheme, $authority, $rest] = $matches;

        $userinfo = '';

        if (($at = strrpos($authority, '@')) !== false) {
            $userinfo = substr($authority, 0, $at + 1);
            $authority = substr($authority, $at + 1);
        }

        [$host, $port] = self::splitPort($authority);

        return $scheme.$userinfo.self::asciiHost($host).$port.$rest;
    }

    /**
     * Split "host:8080" (or "[::1]:8080") into host and port suffix.
     *
     * @return array{0: string, 1: string}
     */
    protected static function splitPort(string $authority): array
    {
        if (preg_match('/^(\[[^\]]+\]|[^:]+)(:\d+)?$/', $authority, $parts) === 1) {
            return [$parts[1], $parts[2] ?? ''];
        }

        return [$authority, ''];
    }

    /**
     * Rewrite the configured URLs from Unicode to punycode.
     *
     * Runs right after the configuration is loaded (see bootstrap/app.php),
     * because the console kernel builds a request out of APP_URL before any
     * provider is registered.
     */
    public static function normalizeConfiguredUrls(): void
    {
        foreach (['app.url', 'app.asset_url', 'poems.canonical_base'] as $key) {
            $value = config($key);

            if (is_string($value) && $value !== '') {
                config([$key => self::normalize($value)]);
            }
        }
    }

    /** Hostname of a URL, or null when it cannot be read. */
    public static function host(string $url): ?string
    {
        $parts = parse_url($url);

        return is_array($parts) && filled($parts['host'] ?? null) ? (string) $parts['host'] : null;
    }

    /** ASCII (punycode) form of a hostname; the input itself when it already is one. */
    public static function asciiHost(string $host): string
    {
        if (preg_match('/[\x80-\xFF]/', $host) !== 1) {
            return $host;
        }

        if (! function_exists('idn_to_ascii')) {
            return $host;
        }

        $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

        return is_string($ascii) && $ascii !== '' ? $ascii : $host;
    }

    /**
     * Readable form of a punycode hostname, or null when it is plain ASCII.
     *
     * Used in hints: "xn--y9a3af0b3a" turns out to be "ղցջհա", and that alone
     * explains why nothing loads.
     */
    public static function unicodeHost(?string $host): ?string
    {
        if ($host === null || $host === '' || ! str_contains($host, 'xn--')) {
            return null;
        }

        if (! function_exists('idn_to_utf8')) {
            return null;
        }

        $unicode = idn_to_utf8($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

        return is_string($unicode) && $unicode !== $host ? $unicode : null;
    }

    /**
     * Subdirectory the application is served from: the explicit setting first,
     * then the path of APP_URL.
     */
    public static function basePath(?string $url = null): string
    {
        $configured = rtrim((string) config('poems.base_path', ''), '/');

        if ($configured !== '') {
            return $configured;
        }

        $path = (string) parse_url($url ?? (string) config('app.url'), PHP_URL_PATH);

        return rtrim($path, '/');
    }

    /**
     * Give ASSET_URL a value when the deployment forgot it.
     *
     * Assets are the only thing served from the folder "public", so they keep
     * the base path while the routes do not: that is exactly why a single
     * APP_URL is not enough on shared hosting.
     */
    public static function deriveAssetUrl(?string $basePath = null): void
    {
        if (filled(config('app.asset_url'))) {
            return;
        }

        $root = rtrim((string) config('app.url'), '/');
        $base = rtrim($basePath ?? self::basePath(), '/');

        // Without a subdirectory nothing has to be added: asset() already uses
        // the root of the request, which keeps the scheme the visitor really
        // uses (behind a proxy that is https while the origin sees http).
        if ($root === '' || $base === '') {
            return;
        }

        config([
            'app.asset_url' => str_ends_with($root, $base) ? $root : $root.$base,
        ]);
    }

    /**
     * Whether a hostname exists in DNS.
     *
     * Returns null when the resolver itself is unavailable, so a restricted
     * environment is not reported as "your domain is missing". The lookup is
     * time-bounded (see DnsLookup): the system resolver waits a full minute for
     * a name that does not exist, and a broken name is exactly the case to
     * report quickly.
     *
     * @param  (callable(string): array{status: string, addresses: list<string>})|null  $lookup
     */
    public static function resolves(string $host, ?callable $lookup = null): ?bool
    {
        if ($host === '') {
            return null;
        }

        static $cache = [];
        $key = strtolower($host);

        if ($lookup === null && array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $lookup ??= static fn (string $name): array => DnsLookup::a($name);

        $answer = $lookup($host);
        $status = $answer['status'] ?? DnsLookup::UNAVAILABLE;

        $result = match ($status) {
            DnsLookup::OK => true,
            DnsLookup::NXDOMAIN => false,
            default => null,
        };

        if ($lookup === null) {
            $cache[$key] = $result;
        }

        return $result;
    }

    /**
     * Addresses of a hostname, as strings.
     *
     * @return list<string>
     */
    public static function records(string $host): array
    {
        return DnsLookup::a($host)['addresses'];
    }
}
