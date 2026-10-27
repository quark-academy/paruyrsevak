<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\ForceHttpsUrls;
use App\Http\Middleware\NormalizeBasePath;
use App\Support\UrlConfig;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));

            // Short "/{slug}" URLs are registered last so that /admin, /poems
            // and other reserved prefixes are matched first.
            Route::middleware('web')->group(base_path('routes/short.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Must run before anything reads the request path (see the middleware).
        $middleware->prepend(NormalizeBasePath::class);

        // Then the *first* one in the stack: prepend() puts every new entry in
        // front of the previous ones, so this runs before the base path is
        // fixed — and before anything can build a URL or a redirect. Everything
        // below must speak the scheme the visitor actually uses: a redirect
        // handed out as http:// from an https:// page is blocked by the browser
        // (mixed content, then CORS).
        $middleware->prepend(ForceHttpsUrls::class);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

/*
| A custom domain is often typed the way it is written ("պոեզիա.հայ"), while a
| URL may only carry punycode. The rewrite has to happen right after the config
| is loaded: the console kernel builds a request out of APP_URL before any
| provider is registered, and Symfony refuses a Unicode host outright.
*/
$app->afterBootstrapping(
    LoadConfiguration::class,
    static fn () => UrlConfig::normalizeConfiguredUrls(),
);

return $app;
