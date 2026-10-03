<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Akun admin dibuat hanya melalui seeder ini (tidak ada registrasi admin).
 * Aman dijalankan berulang kali karena memakai updateOrCreate.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@meel.test')],
            [
                'name' => env('ADMIN_NAME', 'Program Admin'),
                'password' => env('ADMIN_PASSWORD', 'admin123'),
                'role' => User::ROLE_ADMIN,
            ],
        );
    }
}
