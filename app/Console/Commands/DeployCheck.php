<?php

namespace App\Console\Commands;

use App\Support\Seo;
use App\Support\UrlConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * One command that answers "why does the deployed site load without styles?".
 *
 * It compares the three URLs of a deployment (APP_URL, ASSET_URL, canonical
 * base), resolves their hostnames and checks that every file the built
 * manifest references is really there. A punycode typo — two Latin letters in
 * a hand-copied "xn--" name — shows up here as a host that does not exist.
 */
class DeployCheck extends Command
{
    protected $signature = 'deploy:check
                            {--skip-dns : Do not resolve hostnames (offline or locked down servers)}';

    protected $description = 'Verify the deployment URLs, DNS and built assets';

    /** @var list<array{level: string, message: string, hint?: string}> */
    protected array $problems = [];

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <options=bold>Deployment check</>');
        $this->line('  '.str_repeat('─', 68));

        $this->checkApplication();
        $urls = $this->checkUrls();
        $this->checkAssets($urls['asset']);
        $this->checkCrawlers($urls['canonical']);

        $this->newLine();

        $errors = count(array_filter($this->problems, fn (array $p): bool => $p['level'] === 'error'));

        if ($errors === 0) {
            $this->info('  Everything the browser needs is in place.');

            return self::SUCCESS;
        }

        $this->error("  {$errors} problem(s) found — see the hints above.");

        return self::FAILURE;
    }

    protected function checkApplication(): void
    {
        $this->group('Application');

        $this->reportCheck(filled(config('app.key')), 'APP_KEY', filled(config('app.key')) ? 'set' : 'missing', 'Run: php artisan key:generate');

        if (config('app.debug')) {
            $this->reportWarn('APP_DEBUG', 'true', 'In production this prints stack traces and opens /__diagnostics. Set APP_DEBUG=false.');
        } else {
            $this->reportOk('APP_DEBUG', 'false');
        }

        $cached = app()->configurationIsCached();
        $this->line(sprintf('  %s %-20s %s', $cached ? '✔' : '·', 'config cache', $cached ? 'cached' : 'not cached (php artisan config:cache)'));
    }

    /**
     * @return array{app: string, asset: string, canonical: string}
     */
    protected function checkUrls(): array
    {
        $this->group('URLs');

        $app = (string) config('app.url');
        $asset = (string) config('app.asset_url');
        $canonical = (string) config('poems.canonical_base');
        $base = UrlConfig::basePath();

        $this->reportOk('APP_URL', $app !== '' ? $app : '(empty)', $app === '' ? 'route() cannot build links without it.' : null);
        $this->reportOk('ASSET_URL', $asset !== '' ? $asset : '(derived)', null);
        $this->reportOk('canonical base', $canonical !== '' ? $canonical : '(empty)');
        $this->line(sprintf('  %s %-20s %s', '·', 'base path', $base !== '' ? $base : '(root)'));

        $this->checkHost('APP_URL', $app);
        $this->checkHost('ASSET_URL', $asset);
        $this->checkHost('canonical base', $canonical);

        $appHost = UrlConfig::host($app);
        $assetHost = UrlConfig::host($asset);

        if ($appHost !== null && $assetHost !== null && $appHost !== $assetHost) {
            $this->reportFail(
                'host mismatch',
                "ASSET_URL ({$assetHost}) and APP_URL ({$appHost})",
                'Both must name the domain the visitor opened, or the browser looks for the styles on a host that does not exist.',
            );
        }

        // Assets live in the folder "public": they keep the base path while the
        // routes do not.
        if ($base !== '' && $asset !== '' && ! str_contains((string) parse_url($asset, PHP_URL_PATH), rtrim($base, '/'))) {
            $this->reportFail(
                'ASSET_URL path',
                "missing {$base}",
                'The built files are served from '.$base.'/build/…; add it to ASSET_URL.',
            );
        }

        return ['app' => $app, 'asset' => $asset, 'canonical' => $canonical];
    }

    protected function checkHost(string $label, string $url): void
    {
        $host = UrlConfig::host($url);

        if ($host === null) {
            return;
        }

        $unicode = UrlConfig::unicodeHost($host);

        if ($this->option('skip-dns')) {
            $this->line(sprintf('  %s %-20s %s%s', '·', $label.' host', $host, $unicode ? " ({$unicode})" : ''));

            return;
        }

        // A test may replace the resolver ("deploy.dns"); the default is the
        // time-bounded UDP client.
        $lookup = $this->laravel->bound('deploy.dns') ? $this->laravel->make('deploy.dns') : null;

        $resolves = UrlConfig::resolves($host, $lookup instanceof \Closure ? $lookup : null);

        if ($resolves === null) {
            $this->line(sprintf('  %s %-20s %s — resolver unavailable', '·', $label.' DNS', $host));

            return;
        }

        if ($resolves) {
            $this->reportOk($label.' DNS', $host.($unicode ? " ({$unicode})" : ''));

            return;
        }

        $hint = $unicode
            ? 'This punycode reads as «'.$unicode.'». If that is not your domain, the name was mistyped: '
                .'print the right one with php -r "echo idn_to_ascii(\'պոեզիա.հայ\', IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);"'
            : 'The hostname does not exist: register the domain, point its NS at Cloudflare, or fix the typo.';

        $this->reportFail($label.' DNS', $host.' — does not exist', $hint);
    }

    protected function checkAssets(string $assetUrl): void
    {
        $this->group('Built assets');

        $manifest = public_path('build/manifest.json');

        if (! File::exists($manifest)) {
            $this->reportFail('manifest', 'public/build/manifest.json missing', 'Run: npm ci && npm run build, then upload public/build.');

            return;
        }

        $files = $this->manifestFiles($manifest);
        $missing = [];

        foreach ($files as $file) {
            if (! File::exists(public_path($file))) {
                $missing[] = $file;
            }
        }

        if ($missing === []) {
            $this->reportOk('manifest', count($files).' file(s) present');
        } else {
            $this->reportFail('manifest', count($missing).' referenced file(s) missing', implode(', ', array_slice($missing, 0, 3)));
        }

        // The URL the browser is told to fetch must be reachable from the
        // public host — print it, so it can be opened by hand.
        if ($assetUrl !== '') {
            $probe = rtrim($assetUrl, '/').'/'.$this->firstAsset($manifest);
            $this->line(sprintf('  %s %-20s %s', '·', 'first asset', $probe));
        }
    }

    protected function checkCrawlers(string $canonical): void
    {
        $this->group('Crawlers');

        $appHost = UrlConfig::host((string) config('app.url'));
        $canonicalHost = UrlConfig::host($canonical);

        if ($appHost !== null && $canonicalHost !== null && $appHost !== $canonicalHost) {
            $this->reportWarn(
                'canonical host',
                $canonicalHost,
                "APP_URL names {$appHost}: search engines would index a different address than the one visitors use.",
            );
        } else {
            $this->reportOk('canonical', Seo::canonical());
        }

        $this->line(sprintf('  %s %-20s %s', '·', 'sitemap', Seo::canonical('sitemap.xml')));
        $this->line(sprintf('  %s %-20s %s', '·', 'robots', Seo::canonical('robots.txt')));
        $this->line(sprintf('  %s %-20s %s', '·', 'example poem', Seo::canonical('{slug}')));
    }

    /** @return list<string> */
    protected function manifestFiles(string $manifest): array
    {
        $decoded = json_decode((string) File::get($manifest), true);
        $files = [];

        foreach (is_array($decoded) ? $decoded : [] as $entry) {
            foreach (['file', 'css', 'assets'] as $key) {
                foreach ((array) ($entry[$key] ?? []) as $file) {
                    if (is_string($file) && $file !== '') {
                        $files[] = 'build/'.$file;
                    }
                }
            }
        }

        return array_values(array_unique($files));
    }

    protected function firstAsset(string $manifest): string
    {
        $files = $this->manifestFiles($manifest);

        return $files[0] ?? '';
    }

    protected function group(string $title): void
    {
        $this->newLine();
        $this->line('  <options=bold>'.$title.'</>');
    }

    protected function reportOk(string $label, string $value, ?string $hint = null): void
    {
        $this->line(sprintf('  %s %-20s %s', '✔', $label, $value));

        if ($hint !== null) {
            $this->line('    <fg=yellow>'.$hint.'</>');
            $this->problems[] = ['level' => 'warning', 'message' => $label];
        }
    }

    protected function reportWarn(string $label, string $value, string $hint): void
    {
        $this->line(sprintf('  %s %-20s %s', '⚠', $label, $value));
        $this->line('    <fg=yellow>'.$hint.'</>');
        $this->problems[] = ['level' => 'warning', 'message' => $label];
    }

    protected function reportFail(string $label, string $value, string $hint): void
    {
        $this->line(sprintf('  %s %-20s %s', '✖', $label, $value));
        $this->line('    <fg=red>'.$hint.'</>');
        $this->problems[] = ['level' => 'error', 'message' => $label];
    }

    protected function reportCheck(bool $condition, string $label, string $value, string $hint): void
    {
        $condition ? $this->reportOk($label, $value) : $this->reportFail($label, $value, $hint);
    }
}
