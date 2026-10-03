<?php

namespace App\Providers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Carbon ikut memakai locale aplikasi agar translatedFormat()
        // menghasilkan nama bulan Bahasa Indonesia (mis. "03 Okt 2026").
        Carbon::setLocale(config('app.locale'));

        // Gate ini hanya untuk menyembunyikan UI (mis. @can('admin')),
        // bukan pengganti authorization data. Pembatasan data tetap memakai
        // scope TraineeTask::visibleTo() di sisi server.
        Gate::define('admin', fn (?AuthenticatableContract $user) => User::isAdminActor($user));
    }
}
