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
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please choose an Excel file to import.',
            'file.mimes' => 'The file must be a valid .xlsx Excel file.',
            'file.max' => 'The file may not be greater than 10 MB.',
        ];
    }
}
