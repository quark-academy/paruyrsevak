<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Shared hosting (project inside public_html, served from a
        // subdirectory): force the configured root URL and HTTPS scheme so
        // route()/url() never emit "/public" or wrong-scheme links.
        if (config('app.force_root_url') && filled(config('app.url'))) {
            URL::forceRootUrl((string) config('app.url'));

            if (str_starts_with((string) config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
        }
    }
}
