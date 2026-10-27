<?php

namespace App\Providers;

use App\Support\UrlConfig;
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

        // A custom domain is often typed in Unicode ("պոեզիա.հայ"), while a
        // URL may only carry punycode (see UrlConfig::normalizeConfiguredUrls,
        // called from bootstrap/app.php right after the config is loaded).
        // Fills ASSET_URL from APP_URL and the base path when the deployment
        // forgot it — otherwise the browser asks for styles one directory above
        // the folder that holds them.
        UrlConfig::deriveAssetUrl();
    }
}
