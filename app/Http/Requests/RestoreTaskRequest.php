<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\NotBeforeNow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Validasi pemulihan task yang sudah soft-delete.
 *
 * Tanggal WAJIB diisi ulang dan harus berada di masa depan. Tuannya dua:
 *
 * 1. Task yang terarsip biasanya terlambat, jadi memulihkannya tanpa
 *    tanggal baru akan langsung kembali berstatus terlambat.
 * 2. Ini menutup jalur back-dating yang tidak mungkin dicegah di form
 *    create, karena form ini menerima nilai apa pun yang dikirim client.
 *
 * `restoreWithDates()` di model yang menerapkan perubahan status
 * `late` -> `progress` setelah tanggal diganti.
 */
class RestoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Hanya admin; dijaga juga oleh middleware `can:admin` pada route.
        return User::isAdminActor($this->user());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date', new NotBeforeNow('Tanggal mulai')],
            'deadline' => ['required', 'date', 'after_or_equal:start_date', new NotBeforeNow('Tanggal deadline')],
        ];
    }

    /**
     * Aturan `date` tidak mengubah nilai menjadi objek Carbon, jadi
     * konversi dilakukan eksplisit di sini.
     *
     * @return array{start_date: Carbon, deadline: Carbon}
     */
    public function taskDates(): array
    {
        $validated = $this->validated();

        return [
            'start_date' => Carbon::parse($validated['start_date']),
            'deadline' => Carbon::parse($validated['deadline']),
        ];
    }
}
