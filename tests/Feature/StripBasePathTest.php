<?php

namespace Tests\Feature;

use App\Http\Middleware\StripBasePath;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Subdirectory deployments (docroot symlinked to "public") lose the base path,
 * which made Laravel treat "/sevak" as a route segment and break the home page.
 */
class StripBasePathTest extends TestCase
{
    private function applyMiddleware(Request $request): Request
    {
        (new StripBasePath)->handle($request, fn ($r) => response('ok'));

        return $request;
    }

    public function test_base_path_is_stripped_for_subdirectory_installations(): void
    {
        config()->set('app.url', 'https://lab.quark.am/sevak');

        $request = Request::create('https://lab.quark.am/sevak/demo-ashun', 'GET');
        $request->server->set('SCRIPT_NAME', '/sevak/public/index.php');

        $this->assertSame('/demo-ashun', $this->applyMiddleware($request)->getPathInfo());
    }

    public function test_subdirectory_root_request_resolves_to_slash(): void
    {
        config()->set('app.url', 'https://lab.quark.am/sevak');

        $request = Request::create('https://lab.quark.am/sevak/', 'GET');
        $request->server->set('SCRIPT_NAME', '/sevak/public/index.php');

        $this->assertSame('/', $this->applyMiddleware($request)->getPathInfo());
    }

    public function test_query_string_is_preserved(): void
    {
        config()->set('app.url', 'https://lab.quark.am/sevak');

        $request = Request::create('https://lab.quark.am/sevak/demo-ashun?q=test', 'GET');

        $request = $this->applyMiddleware($request);

        $this->assertSame('/demo-ashun', $request->getPathInfo());
        $this->assertSame('test', $request->query('q'));
    }

    public function test_root_deployments_are_left_untouched(): void
    {
        config()->set('app.url', 'https://example.am');

        $request = Request::create('https://example.am/demo-ashun', 'GET');

        $this->assertSame('/demo-ashun', $this->applyMiddleware($request)->getPathInfo());
    }

    public function test_home_route_matches_after_stripping(): void
    {
        config()->set('app.url', 'https://lab.quark.am/sevak');

        $request = Request::create('https://lab.quark.am/sevak/', 'GET');
        $request->server->set('SCRIPT_NAME', '/sevak/public/index.php');
        $this->applyMiddleware($request);

        $route = app('router')->getRoutes()->match($request);

        $this->assertSame('home', $route->getName());
    }
}
