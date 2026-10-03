<?php

use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Satu guard (`web`) untuk admin maupun trainee, sehingga redirect
        // cukup memakai konfigurasi Laravel standar. Tujuan setelah login
        // mengikuti role akun.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(
            fn () => User::isAdminActor(request()->user())
                ? route('admin.dashboard')
                : route('dashboard')
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
