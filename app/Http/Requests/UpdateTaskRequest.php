<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use App\Models\TraineeTask;
use App\Rules\NotBeforeNow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Validasi update task.
 *
 * `trainee_id` tidak pernah diterima di sini: ownership tidak dapat
 * diubah dari form mana pun (termasuk oleh admin).
 *
 * Aturan tanggal sengaja lebih longgar daripada create: nilai lama yang
 * sudah lewat tetap boleh dipertahankan, dan aturan "tidak boleh lampau"
 * hanya dipasang bila tanggal benar-benar diubah. Tanpa itu, task lama
 * (mis. mulai bulan lalu) tidak akan bisa diedit sama sekali - bahkan
 * hanya untuk memperbaiki deskripsi.
 *
 * Task `late` punya aturan tambahan: hanya `deadline` yang boleh diubah
 * (lihat `TraineeTask::hasLockedEditFields()`). Field yang terkunci di-force
 * ulang ke nilai tersimpan di `prepareForValidation()`, jadi request buatan
 * tidak bisa membranesihnya - atribut HTML `readonly` bukan pengaman.
 */
class UpdateTaskRequest extends FormRequest
{
    /**
     * Field yang bebas diubah pada task `late`.
     *
     * @var list<string>
     */
    protected const LATE_EDITABLE_FIELDS = ['deadline'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Memaksa field yang terkunci kembali ke nilai tersimpan.
     *
     * Dipanggil sebelum `rules()`, jadi validasi dan attribute final sama-sama
     * melihat nilai yang benar - bukan nilai dari payload.
     */
    protected function prepareForValidation(): void
    {
        $current = $this->currentTask();

        if ($current === null || ! $current->hasLockedEditFields()) {
            return;
        }

        // `status` sengaja TIDAK di-merge ke sini. Nilainya pasti `late`,
        // sedangkan `late` tidak termasuk `manualValues()` - mem-merge-nya
        // justru membuat request sendiri gagal validasi. Status tidak perlu
        // dipulihkan karena `taskAttributes()` membuangnya sepenuhnya.
        $this->merge([
            'task' => $current->task,
            'desc' => $current->desc,
            'start_date' => $current->start_date?->format('Y-m-d\TH:i'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $current = $this->currentTask();

        // Pada task late `start_date` terkunci ke nilai lampau, jadi aturan
        // "tidak boleh lampau" jelas tidak berlaku untuk field itu.
        $locked = $current?->hasLockedEditFields() ?? false;

        return [
            'task' => ['required', 'string', 'max:255'],
            'desc' => ['nullable', 'string'],
            'start_date' => array_values(array_filter([
                'required',
                'date',
                ! $locked && $this->dateChanged($current, 'start_date')
                    ? new NotBeforeNow('Tanggal mulai')
                    : null,
            ])),
            'deadline' => array_values(array_filter([
                'required',
                'date',
                'after_or_equal:start_date',
                $this->dateChanged($current, 'deadline')
                    ? new NotBeforeNow('Tanggal deadline')
                    : null,
            ])),
            'status' => ['nullable', Rule::in(TaskStatus::manualValues())],
        ];
    }

    /**
     * Data siap disimpan.
     *
     * Untuk task `late`, payload dipangkas ke `deadline` saja. Ini lapisan
     * kedua setelah `prepareForValidation()`: walau request dibuat-buat,
     * `task`, `desc`, `start_date`, dan `status` tidak akan sampai ke model.
     *
     * @return array<string, mixed>
     */
    public function taskAttributes(): array
    {
        $data = $this->validated();

        if ($this->currentTask()?->hasLockedEditFields() ?? false) {
            $data = array_intersect_key($data, array_flip(self::LATE_EDITABLE_FIELDS));
        }

        if (empty($data['status'])) {
            unset($data['status']);
        }

        return $data;
    }

    /**
     * Task yang sedang diedit, atau `null` bila tidak ditemukan/tidak terlihat.
     *
     * Route `tasks/{task}` mengirim UUID sebagai string, BUKAN objek model,
     * jadi model harus dicari ulang di sini.
     *
     * Dicari dengan scope `visibleTo` supaya pesan validasi tidak
     * membocorkan keberadaan task milik trainee lain. Kalau tidak ditemukan,
     * aturan dibuat lebih ketat dan controller tetap mengembalikan 403/404.
     */
    protected function currentTask(): ?TraineeTask
    {
        $task = $this->route('task');

        if ($task instanceof TraineeTask) {
            return $task;
        }

        if (is_string($task) && $task !== '') {
            return TraineeTask::query()
                ->visibleTo($this->user())
                ->find($task);
        }

        return null;
    }

    /**
     * Apakah tanggal pada field ini benar-benar berubah?
     *
     * Input `datetime-local` berpresisi menit sedangkan kolom database
     * menyimpan presisi detik, jadi dibandingkan per menit - tanpa ini
     * task yang tersimpan `11:24:35` akan dianggap "berubah" (menjadi
     * `11:24`) lalu ditolak padahal admin tidak menyentuh field-nya.
     */
    protected function dateChanged(?TraineeTask $current, string $field): bool
    {
        // Tanpa task yang tersimpan (create) aturan lampau selalu berlaku.
        if ($current === null) {
            return true;
        }

        $submitted = $this->input($field);

        if (! is_string($submitted) || $submitted === '') {
            return false;
        }

        $stored = $current->{$field};

        if ($stored === null) {
            return true;
        }

        try {
            return Carbon::parse($submitted)
                ->startOfMinute()
                ->ne($stored->copy()->startOfMinute());
        } catch (Throwable) {
            return true;
        }
    }
}
