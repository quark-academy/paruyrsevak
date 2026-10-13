<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Guards a nasty shared-hosting trap: when short_open_tag=On, Blade's
 * compiler (token_get_all) treats a literal "<?xml" / "<?" inside a template
 * as a PHP opening tag and produces broken PHP ("syntax error, unexpected
 * identifier ..."). Templates must therefore never contain raw short tags.
 */
class BladeTemplatesTest extends TestCase
{
    public function test_blade_templates_contain_no_raw_short_open_tags(): void
    {
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = (string) File::get($file->getPathname());

            if (preg_match('/<\?/', $contents) === 1) {
                $offenders[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Blade templates must not contain "<?...": it breaks compilation when short_open_tag=On.',
        );
    }
}
