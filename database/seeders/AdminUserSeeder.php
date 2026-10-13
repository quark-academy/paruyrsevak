<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@paruyrsevak.am');
        $password = (string) env('ADMIN_PASSWORD', 'password');

        $admin = User::query()->firstOrNew(['email' => $email]);
        $admin->name = (string) env('ADMIN_NAME', 'Administrator');
        $admin->password = Hash::make($password);
        $admin->is_admin = true;
        $admin->email_verified_at ??= now();
        $admin->save();

        $this->command?->info("Admin ready: {$email}");
    }
}
