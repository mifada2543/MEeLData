<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menggabungkan akun admin ke dalam tabel `users` melalui kolom `role`.
 *
 * Alasan: aplikasi hanya membutuhkan satu gateway login. Satu tabel
 * menghilangkan duplikasi data akun dan membuat proses login cukup satu
 * kali verifikasi bcrypt.
 *
 * Keamanan yang tetap dijaga:
 * - Registrasi publik selalu menghasilkan role = 'trainee'
 *   (kolom `role` tidak ada di $fillable User).
 * - Akun admin hanya dapat dibuat melalui AdminSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'trainee'])->default('trainee')->after('email');
        });

        // Backfill akun admin ke tabel users.
        //
        // Catatan: memakai insertOrIgnore saja TIDAK cukup. Setelah
        // rollback, baris admin masih ada di `users` dengan role default
        // `trainee`, sehingga insert akan di-skip dan role tidak pernah
        // dipulihkan. Karena itu baris yang sudah ada di-update, dan hanya
        // email yang benar-benar baru yang di-insert.
        if (Schema::hasTable('admins')) {
            DB::table('admins')
                ->orderBy('id')
                ->chunkById(100, function ($admins) {
                    $now = now();

                    foreach ($admins as $admin) {
                        $attributes = [
                            'name' => $admin->name,
                            'password' => $admin->password,
                            'role' => 'admin',
                            'updated_at' => $admin->updated_at ?? $now,
                        ];

                        $exists = DB::table('users')->where('email', $admin->email)->exists();

                        if ($exists) {
                            DB::table('users')->where('email', $admin->email)->update($attributes);

                            continue;
                        }

                        DB::table('users')->insert($attributes + [
                            'email' => $admin->email,
                            'created_at' => $admin->created_at ?? $now,
                        ]);
                    }
                });

            Schema::drop('admins');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->timestamps();
            });
        }

        // Kembalikan akun admin ke tabel admins.
        DB::table('users')
            ->where('role', 'admin')
            ->orderBy('id')
            ->chunkById(100, function ($users) {
                DB::table('admins')->insertOrIgnore(
                    $users->map(fn ($user) => [
                        'name' => $user->name,
                        'email' => $user->email,
                        'password' => $user->password,
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                    ])->all()
                );
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
