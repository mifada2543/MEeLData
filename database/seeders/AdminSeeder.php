<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

/**
 * Akun admin hanya dapat dibuat melalui seeder ini.
 * Aman dijalankan berulang kali karena memakai updateOrCreate.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@meel.test');

        Admin::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Program Admin'),
                'password' => env('ADMIN_PASSWORD', 'admin123'),
            ],
        );
    }
}
