<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use App\Models\TraineeTask;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi inline status update dari tabel.
 *
 * Aturan:
 * 1. `late` tidak dapat dipilih manual (hanya scheduler).
 * 2. Task yang sudah berstatus `late` tidak dapat diubah sama sekali,
 *    karena `late` adalah status milik sistem (PRD OQ-05).
 */
class UpdateStatusRequest extends FormRequest
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
            'status' => [
                'required',
                Rule::in(TaskStatus::manualValues()),
                $this->rejectSystemOwnedStatus(),
            ],
        ];
    }

    /**
     * Menolak perubahan status bila task sedang berstatus `late`.
     *
     * Task dicari dengan scope `visibleTo` supaya pesan validasi tidak
     * membocorkan keberadaan task milik trainee lain (tetap 403/404 di
     * controller, bukan 422).
     */
    protected function rejectSystemOwnedStatus(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $task = TraineeTask::query()
                ->visibleTo($this->user())
                ->find($this->route('task'));

            if ($task?->status->isSystemOwned()) {
                $fail('Status "Terlambat" diatur otomatis oleh sistem dan tidak dapat diubah.');
            }
        };
    }

    /**
     * Pesan overridden agar menyebut opsi yang benar-benar tampil di UI.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Status tidak valid. "Terlambat" diatur otomatis oleh sistem, jadi tidak dapat dipilih.',
        ];
    }
}
