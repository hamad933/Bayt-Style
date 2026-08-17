<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class S09AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('S09_ADMIN_EMAIL');
        $password = env('S09_ADMIN_PASSWORD');

        if (! is_string($email) || trim($email) === '' || ! is_string($password) || $password === '') {
            return;
        }

        User::query()->updateOrCreate(
            ['email' => Str::lower(trim($email))],
            [
                'name' => 'مشرف الكتالوج التجريبي',
                'password' => Hash::make($password),
                'role' => User::ROLE_CATALOG_ADMIN,
            ],
        );
    }
}
