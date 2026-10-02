<?php

use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TraineeTaskController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Root
|--------------------------------------------------------------------------
| Guest -> /login, Trainee -> /dashboard, Admin -> /admin/dashboard
*/
Route::get('/', function () {
    if (Auth::guard('admin')->check()) {
        return redirect()->route('admin.dashboard');
    }

    if (Auth::guard('web')->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

/*
|--------------------------------------------------------------------------
| Authentication — Trainee (guard: web)
|--------------------------------------------------------------------------
*/
Route::middleware('guest:web')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');

    Route::get('register', [RegisterController::class, 'create'])->name('register');
    Route::post('register', [RegisterController::class, 'store'])->name('register.store');
});

Route::post('logout', [LoginController::class, 'destroy'])
    ->middleware('auth:web')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Authentication — Admin (guard: admin)
|--------------------------------------------------------------------------
| Tidak ada registrasi admin; akun hanya dibuat melalui AdminSeeder.
*/
Route::middleware('guest:admin')->group(function () {
    Route::get('admin/login', [AdminLoginController::class, 'create'])->name('admin.login');
    Route::post('admin/login', [AdminLoginController::class, 'store'])->name('admin.login.store');
});

Route::post('admin/logout', [AdminLoginController::class, 'destroy'])
    ->middleware('auth:admin')
    ->name('admin.logout');

/*
|--------------------------------------------------------------------------
| Trainee area (guard: web)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:web')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('tasks', [TraineeTaskController::class, 'index'])->name('tasks.index');
    Route::get('tasks/create', [TraineeTaskController::class, 'create'])->name('tasks.create');
    Route::post('tasks', [TraineeTaskController::class, 'store'])->name('tasks.store');
    Route::get('tasks/export', [TraineeTaskController::class, 'export'])->name('tasks.export');
    Route::get('tasks/{task}/edit', [TraineeTaskController::class, 'edit'])->name('tasks.edit');
    Route::match(['put', 'patch'], 'tasks/{task}', [TraineeTaskController::class, 'update'])->name('tasks.update');
    Route::patch('tasks/{task}/status', [TraineeTaskController::class, 'status'])->name('tasks.status');
});

/*
|--------------------------------------------------------------------------
| Admin area (guard: admin)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware('auth:admin')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('tasks', [TraineeTaskController::class, 'index'])->name('tasks.index');
    Route::get('tasks/create', [TraineeTaskController::class, 'create'])->name('tasks.create');
    Route::post('tasks', [TraineeTaskController::class, 'store'])->name('tasks.store');
    Route::get('tasks/export', [TraineeTaskController::class, 'export'])->name('tasks.export');

    Route::post('tasks/import', [TraineeTaskController::class, 'import'])->name('tasks.import');
    Route::get('tasks/import/template', [TraineeTaskController::class, 'importTemplate'])->name('tasks.import.template');

    Route::post('tasks/bulk-delete', [TraineeTaskController::class, 'bulkDelete'])->name('tasks.bulk-delete');

    Route::get('tasks/{task}/edit', [TraineeTaskController::class, 'edit'])->name('tasks.edit');
    Route::match(['put', 'patch'], 'tasks/{task}', [TraineeTaskController::class, 'update'])->name('tasks.update');
    Route::patch('tasks/{task}/status', [TraineeTaskController::class, 'status'])->name('tasks.status');
    Route::delete('tasks/{task}', [TraineeTaskController::class, 'destroy'])->name('tasks.destroy');
});
