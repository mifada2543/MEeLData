<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi file import Excel. Maksimal 10 MB (OQ-4).
 */
class ImportTasksRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ];
    }

    /**
     * Pesan overridden agar menyebut batas 10 MB dengan satuan yang jelas.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Silakan pilih file Excel yang akan diimpor.',
            'file.mimes' => 'Berkas harus berupa file Excel .xlsx yang valid.',
            'file.max' => 'Ukuran berkas tidak boleh lebih dari 10 MB.',
        ];
    }
}
