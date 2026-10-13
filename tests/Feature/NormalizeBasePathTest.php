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

    public function test_base_path_is_recovered_when_app_url_has_no_path(): void
    {
        // A forgotten "/sevak" suffix in APP_URL (or a config cache built
        // before it was fixed) must not turn the home page into the catch-all
        // poem page for the slug "sevak".
        config()->set('app.url', 'https://lab.quark.am');

        $request = Request::create('https://lab.quark.am/sevak', 'GET');
        $request->server->set('SCRIPT_NAME', '/sevak/public/index.php');
        $request->server->set('SCRIPT_FILENAME', '/home/user/lab.quark.am/sevak/public/index.php');

        $request = $this->handle($request);

        $this->assertSame('/sevak', $request->getBaseUrl());
        $this->assertSame('/', $request->getPathInfo());
        $this->assertSame('home', app('router')->getRoutes()->match($request)->getName());
    }

    public function test_the_home_page_survives_the_trailing_slash_redirect(): void
    {
        // "public/.htaccess" sends "/sevak/" to "/sevak" (301). Without the
        // trailing slash Symfony cannot derive the base URL, so the router used
        // to see the segment "sevak" and the catch-all "/{poem}" answered with
        // the poem of that slug.
        $request = $this->handle($this->subdirectoryRequest('/sevak'));

        $this->assertSame('/sevak', $request->getBaseUrl());
        $this->assertSame('/', $request->getPathInfo());
        $this->assertSame('home', app('router')->getRoutes()->match($request)->getName());
    }

    public function test_the_redirect_keeps_the_query_string(): void
    {
        $request = $this->handle($this->subdirectoryRequest('/sevak?source=original'));

        $this->assertSame('/sevak/?source=original', $request->server->get('REQUEST_URI'));
        $this->assertSame('/', $request->getPathInfo());
        $this->assertSame('original', $request->query('source'));
    }

    public function test_page_requests_are_never_rewritten(): void
    {
        $request = $this->handle($this->subdirectoryRequest('/sevak/poems?q=sevak'));

        $this->assertSame('/sevak/poems?q=sevak', $request->server->get('REQUEST_URI'));
        $this->assertSame('/poems', $request->getPathInfo());
        $this->assertSame('poems.index', app('router')->getRoutes()->match($request)->getName());
    }

    public function test_unrelated_requests_keep_their_path(): void
    {
        // A root deployment: nothing to normalise at all.
        config()->set('app.url', 'https://example.am');

        $request = Request::create('https://example.am/demo-ashun', 'GET');
        $request->server->set('SCRIPT_NAME', '/index.php');
        $request->server->set('SCRIPT_FILENAME', '/var/www/example.am/public/index.php');

        $request = $this->handle($request);

        $this->assertSame('/index.php', $request->server->get('SCRIPT_NAME'));
        $this->assertSame('', $request->getBaseUrl());
        $this->assertSame('/demo-ashun', $request->getPathInfo());
    }
}
