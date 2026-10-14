/**
 * A request URL as this very page sees it.
 *
 * A generated URL may carry another scheme or host — a proxy makes the server
 * believe it is on http while the visitor is on https. The browser then refuses
 * the call (mixed content, then CORS), so every fetch the front-end makes has to
 * stay on the origin the visitor is already on. Path and query are untouched.
 */
export function sameOrigin(url) {
    try {
        const target = new URL(url, window.location.href);

        target.protocol = window.location.protocol;
        target.host = window.location.host;

        return target.toString();
    } catch (error) {
        return url;
    }
}
