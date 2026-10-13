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
    | Listen counter
    |--------------------------------------------------------------------------
    |
    | The players POST to /api/poems/{slug}/play as soon as the recording
    | starts. Repeated plays by the same visitor inside this window are
    | ignored, so refreshing a page does not inflate the counter.
    | Set to 0 to count every single play request.
    |
    */

    'plays' => [
        'dedupe_seconds' => (int) env('POEMS_PLAYS_DEDUPE_SECONDS', 1800),
    ],

    /*
    |--------------------------------------------------------------------------
    | Shorts player defaults
    |--------------------------------------------------------------------------
    |
    | autoplay starts the recording as soon as the player page opens. Every
    | visitor can turn it off (and switch repeat / shuffle on) right inside
    | the player: the choice is stored in the browser and survives navigation
    | between poems, so "autoplay + shuffle" behaves like a radio stream.
    |
    */

    'player' => [
        'autoplay' => filter_var(env('POEMS_AUTOPLAY', true), FILTER_VALIDATE_BOOL),
        'background' => env('POEMS_BACKGROUND', '#0b0a09'),
        'gold' => env('POEMS_GOLD', '#e8c46a'),
    ],

];
