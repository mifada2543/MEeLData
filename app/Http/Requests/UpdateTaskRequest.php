<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi update task.
 *
 * `trainee_id` tidak pernah diterima di sini: ownership tidak dapat
 * diubah dari form mana pun (termasuk oleh admin).
 */
class UpdateTaskRequest extends FormRequest
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
            'task' => ['required', 'string', 'max:255'],
            'desc' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'deadline' => ['required', 'date', 'after_or_equal:start_date'],
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
     * @return array<string, mixed>
     */
    public function taskAttributes(): array
    {
        $data = $this->validated();

        if (empty($data['status'])) {
            unset($data['status']);
        }

        return $data;
    }
}
