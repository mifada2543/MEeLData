<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TaskLiveController;
use App\Http\Controllers\TraineeTaskController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Root
|--------------------------------------------------------------------------
| Guest -> /login, Trainee/Admin -> /dashboard atau /admin/dashboard
| sesuai perannya.
*/
Route::get('/', function () {
    /*
     * `Auth::user()` bertipe `Authenticatable|null` menurut deklarasi
     * facade-nya, sehingga IDE menganggap `isAdmin()` tidak ada - padahal
     * guard `web` memakai `App\Models\User` (config/auth.php). Anotasi ini
     * hanya memberi tahu IDE apa yang sebenarnya dikembalikan.
     */
    /** @var User|null $user */
    $user = Auth::user();

    if ($user?->isAdmin()) {
        return redirect()->route('admin.dashboard');
    }

    return redirect()->route($user ? 'dashboard' : 'login');
})->name('home');

/*
|--------------------------------------------------------------------------
| Authentication — satu gateway untuk admin & trainee
|--------------------------------------------------------------------------
| Admin dapat login melalui halaman yang sama dengan trainee. Akun admin
| hanya dibuat oleh AdminSeeder; tidak ada registrasi admin.
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
| Trainee area
|--------------------------------------------------------------------------
*/
Route::middleware('auth:web')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Data live (waktu server + task terlambat) untuk hitungan mundur.
    Route::get('tasks/live', TaskLiveController::class)->name('tasks.live');

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
| Admin area
|--------------------------------------------------------------------------
| `can:admin` menghasilkan 403 untuk trainee yang membuka route di sini.
*/
Route::prefix('admin')->name('admin.')->middleware(['auth:web', 'can:admin'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('tasks/live', TaskLiveController::class)->name('tasks.live');

    Route::get('tasks', [TraineeTaskController::class, 'index'])->name('tasks.index');
    Route::get('tasks/create', [TraineeTaskController::class, 'create'])->name('tasks.create');
    Route::post('tasks', [TraineeTaskController::class, 'store'])->name('tasks.store');
    Route::get('tasks/export', [TraineeTaskController::class, 'export'])->name('tasks.export');

    Route::post('tasks/import', [TraineeTaskController::class, 'import'])->name('tasks.import');
    Route::get('tasks/import/template', [TraineeTaskController::class, 'importTemplate'])->name('tasks.import.template');

    Route::post('tasks/bulk-delete', [TraineeTaskController::class, 'bulkDelete'])->name('tasks.bulk-delete');

    /*
     * Sampah & pemulihan task. Terdaftar sebelum route `{task}` agar tidak
     * tertangkap route parametrik. Tetap berada di dalam grup `can:admin`.
     */
    Route::get('tasks/trash', [TraineeTaskController::class, 'trashed'])->name('tasks.trash');
    Route::get('tasks/{task}/restore', [TraineeTaskController::class, 'restoreForm'])->name('tasks.restore.form');
    Route::post('tasks/{task}/restore', [TraineeTaskController::class, 'restore'])->name('tasks.restore');

    Route::get('tasks/{task}/edit', [TraineeTaskController::class, 'edit'])->name('tasks.edit');
    Route::match(['put', 'patch'], 'tasks/{task}', [TraineeTaskController::class, 'update'])->name('tasks.update');
    Route::patch('tasks/{task}/status', [TraineeTaskController::class, 'status'])->name('tasks.status');
    Route::delete('tasks/{task}', [TraineeTaskController::class, 'destroy'])->name('tasks.destroy');
});
