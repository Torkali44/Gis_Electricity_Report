<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $adminEmail = config('auth.admin.email');
        $adminName = config('auth.admin.name');
        $adminPassword = config('auth.admin.password');

        if (! is_string($adminName) || ! is_string($adminEmail) || ! is_string($adminPassword)
            || trim($adminName) === '' || trim($adminEmail) === '' || trim($adminPassword) === '') {
            throw new \RuntimeException(
                'Set ADMIN_NAME, ADMIN_EMAIL, and ADMIN_PASSWORD in .env before running the database seeder.'
            );
        }

        User::query()->updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => $adminName,
                'password' => $adminPassword,
                'is_admin' => true,
            ]
        );

        $this->call(ReadyPresetSeeder::class);
    }
}
