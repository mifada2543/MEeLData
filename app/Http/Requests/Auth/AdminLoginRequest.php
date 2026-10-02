<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Validasi kredential login admin (guard admin).
 */
class AdminLoginRequest extends FormRequest
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
        ];
    }

    public function authenticate(): void
    {
        if (! auth()->guard('admin')->attempt($this->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }
    }
}
