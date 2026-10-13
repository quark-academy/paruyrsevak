<?php

namespace Tests\Feature;

use App\Http\Middleware\NormalizeBasePath;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Subdirectory deployments (docroot symlinked to "public") lose the base path,
 * which used to break routing (catch-all "/{poem}") and generated URLs
 * (redirects without the "/sevak" prefix).
 */
class NormalizeBasePathTest extends TestCase
{
    private function handle(Request $request): Request
    {
        (new NormalizeBasePath)->handle($request, fn ($r) => response('ok'));

        return $request;
    }

    private function subdirectoryRequest(string $uri): Request
    {
        $request = Request::create('https://lab.quark.am'.$uri, 'GET');
        $request->server->set('SCRIPT_NAME', '/sevak/public/index.php');
        $request->server->set('SCRIPT_FILENAME', '/home/user/lab.quark.am/sevak/public/index.php');

        return $request;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'https://lab.quark.am/sevak');
    }

    public function test_base_path_is_derived_from_the_reported_script_name(): void
    {
        $request = $this->handle($this->subdirectoryRequest('/sevak/'));

        $this->assertSame('/sevak', $request->getBaseUrl());
        $this->assertSame('/', $request->getPathInfo());
    }

    public function test_poem_page_path_and_root_are_correct(): void
    {
        $request = $this->handle($this->subdirectoryRequest('/sevak/demo-ashun'));

        $this->assertSame('/demo-ashun', $request->getPathInfo());
        $this->assertSame('https://lab.quark.am/sevak', $request->root());
    }

    public function test_home_route_matches_instead_of_the_catch_all(): void
    {
        $request = $this->handle($this->subdirectoryRequest('/sevak/'));

        $this->assertSame('home', app('router')->getRoutes()->match($request)->getName());
    }

    public function test_generated_urls_keep_the_subdirectory_prefix(): void
    {
        $request = $this->handle($this->subdirectoryRequest('/sevak/admin'));
        app()->instance('request', $request);

        $this->assertSame('https://lab.quark.am/sevak/admin', route('admin.dashboard'));
        $this->assertSame('https://lab.quark.am/sevak', route('home'));
    }

    public function test_root_deployments_are_left_untouched(): void
    {
        config()->set('app.url', 'https://example.am');

        $request = Request::create('https://example.am/demo-ashun', 'GET');
        $request->server->set('SCRIPT_NAME', '/index.php');

        $request = $this->handle($request);

        $this->assertSame('/index.php', $request->server->get('SCRIPT_NAME'));
        $this->assertSame('/demo-ashun', $request->getPathInfo());
    }
}
