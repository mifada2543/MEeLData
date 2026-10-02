<?php

namespace App\Providers;

use App\Models\Admin;
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
        // Gate ini hanya untuk menyembunyikan UI (mis. @can('admin')),
        // bukan pengganti authorization data. Pembatasan data tetap memakai
        // scope TraineeTask::visibleTo() di sisi server.
        Gate::define('admin', fn ($user) => $user instanceof Admin);
    }
}
