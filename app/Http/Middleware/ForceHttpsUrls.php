<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keep generated URLs on https:// whenever the visitor is on https://.
 *
 * Behind Cloudflare (and on most shared hosting) TLS ends at the proxy, so the
 * origin sees a plain http request: route(), asset() and the paginator then hand
 * out http:// links. The browser refuses every fetch made with such a link —
 * mixed content, then CORS — and everything that depends on it (the next poem
 * in the player, the search modal, the catalogue button) stops working without
 * a single server-side error.
 */
class ForceHttpsUrls
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->visitorIsOnHttps($request)) {
            URL::forceScheme('https');
        }

        return $next($request);
    }

    /**
     * The protocol the visitor actually uses, read through the proxy in front.
     */
    protected function visitorIsOnHttps(Request $request): bool
    {
        if ($request->isSecure()) {
            return true;
        }

        // Cloudflare sends X-Forwarded-Proto; CF-Visitor carries the scheme as
        // well, which helps when a layer in between rewrites the first one.
        foreach (['X-Forwarded-Proto', 'CF-Visitor'] as $header) {
            if (str_contains(strtolower((string) $request->header($header, '')), 'https')) {
                return true;
            }
        }

        return str_starts_with((string) config('app.url'), 'https://');
    }
}
