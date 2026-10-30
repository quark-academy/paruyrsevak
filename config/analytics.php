<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Analytics 4
    |--------------------------------------------------------------------------
    |
    | The measurement ID is injected by partials/analytics.blade.php into every
    | public document (Blade layouts and the standalone Vue player). An empty
    | ID renders no tag at all, so analytics can be switched off per server
    | without touching the views.
    |
    */

    'google_analytics_id' => env('GOOGLE_ANALYTICS_ID', 'G-QJFES4FMPK'),

    'enabled' => filter_var(env('GOOGLE_ANALYTICS_ENABLED', true), FILTER_VALIDATE_BOOL),

];
