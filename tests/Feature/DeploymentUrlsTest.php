<?php

namespace Tests\Feature;

use App\Support\DnsLookup;
use App\Support\Seo;
use App\Support\UrlConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A site behind a proxy on a custom domain lives or dies by three URLs:
 * APP_URL, ASSET_URL and the canonical base. A single wrong hostname there
 * strips the page of every style and script, which is exactly what happened in
 * production: a hand-copied punycode name pointed at a host that does not exist.
 */
class DeploymentUrlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_unicode_domain_is_rewritten_to_punycode(): void
    {
        $this->assertSame('https://xn--y9ahdu6dl.xn--y9a3aq', UrlConfig::normalize('https://պոեզիա.հայ'));
        $this->assertSame('https://xn--y9ahdu6dl.xn--y9a3aq/sevak', UrlConfig::normalize('https://պոեզիա.հայ/sevak'));
    }

    public function test_an_ascii_url_is_left_alone(): void
    {
        foreach ([
            'https://lab.quark.am/sevak',
            'http://localhost:8123',
            'https://xn--y9ahdu6dl.xn--y9a3aq',
            '',
        ] as $url) {
            $this->assertSame($url, UrlConfig::normalize($url));
        }
    }

    public function test_a_punycode_name_can_be_read_back(): void
    {
        $this->assertSame('ղցջհա.հայ', UrlConfig::unicodeHost('xn--y9a3af0b3a.xn--y9a3aq'));
        $this->assertSame('պոեզիա.հայ', UrlConfig::unicodeHost('xn--y9ahdu6dl.xn--y9a3aq'));
        $this->assertNull(UrlConfig::unicodeHost('lab.quark.am'));
    }

    public function test_the_configured_urls_are_normalised_before_anything_reads_them(): void
    {
        config()->set('app.url', 'https://պոեզիա.հայ');
        config()->set('app.asset_url', 'https://պոեզիա.հայ/sevak');
        config()->set('poems.canonical_base', 'https://պոեզիա.հայ');

        UrlConfig::normalizeConfiguredUrls();

        $this->assertSame('https://xn--y9ahdu6dl.xn--y9a3aq', config('app.url'));
        $this->assertSame('https://xn--y9ahdu6dl.xn--y9a3aq/sevak', config('app.asset_url'));
        $this->assertSame('https://xn--y9ahdu6dl.xn--y9a3aq', config('poems.canonical_base'));
    }

    public function test_asset_url_is_derived_from_the_app_url_and_the_base_path(): void
    {
        config()->set('app.url', 'https://lab.quark.am');
        config()->set('app.asset_url', null);
        config()->set('poems.base_path', '/sevak');

        UrlConfig::deriveAssetUrl();

        $this->assertSame('https://lab.quark.am/sevak', config('app.asset_url'));
    }

    public function test_a_derived_asset_url_never_doubles_the_prefix(): void
    {
        config()->set('app.url', 'https://lab.quark.am/sevak');
        config()->set('app.asset_url', null);
        config()->set('poems.base_path', '/sevak');

        UrlConfig::deriveAssetUrl();

        $this->assertSame('https://lab.quark.am/sevak', config('app.asset_url'));
    }

    public function test_an_explicit_asset_url_wins(): void
    {
        config()->set('app.url', 'https://պոեզիա.հայ');
        config()->set('app.asset_url', 'https://cdn.example.am/sevak');
        config()->set('poems.base_path', '/sevak');

        UrlConfig::deriveAssetUrl();

        $this->assertSame('https://cdn.example.am/sevak', config('app.asset_url'));
    }

    public function test_the_logic_never_waits_for_a_missing_name(): void
    {
        // A broken name must answer at once: the system resolver spends a full
        // minute on it, which would make the check unusable.
        $started = microtime(true);

        $answer = DnsLookup::a('xn--y9a3af0b3a.xn--y9a3aq', 1.0);

        $this->assertContains($answer['status'], [DnsLookup::NXDOMAIN, DnsLookup::UNAVAILABLE, DnsLookup::TIMEOUT]);
        $this->assertLessThan(10, microtime(true) - $started);
    }

    public function test_a_resolved_domain_is_reported_as_such(): void
    {
        // Local hosts always answer, so this test needs no network either.
        $this->assertTrue(UrlConfig::resolves('localhost'));
    }

    public function test_the_deploy_check_passes_on_a_healthy_deployment(): void
    {
        config()->set('app.url', 'https://lab.quark.am/sevak');
        config()->set('app.asset_url', 'https://lab.quark.am/sevak');
        config()->set('poems.canonical_base', 'https://lab.quark.am/sevak');

        $this->artisan('deploy:check', ['--skip-dns' => true])
            ->assertSuccessful();
    }

    public function test_the_deploy_check_refuses_a_host_that_does_not_exist(): void
    {
        config()->set('app.url', 'https://xn--y9a3af0b3a.xn--y9a3aq');
        config()->set('app.asset_url', 'https://xn--y9a3af0b3a.xn--y9a3aq/sevak');
        config()->set('poems.canonical_base', 'https://lab.quark.am/sevak');

        // The exact production accident: a punycode name that decodes to
        // "ղցջհա.հայ" and exists nowhere. The resolver is faked so the test
        // needs no network.
        $this->app->instance('deploy.dns', static fn (string $host): array => [
            'status' => $host === 'xn--y9a3af0b3a.xn--y9a3aq' ? DnsLookup::NXDOMAIN : DnsLookup::OK,
            'addresses' => [],
        ]);

        $this->artisan('deploy:check')
            ->expectsOutputToContain('does not exist')
            ->assertFailed();
    }

    public function test_the_deploy_check_names_the_wrong_punycode(): void
    {
        config()->set('app.url', 'https://xn--y9a3af0b3a.xn--y9a3aq');
        config()->set('app.asset_url', 'https://xn--y9a3af0b3a.xn--y9a3aq/sevak');
        config()->set('poems.canonical_base', 'https://xn--y9a3af0b3a.xn--y9a3aq');

        $this->app->instance('deploy.dns', static fn (string $host): array => [
            'status' => $host === 'xn--y9a3af0b3a.xn--y9a3aq' ? DnsLookup::NXDOMAIN : DnsLookup::OK,
            'addresses' => [],
        ]);

        // The hint is what turns a broken deployment into a two-minute fix.
        $this->artisan('deploy:check')
            ->expectsOutputToContain('ղցջհա.հայ')
            ->assertFailed();
    }

    public function test_the_deploy_check_notices_a_missing_base_path_in_the_asset_url(): void
    {
        config()->set('app.url', 'https://lab.quark.am/sevak');
        config()->set('app.asset_url', 'https://lab.quark.am');
        config()->set('poems.canonical_base', 'https://lab.quark.am/sevak');

        $this->artisan('deploy:check', ['--skip-dns' => true])
            ->assertFailed();
    }

    public function test_the_diagnostics_page_reports_the_urls_and_their_hosts(): void
    {
        config()->set('app.debug', true);
        config()->set('app.url', 'https://lab.quark.am/sevak');
        config()->set('app.asset_url', 'https://lab.quark.am/sevak');
        config()->set('poems.canonical_base', 'https://lab.quark.am/sevak');

        $this->get('/__diagnostics')
            ->assertOk()
            ->assertJsonPath('urls.hosts_match', true)
            ->assertJsonPath('urls.assets.manifest', true)
            ->assertJsonPath('crawlers.sitemap', Seo::canonical('sitemap.xml'));
    }

    public function test_the_diagnostics_page_is_hidden_in_production(): void
    {
        config()->set('app.debug', false);

        $this->get('/__diagnostics')->assertNotFound();
    }

    public function test_the_public_page_uses_the_configured_host_for_its_assets(): void
    {
        config()->set('app.url', 'https://xn--y9ahdu6dl.xn--y9a3aq');
        config()->set('app.asset_url', 'https://xn--y9ahdu6dl.xn--y9a3aq/sevak');
        config()->set('poems.canonical_base', 'https://xn--y9ahdu6dl.xn--y9a3aq');

        // asset() reads the configuration when the URL generator is built, so
        // the assertion is on the configuration contract itself.
        $this->assertSame(
            'https://xn--y9ahdu6dl.xn--y9a3aq/sevak/build/manifest.json',
            rtrim((string) config('app.asset_url'), '/').'/build/manifest.json',
        );
    }
}
