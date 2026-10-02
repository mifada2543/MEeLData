<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use App\Models\Admin;
use App\Models\User;
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $isAdmin = $this->user() instanceof Admin;

        return [
            'trainee_id' => [
                $isAdmin ? 'required' : 'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'task' => ['required', 'string', 'max:255'],
            'desc' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'deadline' => ['required', 'date', 'after_or_equal:start_date'],
            // `late` hanya dihasilkan scheduler, tidak boleh dipilih manual.
            'status' => ['nullable', Rule::in(TaskStatus::manualValues())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'deadline.after_or_equal' => 'The deadline must be a date after or equal to the start date.',
            'status.in' => 'The status must be one of: '.implode(', ', TaskStatus::manualValues()).'.',
        ];
    }

    /**
     * Trainee tidak boleh menentukan trainee_id dari request.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->user() instanceof Admin) {
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

        $data['trainee_id'] = $this->user() instanceof Admin
            ? $data['trainee_id']
            : $this->user()?->id;

        if (empty($data['status'])) {
            $data['status'] = TaskStatus::Progress->value;
        }

        return $data;
    }
}
