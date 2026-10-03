<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Validasi kredential login trainee (guard web).
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Pesan error kredential yang sama untuk email maupun password agar
     * tidak membocorkan informasi keberadaan sebuah akun.
     */
    public function authenticate(): void
    {
        // Facade `Auth`, bukan helper `auth()`: method helper diteruskan
        // lewat `__call` sehingga analis statis tidak melihat `attempt()`.
        // Facade mendeklarikannya lewat `@method static bool attempt(...)`.
        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }
    }
}
