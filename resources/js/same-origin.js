/**
 * The address the page itself means.
 *
 * A generated URL may carry another scheme or host — behind a proxy the server
 * can believe it is on http while the visitor is on https — and the browser then
 * refuses the call outright (mixed content, then CORS). Safari even ignores the
 * URL protocol setter, so the fix is not to patch the URL but to drop scheme and
 * host altogether: a relative path is always resolved against the page the
 * visitor is already on. Path and query are kept exactly as generated.
 */
export function sameOrigin(url) {
    try {
        const target = new URL(url, window.location.href);

        return target.pathname + target.search;
    } catch (error) {
        return url;
    }
}
