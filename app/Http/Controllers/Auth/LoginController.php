<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Satu gateway untuk admin maupun trainee: form-nya sama, tujuan
     * setelah login berbeda mengikuti role akun.
     */
    public function create(): View
    {
        return view('auth.auth', ['mode' => 'login']);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        /*
         * `Auth::user()` dideklarasikan sebagai
         * `Illuminate\Contracts\Auth\Authenticatable|null`, jadi IDE tidak
         * bisa melihat `isAdmin()` yang sebenarnya ada di model kita. Guard
         * `web` memakai `App\Models\User` (config/auth.php), jadi anotasi di
         * bawah ini menyatakan fakta, bukan mengubah perilaku apa pun.
         */
        /** @var User $user */
        $user = Auth::user();

        return redirect()->intended(
            $user->isAdmin() ? route('admin.dashboard') : route('dashboard')
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Anda telah keluar.');
    }
}
