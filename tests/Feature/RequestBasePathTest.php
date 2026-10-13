<?php

namespace Tests\Feature;

use App\Support\RequestBasePath;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Subdirectory deployments (docroot symlinked to "public") lose the base path,
 * which used to make Laravel treat "/sevak" as a route segment.
 */
class RequestBasePathTest extends TestCase
{
    public function test_base_path_is_taken_from_app_url_for_subdirectory_installations(): void
    {
        $request = Request::create('https://lab.quark.am/sevak/demo-ashun', 'GET');
        $request->server->set('SCRIPT_NAME', '/sevak/public/index.php');
        $request->server->set('SCRIPT_FILENAME', '/home/user/lab.quark.am/sevak/public/index.php');

        RequestBasePath::apply($request, 'https://lab.quark.am/sevak');

        $this->assertSame('/demo-ashun', $request->getPathInfo());
    }

    public function test_subdirectory_root_request_resolves_to_slash(): void
    {
        $request = Request::create('https://lab.quark.am/sevak/', 'GET');
        $request->server->set('SCRIPT_NAME', '/sevak/public/index.php');
        $request->server->set('SCRIPT_FILENAME', '/home/user/lab.quark.am/sevak/public/index.php');

        RequestBasePath::apply($request, 'https://lab.quark.am/sevak');

        $this->assertSame('/', $request->getPathInfo());
    }

    public function test_root_deployments_are_left_untouched(): void
    {
        $request = Request::create('https://example.am/demo-ashun', 'GET');
        $request->server->set('SCRIPT_NAME', '/index.php');

        RequestBasePath::apply($request, 'https://example.am');

        $this->assertSame('/demo-ashun', $request->getPathInfo());
        $this->assertSame('/index.php', $request->server->get('SCRIPT_NAME'));
    }
}
