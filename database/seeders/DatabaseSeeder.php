<?php

namespace Database\Seeders;

use App\Services\OriginalTracksImporter;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);

        // Demo poems are useful locally but usually unwanted in production.
        // Disable with SEED_DEMO_POEMS=false in .env.
        if (filter_var(env('SEED_DEMO_POEMS', true), FILTER_VALIDATE_BOOL)) {
            $this->call(PoemSeeder::class);
        }

        // Archive recordings: runs only when the ./original_tracks folder with
        // the mp3 files is present (upload it to the server, then re-seed).
        // Disable with SEED_ORIGINAL_TRACKS=false in .env (or phpunit.xml).
        if (filter_var(env('SEED_ORIGINAL_TRACKS', true), FILTER_VALIDATE_BOOL)
            && app(OriginalTracksImporter::class)->resolveSource(null) !== null) {
            $this->call(OriginalTracksSeeder::class);
        }
    }
}
