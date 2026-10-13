<?php

namespace Database\Seeders;

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
    }
}
