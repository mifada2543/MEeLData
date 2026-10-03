<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Akun aplikasi.
 *
 * Trainee dan admin memakai tabel yang sama; keduanya dibedakan oleh
 * kolom `role`. Kolom ini TIDAK dapat di-mass-assignment sehingga
 * registrasi publik selalu menghasilkan role `trainee`.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_TRAINEE = 'trainee';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => 'string',
        ];
    }

    /**
     * @return HasMany<TraineeTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(TraineeTask::class, 'trainee_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isTrainee(): bool
    {
        return ! $this->isAdmin();
    }

    /**
     * Apakah `$actor` adalah admin.
     *
     * Dipakai di tempat kita hanya memegang `?Authenticatable` -
     * `$request->user()`, `$this->user()`, parameter scope - dan perlu
     * memanggil `isAdmin()`. Kontrak `Authenticatable` memang tidak
     * mendeklarasikan method itu, sehingga pemanggilannya tidak bisa
     * diverifikasi analis statis.
     *
     * Diperiksa dengan `instanceof`, bukan dengan membandingkan konstanta
     * string: kalau suatu saat guard memakai model lain, hasilnya `false`
     * (gagal tertutup) alih-alih fatal error di tengah request.
     */
    public static function isAdminActor(?AuthenticatableContract $actor): bool
    {
        return $actor instanceof self && $actor->isAdmin();
    }
}
