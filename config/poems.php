<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Canonical base URL
    |--------------------------------------------------------------------------
    |
    | Canonical links are always built from this domain, e.g.
    | https://subdomain.domain.am/{slug}. Configure it through the
    | POEMS_CANONICAL_BASE environment variable in production.
    |
    */

    'canonical_base' => rtrim((string) env('POEMS_CANONICAL_BASE', env('APP_URL', 'http://localhost')), '/'),

    /*
    |--------------------------------------------------------------------------
    | Short URLs (Shorts style)
    |--------------------------------------------------------------------------
    |
    | When enabled a poem is also reachable through "/{slug}" (in addition to
    | "/poems/{slug}") and the short URL becomes the canonical one.
    |
    */

    'short_urls' => filter_var(env('POEMS_SHORT_URLS', true), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Public AI generation
    |--------------------------------------------------------------------------
    |
    | Allows the public player to trigger ElevenLabs generation on demand.
    | Keep it disabled unless you accept the API cost on public traffic.
    |
    */

    'public_generation' => filter_var(env('POEMS_PUBLIC_GENERATION', false), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Shorts player defaults
    |--------------------------------------------------------------------------
    */

    'player' => [
        'autoplay' => filter_var(env('POEMS_AUTOPLAY', false), FILTER_VALIDATE_BOOL),
        'background' => env('POEMS_BACKGROUND', '#0b0a09'),
        'gold' => env('POEMS_GOLD', '#e8c46a'),
    ],

];
