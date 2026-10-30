{{--
    Google Analytics 4 (gtag.js).

    Included by both public documents:
      - layouts/app.blade.php — catalogue, poem page, favourites, terms
      - poems/short.blade.php — the Vue player, which renders its own <head>

    The ID lives in config/analytics.php. The player swaps poems in place
    without reloading, so it reports those views itself
    (resources/js/analytics.js) — the snippet below only sends the first one.
--}}
@php
    $gaId = config('analytics.enabled') ? config('analytics.google_analytics_id') : null;
@endphp

@if ($gaId)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', @json($gaId));
    </script>
@endif
