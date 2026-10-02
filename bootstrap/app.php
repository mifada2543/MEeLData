<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Dua guard memakai satu konfigurasi redirect, sehingga callback-nya
        // menerima Request dan memilih tujuan sesuai area rute.
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->routeIs('admin.*')
                ? route('admin.login')
                : route('login')
        );

        $middleware->redirectUsersTo(
            fn (Request $request) => $request->routeIs('admin.*')
                ? route('admin.dashboard')
                : route('dashboard')
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Trainee yang accessing route admin harus mendapat 403, bukan redirect
        // ke form login admin. Guest tetap diarahkan ke halaman login.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->routeIs('admin.*') && Auth::guard('web')->check()) {
                return response()->view('errors.403', status: 403);
            }

            return null;
        });
    })->create();
