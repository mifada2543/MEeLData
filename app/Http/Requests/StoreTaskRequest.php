<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use App\Models\User;
use App\Rules\NotBeforeNow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi create task.
 *
 * Trainee: `trainee_id` diabaikan sepenuhnya dan selalu menggunakan
 * authenticated user (lihat TraineeTaskController::store).
 */
class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Hanya admin yang boleh menentukan trainee; trainee tidak punya
     * jalur untuk memilih trainee lain.
     */
    protected function isAdmin(): bool
    {
        return User::isAdminActor($this->user());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $isAdmin = $this->isAdmin();

        return [
            'trainee_id' => [
                $isAdmin ? 'required' : 'nullable',
                'integer',
                // Hanya akun trainee yang boleh menjadi assignee. Akun admin
                // ditolak walau request dibuat-buat secara manual.
                Rule::exists('users', 'id')->where('role', User::ROLE_TRAINEE),
            ],
            'task' => ['required', 'string', 'max:255'],
            'desc' => ['nullable', 'string'],
            // Tanggal lampau ditolak supaya task baru tidak langsung
            // berstatus terlambat (regresi: user membuat task kemarin).
            'start_date' => ['required', 'date', new NotBeforeNow('Tanggal mulai')],
            'deadline' => ['required', 'date', 'after_or_equal:start_date', new NotBeforeNow('Tanggal deadline')],
            // `late` hanya dihasilkan scheduler, tidak boleh dipilih manual.
            'status' => ['nullable', Rule::in(TaskStatus::manualValues())],
        ];
    }

    /**
     * Trainee tidak boleh menentukan trainee_id dari request.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->isAdmin()) {
            $this->merge(['trainee_id' => $this->user()?->id]);
        }
    }

    /**
     * Data siap disimpan. `trainee_id` selalu berasal dari session
     * untuk trainee, bukan dari payload client.
     *
     * @return array<string, mixed>
     */
    public function taskAttributes(): array
    {
        $data = $this->validated();

        $data['trainee_id'] = $this->isAdmin()
            ? $data['trainee_id']
            : $this->user()?->id;

        if (empty($data['status'])) {
            $data['status'] = TaskStatus::Progress->value;
        }

        return $data;
    }
}
