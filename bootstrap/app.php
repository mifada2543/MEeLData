<?php

use App\Models\User;
use App\Support\UploadLimits;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

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
        /*
         * `post_max_size` terlampaui -> middleware ValidatePostSize melempar
         * PostTooLargeException (413) sebelum request sampai ke controller,
         * jadi ImportTasksRequest tidak pernah ikut jalan untuk kasus ini.
         *
         * Tanpa penanganan di sini, impor berkas besar berakhir di halaman
         * error "Post Too Large" yang tidak menjelaskan apa pun. Balikkan
         * saja ke halaman tugas dengan pesan yang menyebut batas server yang
         * sebenarnya.
         */
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if (! $request->is('admin/tasks/import')) {
                return null;
            }

            return back()
                ->withInput()
                ->withErrors(['file' => UploadLimits::postSizeMessage()]);
        });
    })->create();
