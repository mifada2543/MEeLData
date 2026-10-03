<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi parameter export.
 *
 * Export mendukung dua mode:
 * - `ids[]` terisi  → export hanya task yang dicentang (selected menang)
 * - `ids[]` kosong  → export seluruh hasil filter `status` yang aktif
 *
 * `ids` tetap diinterseksi dengan scope `visibleTo()` di controller,
 * sehingga trainee tidak dapat mengekspor task trainee lain.
 */
class ExportTasksRequest extends FormRequest
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
            'ids' => ['sometimes', 'array'],
            'ids.*' => ['uuid'],
            'status' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * Daftar UUID yang dipilih user (boleh kosong).
     *
     * @return array<int, string>
     */
    public function selectedIds(): array
    {
        return array_values($this->validated('ids', []));
    }
}
