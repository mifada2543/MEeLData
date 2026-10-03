<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi bulk delete (admin). Minimal satu task harus dipilih.
 */
class BulkDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'uuid'],
        ];
    }

    /**
     * Pesan overridden karena aturan min(1) milik aplikasi, bukan bawaan.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Pilih minimal satu tugas untuk dihapus.',
            'ids.min' => 'Pilih minimal satu tugas untuk dihapus.',
        ];
    }
}
